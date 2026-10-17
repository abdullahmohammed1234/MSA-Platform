<?php

namespace App\Jobs\Announcements;

use App\Models\AnnouncementDispatch;
use App\Services\Announcements\AnnouncementDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends one bounded slice of a dispatch and re-queues itself while claimable work
 * remains. Duplicate copies of this job are harmless: every row is claimed first.
 */
class SendAnnouncementDeliveriesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $timeout = 50;

    public function __construct(public readonly int $dispatchId)
    {
        $this->afterCommit = true;
    }

    public function handle(AnnouncementDeliveryService $service): void
    {
        $dispatch = AnnouncementDispatch::query()->find($this->dispatchId);
        if ($dispatch === null) {
            return;
        }

        if ($service->sendDue($dispatch)) {
            $service->queueSending($dispatch);
        } elseif ($service->emailThrottled() && ! $this->job instanceof \Illuminate\Queue\Jobs\SyncJob) {
            // The sync driver ignores delays; RecoverAnnouncementDeliveriesJob resumes it instead.
            $service->queueSending($dispatch, now()->addMinutes(max(1, (int) config('announcements.delivery.throttle_requeue_minutes', 10))));
        }
    }
}
