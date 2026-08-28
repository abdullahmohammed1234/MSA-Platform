<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Http\Resources\PaymentResource;
use App\Ems\Models\Payment;
use App\Ems\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Http\Request;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\OrderStatus;
use App\Ems\Services\PaymentFulfillmentService;

class PaymentController extends EmsController
{
    /**
     * GET /api/v1/ems/payments/{payment}
     */
    public function show(Payment $payment): JsonResponse
    {
        $payment->loadMissing(['order.event', 'registration.event']);
        $event = $payment->order?->event ?? $payment->registration?->event;

        if ($event === null) {
            throw new NotFoundHttpException('Payment not found.');
        }

        $this->authorize('view', $event);

        return ApiResponse::success(
            new PaymentResource($payment->loadMissing(['order.event', 'registration', 'squareRefunds'])),
            'Payment retrieved successfully.'
        );
    }

    public function refund(\App\Ems\Http\Requests\RefundPaymentRequest $request, Payment $payment): JsonResponse
    {
        $payment->loadMissing(['order.event', 'registration.event']);
        $event = $payment->order?->event ?? $payment->registration?->event;

        if ($event === null) {
            throw new NotFoundHttpException('Payment not found.');
        }

        $this->authorize('update', $event);

        if (! $request->user()?->hasPermission(\App\Ems\Support\EmsPermissions::PAYMENTS_REFUND)) {
            abort(403, 'Unauthorized. Required permission: payments.refund');
        }

        $refund = app(\App\Ems\Services\Square\SquareRefundService::class)->refund(
            $payment,
            isset($request->validated()['amount']) ? (float) $request->validated()['amount'] : null,
            $request->validated()['reason'] ?? null,
            $request->user()
        );

        return ApiResponse::success(
            [
                'refund' => [
                    'uuid' => $refund->uuid,
                    'status' => $refund->status->value,
                    'status_label' => $refund->status->label(),
                    'amount' => (float) $refund->amount,
                    'currency' => $refund->currency,
                    'provider_refund_id' => $refund->provider_refund_id,
                    'reason' => $refund->reason,
                ],
                'payment' => new PaymentResource($payment->fresh(['order', 'squareRefunds'])),
            ],
            'Refund submitted to Square.'
        );
    }

    /**
     * POST /api/v1/ems/payments/{payment}/fulfill
     */
    public function fulfill(Request $request, Payment $payment): JsonResponse
    {
        $payment->loadMissing(['order.event', 'registration.tickets', 'squareRefunds']);
        $event = $payment->order?->event ?? $payment->registration?->event;

        if ($event === null) {
            return ApiResponse::error('Payment is not linked to a valid event.', [], 404);
        }

        $this->authorize('update', $event);

        if (! $request->user()?->hasPermission(\App\Ems\Support\EmsPermissions::PAYMENTS_REFUND)) {
            abort(403, 'Unauthorized. Required permission: payments.refund');
        }

        $registration = $payment->registration ?? $payment->order?->registrations()->first();
        if ($registration === null) {
            return ApiResponse::error('Payment is not linked to a registration.', [], 400);
        }

        // Idempotency check: already fulfilled
        if ($payment->status === PaymentStatus::Paid
            && $registration->status === RegistrationStatus::Confirmed
            && $registration->tickets()->exists()
        ) {
            return ApiResponse::success(
                new PaymentResource($payment),
                'Payment already fulfilled.'
            );
        }

        // Safety verification:
        // 1. Check if payment is already paid but state is mismatched
        if ($payment->status === PaymentStatus::Paid) {
            return ApiResponse::error('Payment is already marked Paid, but registration or tickets are incomplete.', [], 409);
        }
        
        // 2. Check if registration is already confirmed
        if ($registration->status === RegistrationStatus::Confirmed) {
            return ApiResponse::error('Associated registration is already confirmed.', [], 409);
        }

        // 3. Check for cancellation conflicts
        if ($payment->status === PaymentStatus::Cancelled 
            || $registration->status === RegistrationStatus::Cancelled 
            || ($payment->order && $payment->order->status === OrderStatus::Cancelled)
        ) {
            return ApiResponse::error('Cannot fulfill a cancelled payment, registration, or order.', [], 409);
        }

        // 4. Check for refund conflicts
        if ($payment->status === PaymentStatus::Refunded 
            || $payment->status === PaymentStatus::PartiallyRefunded
            || (float) $payment->amount_refunded > 0
            || $payment->squareRefunds()->exists()
        ) {
            return ApiResponse::error('Cannot fulfill a payment with active or pending refunds.', [], 409);
        }

        // Perform force fulfillment
        $previousPaymentStatus = $payment->status->value;
        $previousRegistrationStatus = $registration->status->value;

        /** @var PaymentFulfillmentService $fulfillmentSvc */
        $fulfillmentSvc = app(PaymentFulfillmentService::class);
        
        $providerData = [
            'provider_payment_id' => $payment->provider_payment_id ?? 'MANUAL_ADMIN_FORCE',
            'provider_transaction_id' => $payment->provider_transaction_id ?? 'MANUAL_ADMIN_TXN',
            'metadata' => [
                'force_fulfilled_by_user_id' => $request->user()->id,
                'force_fulfilled_at' => now()->toIso8601String(),
            ]
        ];

        $fulfilledPayment = $fulfillmentSvc->markPaid($payment, $providerData);

        // Audit log force fulfillment
        app(\App\Ems\Services\EmsActivityLogger::class)->log(
            'payment.force_fulfilled',
            $payment,
            'Payment force-fulfilled by administrator.',
            [
                'payment_uuid' => $payment->uuid,
                'order_uuid' => $payment->order?->uuid,
                'registration_uuid' => $registration->uuid,
                'previous_payment_status' => $previousPaymentStatus,
                'previous_registration_status' => $previousRegistrationStatus,
                'resulting_payment_status' => $fulfilledPayment->status->value,
                'resulting_registration_status' => $registration->fresh()->status->value,
                'tickets_count' => $registration->tickets()->count(),
                'actor_user_id' => $request->user()->id,
            ]
        );

        return ApiResponse::success(
            new PaymentResource($fulfilledPayment),
            'Payment fulfilled successfully.'
        );
    }

