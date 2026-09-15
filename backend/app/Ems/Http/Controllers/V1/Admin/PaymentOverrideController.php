<?php

namespace App\Ems\Http\Controllers\V1\Admin;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Services\PaymentFulfillmentService;
use App\Ems\Services\EmsActivityLogger;
use App\Ems\Support\ApiResponse;
use App\Ems\Support\EmsPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentOverrideController extends EmsController
{
    /**
     * POST /api/v1/ems/admin/payments/override
     * Super-admin manual payment override workflow.
     */
    public function overridePayment(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. Authorization Check: Super-Admin or explicit override/refund permission
        $isAuthorized = $user->hasRole('super-admin') || $user->hasPermission(EmsPermissions::PAYMENTS_REFUND);
        if (! $isAuthorized) {
            abort(403, 'Unauthorized. Super-admin authorization required for payment override.');
        }

        $validated = $request->validate([
            'registration_id' => 'required|integer|exists:ems_registrations,id',
            'payment_method' => 'required|string|in:cash,cheque,bank_transfer,square_pos,external_square,other',
            'reference_id' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric|min:0',
            'reason' => 'required|string|min:5|max:1000',
        ]);

        /** @var Registration $registration */
        $registration = Registration::with(['event', 'order', 'tickets'])->findOrFail($validated['registration_id']);

        if ($registration->event) {
            $this->authorize('view', $registration->event);
        }

        // Idempotency check: Already confirmed & paid with active tickets
        if ($registration->status === RegistrationStatus::Confirmed && $registration->tickets()->exists()) {
            return ApiResponse::error('Registration is already confirmed and tickets are issued.', [], 409);
        }

        return DB::transaction(function () use ($registration, $validated, $user) {
            $order = $registration->order;
            if (! $order) {
                // Create a fallback order if missing
                $order = Order::create([
                    'uuid' => (string) Str::uuid(),
                    'event_id' => $registration->event_id,
                    'user_id' => $registration->user_id,
                    'buyer_name' => $registration->attendee_name ?? $user->name,
                    'buyer_email' => $registration->attendee_email ?? $user->email,
                    'buyer_phone' => $registration->attendee_phone ?? null,
                    'reference' => 'ORD-OVR-' . strtoupper(Str::random(8)),
                    'status' => 'pending',
                    'total_amount' => $validated['amount'] ?? 0.00,
                    'currency' => config('ems.defaults.currency', 'CAD'),
                ]);
                $registration->order_id = $order->id;
                $registration->save();
            }

            // Find existing payment or create manual override payment record
            $payment = Payment::where('registration_id', $registration->id)
                ->orWhere('order_id', $order->id)
                ->first();

            $originalPaymentStatus = $payment?->status?->value ?? 'none';
            $originalRegistrationStatus = $registration->status->value;

            $providerPaymentId = 'MANUAL_OVR_' . ($validated['reference_id'] ?? (string) Str::uuid());

            if (! $payment) {
                $payment = new Payment();
                $payment->uuid = (string) Str::uuid();
                $payment->order_id = $order->id;
                $payment->registration_id = $registration->id;
                $payment->status = PaymentStatus::Pending;
                $payment->amount = $validated['amount'] ?? $order->total_amount ?? 0.00;
                $payment->currency = config('ems.defaults.currency', 'CAD');
            }

            $payment->provider_payment_id = $providerPaymentId;
            $payment->metadata = array_merge($payment->metadata ?? [], [
                'override_by_user_id' => $user->id,
                'override_by_name' => $user->name,
                'override_reason' => $validated['reason'],
                'override_at' => now()->toIso8601String(),
                'payment_method' => $validated['payment_method'],
                'external_reference' => $validated['reference_id'] ?? null,
            ]);
            $payment->save();

            // Execute fulfillment via PaymentFulfillmentService (confirms registration, issues ticket & QR, dispatches email)
            /** @var PaymentFulfillmentService $fulfillmentSvc */
            $fulfillmentSvc = app(PaymentFulfillmentService::class);
            $fulfilledPayment = $fulfillmentSvc->markPaid($payment, [
                'provider_payment_id' => $providerPaymentId,
                'metadata' => [
                    'override' => true,
                    'payment_method' => $validated['payment_method'],
                ],
            ]);

            // Log immutable activity audit
            app(EmsActivityLogger::class)->log(
                'payment.super_admin_override',
                $fulfilledPayment,
                'Super-admin executed manual payment override.',
                [
                    'registration_id' => $registration->id,
                    'registration_uuid' => $registration->uuid,
                    'event_id' => $registration->event_id,
                    'actor_user_id' => $user->id,
                    'actor_name' => $user->name,
                    'original_payment_status' => $originalPaymentStatus,
                    'resulting_payment_status' => $fulfilledPayment->status->value,
                    'original_registration_status' => $originalRegistrationStatus,
                    'resulting_registration_status' => $registration->fresh()->status->value,
                    'payment_method' => $validated['payment_method'],
                    'reference_id' => $validated['reference_id'] ?? null,
                    'reason' => $validated['reason'],
                    'timestamp' => now()->toIso8601String(),
                ]
            );

            return ApiResponse::success([
                'registration' => $registration->fresh(['tickets', 'event', 'order']),
                'payment' => $fulfilledPayment,
            ], 'Super-admin payment override completed successfully.');
        });
    }

    /**
     * POST /api/v1/ems/admin/payments/reconcile-external
     * Square POS / Terminal or external direct payment reconciliation.
     */
    public function reconcileExternalSquare(Request $request): JsonResponse
    {
        $user = $request->user();

        $isAuthorized = $user->hasRole('super-admin') || $user->hasPermission(EmsPermissions::PAYMENTS_REFUND);
        if (! $isAuthorized) {
            abort(403, 'Unauthorized. Required permission: payments.refund');
        }

        $validated = $request->validate([
            'registration_id' => 'required|integer|exists:ems_registrations,id',
            'external_transaction_id' => 'required|string|max:255',
            'payment_method' => 'nullable|string|in:square_pos,square_terminal,square_direct,cash,cheque',
            'amount' => 'nullable|numeric|min:0',
            'reason' => 'required|string|min:5|max:1000',
        ]);

        /** @var Registration $registration */
        $registration = Registration::with(['event', 'order', 'tickets'])->findOrFail($validated['registration_id']);

        if ($registration->event) {
            $this->authorize('view', $registration->event);
        }

        // Duplicate reconciliation protection
        $existingReconciledPayment = Payment::where('provider_payment_id', $validated['external_transaction_id'])
            ->where('status', PaymentStatus::Paid)
            ->first();

        if ($existingReconciledPayment) {
            return ApiResponse::error('External transaction ID has already been reconciled.', [
                'payment_uuid' => $existingReconciledPayment->uuid,
            ], 409);
        }

        return DB::transaction(function () use ($registration, $validated, $user) {
            $order = $registration->order;
            if (! $order) {
                $order = Order::create([
                    'uuid' => (string) Str::uuid(),
                    'event_id' => $registration->event_id,
                    'user_id' => $registration->user_id,
                    'buyer_name' => $registration->attendee_name ?? $user->name,
                    'buyer_email' => $registration->attendee_email ?? $user->email,
                    'buyer_phone' => $registration->attendee_phone ?? null,
                    'reference' => 'ORD-SQ-' . strtoupper(Str::random(8)),
                    'status' => 'pending',
                    'total_amount' => $validated['amount'] ?? 0.00,
                    'currency' => config('ems.defaults.currency', 'CAD'),
                ]);
                $registration->order_id = $order->id;
                $registration->save();
            }

            $payment = Payment::where('registration_id', $registration->id)
                ->orWhere('order_id', $order->id)
                ->first();

            if (! $payment) {
                $payment = new Payment();
                $payment->uuid = (string) Str::uuid();
                $payment->order_id = $order->id;
                $payment->registration_id = $registration->id;
                $payment->status = PaymentStatus::Pending;
                $payment->amount = $validated['amount'] ?? $order->total_amount ?? 0.00;
                $payment->currency = config('ems.defaults.currency', 'CAD');
            }

            $payment->provider_payment_id = $validated['external_transaction_id'];
            $payment->metadata = array_merge($payment->metadata ?? [], [
                'reconciled_by_user_id' => $user->id,
                'reconciled_reason' => $validated['reason'],
                'external_transaction_id' => $validated['external_transaction_id'],
                'payment_method' => $validated['payment_method'] ?? 'square_pos',
                'reconciled_at' => now()->toIso8601String(),
            ]);
            $payment->save();

            /** @var PaymentFulfillmentService $fulfillmentSvc */
            $fulfillmentSvc = app(PaymentFulfillmentService::class);
            $fulfilledPayment = $fulfillmentSvc->markPaid($payment, [
                'provider_payment_id' => $validated['external_transaction_id'],
            ]);

            app(EmsActivityLogger::class)->log(
                'payment.square_reconciled',
                $fulfilledPayment,
                'External Square POS direct payment reconciled.',
                [
                    'registration_id' => $registration->id,
                    'registration_uuid' => $registration->uuid,
                    'external_transaction_id' => $validated['external_transaction_id'],
                    'actor_user_id' => $user->id,
                    'reason' => $validated['reason'],
                ]
            );

            return ApiResponse::success([
                'registration' => $registration->fresh(['tickets', 'event', 'order']),
                'payment' => $fulfilledPayment,
            ], 'External Square payment reconciled and ticket issued.');
        });
    }
}
