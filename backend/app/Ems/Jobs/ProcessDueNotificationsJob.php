<?php

namespace App\Ems\Jobs;

use App\Ems\Models\EventNotification;
use App\Ems\Services\Notifications\QueuedEventNotificationDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Picks up scheduled/pending ledger rows whose scheduled_at has arrived.
 */
class ProcessDueNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $limit = 100)
    {
        $this->onQueue((string) config('ems.notifications.queue', 'ems-notifications'));
    }

    public function handle(QueuedEventNotificationDispatcher $dispatcher): void
    {
        if (! config('ems.notifications.enabled', false)) {
            return;
        }

        $count = 0;

        EventNotification::query()
            ->due()
            ->orderBy('id')
            ->limit($this->limit)
            ->get()
            ->each(function (EventNotification $notification) use ($dispatcher, &$count): void {
                $dispatcher->queueIfDue($notification);
                $count++;
            });

        if ($count > 0) {
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.notifications.due_processed', ['count' => $count]);
        }
    }
}
