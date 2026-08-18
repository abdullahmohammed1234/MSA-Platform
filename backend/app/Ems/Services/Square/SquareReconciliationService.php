<?php

namespace App\Ems\Services\Square;

use App\Ems\Enums\WebhookEventStatus;
use App\Ems\Models\Payment;
use App\Ems\Models\SquareCatalogMapping;
use App\Ems\Models\SquareSyncCursor;
use App\Ems\Models\WebhookEvent;
use Illuminate\Support\Facades\Log;

/**
 * Idempotent import of Square payments that EMS should own.
 *
 * Only payments whose catalog variations map to EMS ticket types are ingested.
 */
class SquareReconciliationService
{
    public function __construct(
        private readonly SquareClient $square,
        private readonly SquarePosIngestService $pos,
        private readonly SquareCatalogService $catalog,
    ) {
    }

    /**
     * @return array{ingested: int, unmatched: int, skipped: int, errors: int}
     */
    public function reconcile(?string $since = null): array
    {
        $result = ['ingested' => 0, 'unmatched' => 0, 'skipped' => 0, 'errors' => 0];

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
