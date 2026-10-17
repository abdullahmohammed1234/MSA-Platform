<?php

namespace App\Ems\Jobs;

use App\Ems\Services\Notifications\ReminderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Unique so a stalled worker does not accumulate one copy per scheduler tick.
 */
class ProcessDueRemindersJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $limit = 50)
    {
        $this->onQueue((string) config('ems.notifications.queue', 'ems-notifications'));
    }

    public function handle(ReminderService $reminders): void
    {
        if (! config('ems.notifications.enabled', false)) {
            return;
        }

        $reminders->processDue($this->limit);
    }
}
