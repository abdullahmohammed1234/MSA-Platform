<?php

namespace App\Ems\Services\Square;

use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\WebhookEventStatus;
use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Models\SquareCatalogMapping;
use App\Ems\Models\SquareSyncCursor;
use App\Ems\Models\WebhookEvent;
use App\Ems\Services\CheckoutService;
use App\Ems\Services\PaymentFulfillmentService;
use Illuminate\Support\Facades\Log;

/**
 * Idempotent import of Square payments and refunds that EMS should own.
 *
 * Webhooks remain the near-real-time path. Reconciliation is an independent
 * recovery mechanism: it lists Square payments and refunds and applies them
 * without duplicating tickets or refund rows.
 */
class SquareReconciliationService
{
    public function __construct(
        private readonly SquareClient $square,
        private readonly SquarePosIngestService $pos,
        private readonly SquareCatalogService $catalog,
        private readonly SquareRefundService $refunds,
    ) {
    }

    /**
     * @return array{
     *     ingested: int,
     *     unmatched: int,
     *     skipped: int,
     *     errors: int,
     *     refunds_applied: int,
     *     refunds_skipped: int,
     *     refunds_errors: int,
     *     ambiguous_examined: int,
     *     ambiguous_reconciled_paid: int,
     *     ambiguous_inventory_released: int,
     *     ambiguous_uncertain_retained: int
     * }
     */
    public function reconcile(?string $since = null): array
    {
        $result = [
            'ingested' => 0,
            'unmatched' => 0,
            'skipped' => 0,
            'errors' => 0,
            'refunds_applied' => 0,
            'refunds_skipped' => 0,
            'refunds_errors' => 0,
            'ambiguous_examined' => 0,
            'ambiguous_reconciled_paid' => 0,
            'ambiguous_inventory_released' => 0,
            'ambiguous_uncertain_retained' => 0,
        ];

        if (! $this->square->enabled()) {
            return $result;
        }

        $this->catalog->pullRemoteChanges();
        $this->retryUnmatchedWebhooks();

        $ambiguousResult = $this->reconcileAmbiguousPayments();
        $result['ambiguous_examined'] = $ambiguousResult['examined'];
        $result['ambiguous_reconciled_paid'] = $ambiguousResult['reconciled_paid'];
        $result['ambiguous_inventory_released'] = $ambiguousResult['inventory_released'];
        $result['ambiguous_uncertain_retained'] = $ambiguousResult['uncertain_retained'];

        $begin = $since ?: SquareSyncCursor::getValue('payments_reconcile') ?: now()->subDay()->toRfc3339String();
        $cursor = null;
        $latest = $begin;

        do {
            $query = [
                'location_id' => $this->square->locationId(),
                'begin_time' => $begin,
                'limit' => 100,
            ];
            if ($cursor) {
                $query['cursor'] = $cursor;
            }

            try {
                $response = $this->square->get('/v2/payments', $query);
            } catch (\Throwable $e) {
                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->error('ems.square.reconcile.list_failed', ['error' => $e->getMessage()]);
                $result['errors']++;
                break;
            }

            foreach ($response['payments'] ?? [] as $payment) {
                if (! is_array($payment)) {
                    continue;
                }
                $created = (string) ($payment['created_at'] ?? '');
                if ($created !== '' && $created > $latest) {
                    $latest = $created;
                }

                $outcome = $this->reconcilePayment($payment);
                $result[$outcome]++;
            }

            $cursor = $response['cursor'] ?? null;
        } while (is_string($cursor) && $cursor !== '');

        SquareSyncCursor::putValue('payments_reconcile', $latest, ['last_run_at' => now()->toIso8601String()]);

        $refundResult = $this->reconcileRefunds($since);
        $result['refunds_applied'] = $refundResult['applied'];
        $result['refunds_skipped'] = $refundResult['skipped'];
        $result['refunds_errors'] = $refundResult['errors'];

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.square.reconcile.completed', $result);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $squarePayment
     */
    private function reconcilePayment(array $squarePayment): string
    {
        $id = (string) ($squarePayment['id'] ?? '');
        if ($id === '') {
            return 'skipped';
        }

        if (Payment::query()->where('provider_payment_id', $id)->exists()) {
            return 'skipped';
        }

        $status = strtoupper((string) ($squarePayment['status'] ?? ''));
        if (! in_array($status, ['COMPLETED', 'PAID'], true)) {
            return 'skipped';
        }

        try {
            $ingested = $this->pos->ingestPayment($squarePayment);
            if ($ingested !== null) {
                return 'ingested';
            }
        } catch (\Throwable $e) {
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->error('ems.square.reconcile.ingest_failed', [
                    'square_payment_id' => $id,
                    'error' => $e->getMessage(),
                ]);

            return 'errors';
        }

        if (! $this->hasMappedLineItems($squarePayment)) {
            return 'skipped';
        }

        return 'unmatched';
    }

    /**
     * List Square PaymentRefunds (GET /v2/refunds) and apply those whose
     * payment_id maps to an EMS payment. Unmatched Square payment IDs are
     * skipped — never attached to an unrelated EMS payment.
     *
     * @return array{applied: int, skipped: int, errors: int}
     */
    private function reconcileRefunds(?string $since): array
    {
        $result = ['applied' => 0, 'skipped' => 0, 'errors' => 0];
        $begin = $since ?: SquareSyncCursor::getValue('refunds_reconcile') ?: now()->subDay()->toRfc3339String();
        $cursor = null;
        $latest = $begin;

        do {
            $query = [
                'location_id' => $this->square->locationId(),
                'begin_time' => $begin,
                'limit' => 100,
            ];
            if ($cursor) {
                $query['cursor'] = $cursor;
            }

            try {
                $response = $this->square->get('/v2/refunds', $query);
            } catch (\Throwable $e) {
                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->error('ems.square.reconcile.refunds_list_failed', ['error' => $e->getMessage()]);
                $result['errors']++;
                break;
            }

            foreach ($response['refunds'] ?? [] as $squareRefund) {
                if (! is_array($squareRefund)) {
                    continue;
                }

                $created = (string) ($squareRefund['created_at'] ?? '');
                if ($created !== '' && $created > $latest) {
                    $latest = $created;
                }

                $outcome = $this->reconcileRefund($squareRefund);
                $result[$outcome]++;
            }

            $cursor = $response['cursor'] ?? null;
        } while (is_string($cursor) && $cursor !== '');

        SquareSyncCursor::putValue('refunds_reconcile', $latest, ['last_run_at' => now()->toIso8601String()]);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $squareRefund
     */
    private function reconcileRefund(array $squareRefund): string
    {
        $refundId = (string) ($squareRefund['id'] ?? '');
        $paymentId = (string) ($squareRefund['payment_id'] ?? '');

        if ($refundId === '' || $paymentId === '') {
            return 'skipped';
        }

        if (! Payment::query()->where('provider_payment_id', $paymentId)->exists()) {
            return 'skipped';
        }

        try {
            $applied = $this->refunds->applyFromWebhook($squareRefund);
            if ($applied !== null) {
                return 'applied';
            }
        } catch (\Throwable $e) {
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->error('ems.square.reconcile.refund_failed', [
                    'square_refund_id' => $refundId,
                    'square_payment_id' => $paymentId,
                    'error' => $e->getMessage(),
                ]);

            return 'errors';
        }

        return 'skipped';
    }

    /**
     * @param  array<string, mixed>  $squarePayment
     */
    private function hasMappedLineItems(array $squarePayment): bool
    {
        $orderId = (string) ($squarePayment['order_id'] ?? '');
        if ($orderId === '') {
            return SquareCatalogMapping::query()->whereNotNull('square_catalog_variation_id')->exists();
        }

        try {
            $response = $this->square->get('/v2/orders/' . urlencode($orderId));
            $items = $response['order']['line_items'] ?? [];
            foreach ($items as $line) {
                $variationId = (string) ($line['catalog_object_id'] ?? '');
                if ($variationId !== '' && $this->catalog->findByVariationId($variationId)) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    private function retryUnmatchedWebhooks(): void
    {
        $events = WebhookEvent::query()
            ->where('provider', 'square')
            ->where(function ($query): void {
                $query->where(fn ($unmatched) => $unmatched->unmatchedDue())
                    ->orWhere(function ($bounded): void {
                        $bounded->whereIn('status', [
                            WebhookEventStatus::RetryPending->value,
                            WebhookEventStatus::Failed->value,
                        ])->where('retry_count', '<', WebhookEvent::maxAttempts());
                    });
            })
            ->orderBy('last_attempt_at')
            ->orderBy('id')
            ->limit(50)
            ->get();

        foreach ($events as $event) {
            try {
                app(\App\Ems\Services\SquareWebhookService::class)->processRecord($event);
            } catch (\Throwable $e) {
                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->warning('ems.square.reconcile.webhook_retry_failed', [
                        'event_id' => $event->event_id,
                        'exception' => $e::class,
                    ]);
            }
        }
    }

    /**
     * Reconcile pending or processing payments with ambiguous outcomes.
     *
     * @return array{
     *     examined: int,
     *     reconciled_paid: int,
     *     inventory_released: int,
     *     uncertain_retained: int
     * }
     */
    public function reconcileAmbiguousPayments(?int $olderThanMinutes = 5): array
    {
        $outcomes = [
            'examined' => 0,
            'reconciled_paid' => 0,
            'inventory_released' => 0,
            'uncertain_retained' => 0,
        ];

        $query = Payment::query()
            ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Processing->value, PaymentStatus::Authorized->value]);

        if ($olderThanMinutes !== null && (int) $olderThanMinutes > 0) {
            $query->where('created_at', '<=', now()->subMinutes((int) $olderThanMinutes));
        }

        $payments = $query
            ->with(['order.registrations.ticketType', 'registration.ticketType'])
            ->get()
            ->filter(function (Payment $p) {
                $meta = $p->metadata ?? [];

                return ! empty($meta['provider_creation_ambiguous'])
                    || ! empty($meta['terminal_creation_ambiguous'])
                    || ! empty($p->terminal_checkout_id)
                    || ! empty($p->provider_checkout_id)
                    || ! empty($p->provider_payment_id)
                    || $p->status === PaymentStatus::Authorized;
            });

        foreach ($payments as $payment) {
            $outcomes['examined']++;

            // 1. Reconcile Terminal payments
            if (! empty($payment->terminal_checkout_id)) {
                try {
                    $retrieved = $this->square->get('/v2/terminals/checkouts/' . urlencode($payment->terminal_checkout_id));
                    $checkout = is_array($retrieved['checkout'] ?? null) ? $retrieved['checkout'] : null;

                    if ($checkout) {
                        $termStatus = strtoupper((string) ($checkout['status'] ?? ''));

                        if ($termStatus === 'COMPLETED') {
                            $fulfilled = app(SquareTerminalService::class)->handleCheckoutUpdated($checkout);
                            if ($fulfilled && $fulfilled->status === PaymentStatus::Paid) {
                                $outcomes['reconciled_paid']++;
                                $this->recordReconciliationAudit($fulfilled, 'reconciled_paid', 'Terminal checkout confirmed COMPLETED by Square API.');
                                continue;
                            }
                            if ($fulfilled && $fulfilled->status === PaymentStatus::Authorized) {
                                $outcomes['uncertain_retained']++;
                                $this->recordReconciliationAudit($fulfilled, 'uncertain_retained', 'Terminal payment is APPROVED but not COMPLETED. Retaining reservation.');
                                continue;
                            }
                        } elseif (in_array($termStatus, ['CANCELED', 'CANCEL_REQUESTED'], true)) {
                            $this->releaseNonPayment($payment, "Terminal checkout {$payment->terminal_checkout_id} confirmed CANCELED on Square.");
                            $outcomes['inventory_released']++;
                            $this->recordReconciliationAudit($payment, 'released_cancelled', 'Terminal checkout confirmed CANCELED on Square.');
                            continue;
                        } else {
                            // Status is still PENDING on terminal: outcome remains uncertain
                            $outcomes['uncertain_retained']++;
                            $this->recordReconciliationAudit($payment, 'uncertain_retained', 'Terminal checkout is still PENDING on Square.');
                            continue;
                        }
                    }
                } catch (\Throwable $e) {
                    $outcomes['uncertain_retained']++;
                    $this->recordReconciliationAudit($payment, 'uncertain_retained', 'Square Terminal API lookup failed: ' . $e->getMessage());
                    continue;
                }
            }

            // 2. Reconcile Online checkouts
            if (! empty($payment->provider_order_id)) {
                try {
                    $retrieved = $this->square->get('/v2/orders/' . urlencode($payment->provider_order_id));
                    $orderObj = is_array($retrieved['order'] ?? null) ? $retrieved['order'] : null;

                    if ($orderObj) {
                        $sqOrderState = strtoupper((string) ($orderObj['state'] ?? ''));
                        $tenders = $orderObj['tenders'] ?? [];
                        $squarePaymentId = null;

                        foreach ($tenders as $tender) {
                            if (! empty($tender['payment_id'])) {
                                $squarePaymentId = (string) $tender['payment_id'];
                                break;
                            }
                        }

                        if ($squarePaymentId !== null && ($sqOrderState === 'COMPLETED' || $sqOrderState === 'OPEN')) {
                            // Verify the underlying Square payment status
                            $sqPayStatus = null;
                            try {
                                $payResponse = $this->square->get('/v2/payments/' . urlencode($squarePaymentId));
                                $sqPayObj = is_array($payResponse['payment'] ?? null) ? $payResponse['payment'] : null;
                                $sqPayStatus = strtoupper((string) ($sqPayObj['status'] ?? ''));
                            } catch (\Throwable) {
                                // If payment lookup fails, cannot establish COMPLETED
                            }

                            if ($sqPayStatus === 'COMPLETED') {
                                app(PaymentFulfillmentService::class)->markPaid($payment, [
                                    'provider_payment_id' => $squarePaymentId,
                                    'provider_order_id' => $payment->provider_order_id,
                                    'metadata' => ['reconciled_via' => 'square_order_lookup'],
                                ]);
                                $outcomes['reconciled_paid']++;
                                $this->recordReconciliationAudit($payment, 'reconciled_paid', 'Online checkout paid tender confirmed COMPLETED by Square API.');
                                continue;
                            }

                            if ($sqPayStatus === 'APPROVED') {
                                if ($payment->status !== PaymentStatus::Paid) {
                                    $payment->status = PaymentStatus::Authorized;
                                    $payment->provider_payment_id = $squarePaymentId;
                                    $payment->metadata = array_merge($payment->metadata ?? [], [
                                        'square_payment_status' => 'APPROVED',
                                        'reconciled_via' => 'square_order_lookup',
                                    ]);
                                    $payment->save();
                                }
                                $outcomes['uncertain_retained']++;
                                $this->recordReconciliationAudit($payment, 'uncertain_retained', 'Square order payment tender is APPROVED but not COMPLETED. Retaining reservation.');
                                continue;
                            }

                            if (in_array($sqPayStatus, ['FAILED', 'CANCELED', 'CANCELLED'], true)) {
                                $this->releaseNonPayment($payment, "Square payment {$squarePaymentId} is {$sqPayStatus}.");
                                $outcomes['inventory_released']++;
                                $this->recordReconciliationAudit($payment, 'released_cancelled', "Square payment is {$sqPayStatus}.");
                                continue;
                            }
                        }

                        if ($sqOrderState === 'CANCELED') {
                            $this->releaseNonPayment($payment, "Square order {$payment->provider_order_id} is CANCELED.");
                            $outcomes['inventory_released']++;
                            $this->recordReconciliationAudit($payment, 'released_cancelled', 'Square order confirmed CANCELED.');
                            continue;
                        }

                        // Check checkout expiration policy
                        $expiresAt = $payment->checkout_expires_at;
                        if ($expiresAt && $expiresAt->isPast()) {
                            $this->releaseNonPayment($payment, "Checkout expired at {$expiresAt->toIso8601String()} with no payment received on Square.");
                            $outcomes['inventory_released']++;
                            $this->recordReconciliationAudit($payment, 'released_expired', 'Checkout TTL expired with no payment tenders.');
                            continue;
                        }

                        $outcomes['uncertain_retained']++;
                        $this->recordReconciliationAudit($payment, 'uncertain_retained', 'Square order has no completed payments and has not expired.');
                        continue;
                    }
                } catch (\Throwable $e) {
                    $outcomes['uncertain_retained']++;
                    $this->recordReconciliationAudit($payment, 'uncertain_retained', 'Square order lookup failed: ' . $e->getMessage());
                    continue;
                }
            }

            // 2.5 Reconcile by direct Square payment ID (e.g. Authorized payments awaiting capture)
            if (! empty($payment->provider_payment_id)) {
                try {
                    $payResponse = $this->square->get('/v2/payments/' . urlencode($payment->provider_payment_id));
                    $sqPayObj = is_array($payResponse['payment'] ?? null) ? $payResponse['payment'] : null;

                    if ($sqPayObj) {
                        $sqPayStatus = strtoupper((string) ($sqPayObj['status'] ?? ''));

                        if ($sqPayStatus === 'COMPLETED') {
                            app(PaymentFulfillmentService::class)->markPaid($payment, [
                                'provider_payment_id' => (string) $sqPayObj['id'],
                                'provider_order_id' => $sqPayObj['order_id'] ?? $payment->provider_order_id,
                                'metadata' => ['reconciled_via' => 'direct_payment_lookup'],
                            ]);
                            $outcomes['reconciled_paid']++;
                            $this->recordReconciliationAudit($payment, 'reconciled_paid', 'Authorized payment confirmed COMPLETED by Square API.');
                            continue;
                        }

                        if ($sqPayStatus === 'APPROVED') {
                            $reviewThresholdHours = (int) config('ems.payments.authorized_review_threshold_hours', 24);
                            $authorizedCutoff = now()->subHours($reviewThresholdHours);
                            $note = 'Payment is APPROVED but not COMPLETED on Square. Retaining reservation.';
                            if ($payment->created_at && $payment->created_at->lessThanOrEqualTo($authorizedCutoff)) {
                                $note = "Payment has remained APPROVED for over {$reviewThresholdHours} hours without capture confirmation.";
                            }
                            $outcomes['uncertain_retained']++;
                            $this->recordReconciliationAudit($payment, 'uncertain_retained', $note);
                            continue;
                        }

                        if (in_array($sqPayStatus, ['FAILED', 'CANCELED', 'CANCELLED'], true)) {
                            $this->releaseNonPayment($payment, "Square payment {$payment->provider_payment_id} is {$sqPayStatus}.");
                            $outcomes['inventory_released']++;
                            $this->recordReconciliationAudit($payment, 'released_cancelled', "Square payment confirmed {$sqPayStatus}.");
                            continue;
                        }
                    }
                } catch (\Throwable $e) {
                    // Fallthrough to reference search or uncertain retained
                }
            }

            // 3. For creation-ambiguous payments without provider order ID, search payments by order reference
            $order = $payment->order;
            if ($order && $order->reference) {
                try {
                    $searchResponse = $this->square->get('/v2/payments', [
                        'location_id' => $this->square->locationId(),
                        'begin_time' => $payment->created_at?->subHour()->toRfc3339String() ?? now()->subDay()->toRfc3339String(),
                        'limit' => 50,
                    ]);

                    $matchingPayment = null;
                    foreach ($searchResponse['payments'] ?? [] as $sqPay) {
                        if (is_array($sqPay) && ($sqPay['reference_id'] ?? '') === $order->reference) {
                            $matchingPayment = $sqPay;
                            break;
                        }
                    }

                    if ($matchingPayment) {
                        $matchStatus = strtoupper((string) ($matchingPayment['status'] ?? ''));

                        if ($matchStatus === 'COMPLETED') {
                            app(PaymentFulfillmentService::class)->markPaid($payment, [
                                'provider_payment_id' => (string) $matchingPayment['id'],
                                'provider_order_id' => $matchingPayment['order_id'] ?? $payment->provider_order_id,
                                'metadata' => ['reconciled_via' => 'square_payment_reference_search'],
                            ]);
                            $outcomes['reconciled_paid']++;
                            $this->recordReconciliationAudit($payment, 'reconciled_paid', 'Found matching completed payment by order reference.');
                            continue;
                        }

                        if ($matchStatus === 'APPROVED') {
                            if ($payment->status !== PaymentStatus::Paid) {
                                $payment->status = PaymentStatus::Authorized;
                                $payment->provider_payment_id = (string) $matchingPayment['id'];
                                $payment->metadata = array_merge($payment->metadata ?? [], [
                                    'square_payment_status' => 'APPROVED',
                                    'reconciled_via' => 'square_payment_reference_search',
                                ]);
                                $payment->save();
                            }
                            $outcomes['uncertain_retained']++;
                            $this->recordReconciliationAudit($payment, 'uncertain_retained', 'Matching payment is APPROVED but not COMPLETED. Retaining reservation.');
                            continue;
                        }

                        if (in_array($matchStatus, ['FAILED', 'CANCELED', 'CANCELLED'], true)) {
                            $this->releaseNonPayment($payment, "Matching Square payment is {$matchStatus}.");
                            $outcomes['inventory_released']++;
                            $this->recordReconciliationAudit($payment, 'released_cancelled', "Matching Square payment confirmed {$matchStatus}.");
                            continue;
                        }
                    }
                } catch (\Throwable $e) {
                    // Fallthrough to uncertain retained
                }
            }

            // Still unresolved: Retain reservation and mark for administrative visibility
            $outcomes['uncertain_retained']++;
            $this->recordReconciliationAudit($payment, 'uncertain_retained', 'Ambiguous outcome could not be resolved automatically; reservation safely retained.');
        }

        return $outcomes;
    }

    private function releaseNonPayment(Payment $payment, string $reason): void
    {
        $order = $payment->order;
        $registration = $payment->registration;

        if ($order && $registration) {
            app(CheckoutService::class)->releaseReservationIdempotent(
                $order,
                $payment,
                $registration,
                $reason
            );

            $payment->refresh();
        }
    }

    private function recordReconciliationAudit(Payment $payment, string $status, string $note): void
    {
        $payment->refresh();
        $meta = $payment->metadata ?? [];
        $meta['reconciliation_status'] = $status;
        $meta['reconciliation_note'] = $note;
        $meta['reconciled_at'] = now()->toIso8601String();
        $meta['reconciliation_attempts'] = (int) ($meta['reconciliation_attempts'] ?? 0) + 1;

        if ($status === 'uncertain_retained') {
            $meta['reconciliation_review_required'] = true;
        } else {
            unset($meta['reconciliation_review_required']);
        }

        $payment->metadata = $meta;
        $payment->save();

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.square.reconcile.ambiguous_outcome', [
                'payment_uuid' => $payment->uuid,
                'status' => $status,
                'note' => $note,
            ]);
    }

    /**
     * Retrieve unresolved ambiguous payments for administrative dashboards and review.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Payment>
     */
    public function getUnresolvedAmbiguousPayments(): \Illuminate\Database\Eloquent\Collection
    {
        $reviewThresholdHours = (int) config('ems.payments.authorized_review_threshold_hours', 24);
        $authorizedCutoff = now()->subHours($reviewThresholdHours);

        return Payment::query()
            ->whereIn('status', [
                PaymentStatus::Pending->value,
                PaymentStatus::Processing->value,
                PaymentStatus::Authorized->value,
            ])
            ->where(function ($q) use ($authorizedCutoff) {
                $q->where('metadata', 'like', '%"reconciliation_review_required":true%')
                  ->orWhere('metadata', 'like', '%"provider_creation_ambiguous":true%')
                  ->orWhere('metadata', 'like', '%"terminal_creation_ambiguous":true%')
                  ->orWhere(function ($sub) use ($authorizedCutoff) {
                      $sub->where('status', PaymentStatus::Authorized->value)
                          ->where('created_at', '<=', $authorizedCutoff);
                  });
            })
            ->with(['order.registrations', 'registration.ticketType'])
            ->get();
    }
}
