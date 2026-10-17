<?php

namespace App\Ems\Jobs;

use App\Ems\Enums\WebhookEventStatus;
use App\Ems\Models\WebhookEvent;
use App\Ems\Services\SquareWebhookService;
use App\Platform\Enums\AlertSeverity;
use App\Services\Platform\PlatformAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSquareWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 300, 900];

    public function __construct(public readonly int $webhookEventId)
    {
        $this->onQueue((string) config('ems.payments.queue', 'ems-payments'));
    }

    public function handle(SquareWebhookService $webhooks): void
    {
        $event = WebhookEvent::query()->find($this->webhookEventId);
        if ($event === null) {
            return;
        }

        $webhooks->processRecord($event);
    }

    public function failed(?\Throwable $exception): void
    {
        $event = WebhookEvent::query()->find($this->webhookEventId);
        if ($event === null || $event->status === WebhookEventStatus::Processed->value) {
            return;
        }

        $attempts = (int) $event->retry_count;
        $event->status = WebhookEventStatus::Failed->value;
        $event->retry_count = max($attempts, WebhookEvent::maxAttempts());
        $event->failure_reason = mb_substr(
            'Permanently failed after '.$attempts.' attempts: '.($exception ? $exception::class.': '.$exception->getMessage() : 'unknown error'),
            0,
            500
        );
        $event->save();

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->error('ems.webhooks.square.permanently_failed', [
                'event_id' => $event->event_id,
                'event_type' => $event->event_type,
                'attempts' => $attempts,
                'exception' => $exception ? $exception::class : null,
            ]);

        app(PlatformAlertService::class)->createDeduplicatedAlert(
            'ems.webhooks.square.permanently_failed.'.$event->event_id,
            'ems',
            AlertSeverity::CRITICAL,
            'Square webhook permanently failed',
            'A Square webhook could not be processed after all automatic retries. Review the EMS webhook log.',
            ['event_id' => $event->event_id, 'event_type' => $event->event_type],
        );
    }
}
