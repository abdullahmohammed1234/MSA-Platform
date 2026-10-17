<?php

namespace App\Ems\Jobs;

use App\Ems\Enums\WebhookEventStatus;
use App\Ems\Models\WebhookEvent;
use App\Platform\Enums\AlertSeverity;
use App\Services\Platform\PlatformAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Re-dispatches Square webhook rows that crashed mid-processing, were stored
 * but never processed, failed with automatic attempts remaining, or are unmatched
 * with their backoff elapsed. ProcessSquareWebhookJob claims each row, so
 * overlapping dispatches are safe.
 */
class RecoverSquareWebhookEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue((string) config('ems.payments.queue', 'ems-payments'));
    }

    public function handle(): void
    {
        $this->parkAmbiguousExhausted();

        $ids = WebhookEvent::query()
            ->where('provider', 'square')
            ->where(function ($query): void {
                $query->where(fn ($recovery) => $recovery->awaitingRecovery())
                    ->orWhere(fn ($unmatched) => $unmatched->unmatchedDue());
            })
            ->orderBy('id')
            ->limit(100)
            ->pluck('id');

        foreach ($ids as $id) {
            ProcessSquareWebhookJob::dispatch((int) $id);
        }

        if ($ids->isNotEmpty()) {
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->warning('ems.webhooks.square.recovery_dispatched', ['count' => $ids->count()]);
        }
    }

    /**
     * A row still "processing" after its lease with no attempts left died mid-flight
     * on its final attempt; its side effects may or may not have committed.
     */
    private function parkAmbiguousExhausted(): void
    {
        $rows = WebhookEvent::query()
            ->where('provider', 'square')
            ->where('status', WebhookEventStatus::Processing->value)
            ->where('retry_count', '>=', WebhookEvent::maxAttempts())
            ->where(function ($lease): void {
                $lease->whereNull('last_attempt_at')
                    ->orWhere('last_attempt_at', '<', now()->subSeconds(WebhookEvent::processingLeaseSeconds()));
            })
            ->limit(100)
            ->get();

        foreach ($rows as $row) {
            $parked = WebhookEvent::query()
                ->whereKey($row->id)
                ->where('status', WebhookEventStatus::Processing->value)
                ->where('last_attempt_at', $row->last_attempt_at)
                ->update([
                    'status' => WebhookEventStatus::NeedsReview->value,
                    'failure_reason' => 'Processing interrupted on the final automatic attempt; outcome unknown. Verify against Square before requeueing.',
                    'updated_at' => now(),
                ]) === 1;

            if (! $parked) {
                continue;
            }

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->error('ems.webhooks.square.ambiguous_needs_review', [
                    'event_id' => $row->event_id,
                    'event_type' => $row->event_type,
                    'attempts' => (int) $row->retry_count,
                ]);
            app(PlatformAlertService::class)->createDeduplicatedAlert(
                'ems.webhooks.square.needs_review.'.$row->event_id,
                'ems',
                AlertSeverity::CRITICAL,
                'Square webhook outcome unknown',
                'A Square webhook was interrupted on its final automatic attempt. Verify the payment state in Square before requeueing it.',
                ['event_id' => $row->event_id, 'event_type' => $row->event_type],
            );
        }
    }
}
