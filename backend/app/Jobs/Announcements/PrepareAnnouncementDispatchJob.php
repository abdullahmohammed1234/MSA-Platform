<?php

namespace App\Jobs\Announcements;

use App\Models\AnnouncementDispatch;
use App\Services\Announcements\AnnouncementDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PrepareAnnouncementDispatchJob implements ShouldQueue
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

        $service->prepare($dispatch);

        if ($dispatch->status === AnnouncementDispatch::STATUS_SENDING) {
            $service->queueSending($dispatch);
        }
    }
}