    /**
     * POST /api/v1/ems/payments/{payment}/reconcile
     */
    public function reconcile(Request $request, Payment $payment): JsonResponse
    {
        $payment->loadMissing(['order.event', 'registration.tickets', 'squareRefunds']);
        $event = $payment->order?->event ?? $payment->registration?->event;

        if ($event === null) {
            return ApiResponse::error('Payment is not linked to a valid event.', [], 404);
        }

        $this->authorize('view', $event);

        if (! $request->user()?->hasPermission(\App\Ems\Support\EmsPermissions::PAYMENTS_REFUND)) {
            abort(403, 'Unauthorized. Required permission: payments.refund');
        }

        $registration = $payment->registration ?? $payment->order?->registrations()->first();
        if ($registration === null) {
            return ApiResponse::success([
                'payment_uuid' => $payment->uuid,
                'status' => 'inconsistent',
                'issues' => ['Payment has no linked registration.'],
            ]);
        }

        $issues = [];
        $status = 'healthy';

        // Check 1: Refund conflict
        $hasRefund = $payment->status === PaymentStatus::Refunded
            || $payment->status === PaymentStatus::PartiallyRefunded
            || (float) $payment->amount_refunded > 0
            || $payment->squareRefunds()->exists();

        if ($hasRefund && $registration->status !== RegistrationStatus::Cancelled) {
            $issues[] = 'Refund conflict: Payment is refunded or has refund records, but registration is still active (not cancelled).';
            $status = 'inconsistent';
        }

        // Check 2: Payment captured but registration incomplete
        if ($payment->status === PaymentStatus::Paid && $registration->status !== RegistrationStatus::Confirmed) {
            $issues[] = 'Payment captured but registration incomplete: Payment is Paid, but registration is ' . $registration->status->value . '.';
            $status = 'inconsistent';
        }

        // Check 3: Registration confirmed but tickets missing
        if ($registration->status === RegistrationStatus::Confirmed && ! $registration->tickets()->exists()) {
            $issues[] = 'Registration confirmed but tickets missing: Registration is Confirmed, but no tickets are issued.';
            $status = 'inconsistent';
        }

        // Check 4: Currency/Amount mismatches
        if ($payment->status === PaymentStatus::Paid) {
            if ($payment->order && (float) $payment->order->total_amount !== (float) $payment->amount) {
                $issues[] = 'Amount mismatch: Order total (' . $payment->order->total_amount . ') does not match payment amount (' . $payment->amount . ').';
                $status = 'inconsistent';
            }
            if ($payment->order && strtoupper($payment->order->currency) !== strtoupper($payment->currency)) {
                $issues[] = 'Currency mismatch: Order currency (' . $payment->order->currency . ') does not match payment currency (' . $payment->currency . ').';
                $status = 'inconsistent';
            }
        }

        // Check 5: Cancellation mismatch
        if ($payment->status === PaymentStatus::Cancelled && $registration->status !== RegistrationStatus::Cancelled) {
            $issues[] = 'Cancellation mismatch: Payment is Cancelled, but registration is ' . $registration->status->value . '.';
            $status = 'inconsistent';
        }

        return ApiResponse::success([
            'payment_uuid' => $payment->uuid,
            'status' => $status,
            'issues' => $issues,
            'payment_state' => $payment->status->value,
            'registration_state' => $registration->status->value,
            'tickets_issued' => $registration->tickets()->count(),
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'amount_refunded' => (float) $payment->amount_refunded,
        ], 'Payment reconciliation report generated.');
    }
}
