<?php

namespace App\Ems\Services\Square;

use App\Ems\Enums\WebhookEventStatus;
use App\Ems\Models\Payment;
use App\Ems\Models\SquareCatalogMapping;
use App\Ems\Models\SquareSyncCursor;
use App\Ems\Models\WebhookEvent;
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
     *     refunds_errors: int
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
        ];

        if (! $this->square->enabled()) {
            return $result;
        }

        $this->catalog->pullRemoteChanges();
        $this->retryUnmatchedWebhooks();

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
        if (! in_array($status, ['COMPLETED', 'APPROVED', 'PAID'], true)) {
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
            ->whereIn('status', [
                WebhookEventStatus::Unmatched->value,
                WebhookEventStatus::RetryPending->value,
                WebhookEventStatus::Failed->value,
            ])
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
                        'error' => $e->getMessage(),
                    ]);
            }
        }
    }
}
