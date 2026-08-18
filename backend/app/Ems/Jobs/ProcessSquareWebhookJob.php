<?php

namespace App\Ems\Jobs;

use App\Ems\Models\WebhookEvent;
use App\Ems\Services\SquareWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSquareWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

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
}
