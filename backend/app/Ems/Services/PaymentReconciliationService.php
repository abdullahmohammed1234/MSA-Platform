<?php

namespace App\Ems\Services;

use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Models\Payment;
use App\Ems\Services\Payments\PaymentProviderManager;
use Illuminate\Support\Facades\Log;

/**
 * Verifies EMS payment records against provider truth and local ticket state.
 */
class PaymentReconciliationService
{
    public function __construct(
        private readonly PaymentProviderManager $providers,
    ) {
    }

    /**
     * @return array{ok: bool, issues: list<string>}
     */
    public function reconcile(Payment $payment): array
    {
        $issues = [];

        $payment->loadMissing(['order.registrations.tickets', 'registration.tickets']);

        $order = $payment->order;
        $registrations = $order?->registrations ?? collect([$payment->registration])->filter();

        if ($order === null) {
            $issues[] = 'Payment has no linked order.';
        }

        if ($payment->status === PaymentStatus::Paid) {
            if ($order !== null && (float) $order->total_amount !== (float) $payment->amount) {
                $issues[] = 'Order total does not match payment amount.';
            }

            if ($order !== null && strtoupper($order->currency) !== strtoupper($payment->currency)) {
                $issues[] = 'Order currency does not match payment currency.';
            }

            foreach ($registrations as $registration) {
                if ($registration === null) {
                    continue;
                }

                if ($registration->status !== RegistrationStatus::Confirmed) {
                    $issues[] = "Registration {$registration->reference} is not confirmed after paid payment.";
                }

                if ($registration->tickets->count() < max(1, (int) $registration->quantity)) {
                    $issues[] = "Registration {$registration->reference} is missing issued tickets.";
                }

                if ((float) $registration->amount_due > 0
                    && (float) $registration->amount_due !== (float) $payment->amount
                    && $registrations->count() === 1
                ) {
                    $issues[] = "Registration {$registration->reference} amount_due does not match payment.";
                }
            }

            if ($payment->provider->isExternal() && $this->providers->enabled()) {
                try {
                    $remote = $this->providers->driver($payment->provider)->retrievePayment($payment);

                    if (($remote['status'] ?? null) !== PaymentStatus::Paid->value) {
                        $issues[] = 'Provider does not report payment as paid.';
                    }

                    if (isset($remote['amount']) && (float) $remote['amount'] !== (float) $payment->amount) {
                        $issues[] = 'Provider amount does not match EMS payment amount.';
                    }

                    if (isset($remote['currency'])
                        && strtoupper((string) $remote['currency']) !== strtoupper($payment->currency)
                    ) {
                        $issues[] = 'Provider currency does not match EMS payment currency.';
                    }
                } catch (\Throwable $e) {
                    $issues[] = 'Provider reconciliation lookup failed: ' . $e->getMessage();
                }
            }

            if (! $payment->provider_payment_id && ! $payment->provider_transaction_id) {
                $issues[] = 'Paid payment is missing provider transaction references.';
            }
        }

        $ok = $issues === [];

        if (! $ok) {
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->error('ems.payments.reconciliation_failed', [
                    'payment_uuid' => $payment->uuid,
                    'order_uuid' => $order?->uuid,
                    'issues' => $issues,
                ]);
        } else {
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.payments.reconciled', [
                    'payment_uuid' => $payment->uuid,
                    'order_uuid' => $order?->uuid,
                ]);
        }

        return ['ok' => $ok, 'issues' => $issues];
    }
}
