<?php

namespace App\Jobs\Announcements;

use App\Models\AnnouncementDelivery;
use App\Models\AnnouncementDispatch;
use App\Services\Announcements\AnnouncementDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Scheduled sweep: resumes dispatches whose preparation or sending was interrupted
 * and picks up retries whose backoff has elapsed.
 */
class RecoverAnnouncementDeliveriesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(AnnouncementDeliveryService $service): void
    {
        AnnouncementDispatch::query()
            ->where('status', AnnouncementDispatch::STATUS_PREPARING)
            ->where('updated_at', '<', now()->subMinutes(5))
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->each(fn (AnnouncementDispatch $dispatch) => $service->queuePreparation($dispatch));

        AnnouncementDispatch::query()
            ->where('status', AnnouncementDispatch::STATUS_SENDING)
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->each(function (AnnouncementDispatch $dispatch) use ($service): void {
                if (AnnouncementDelivery::query()->where('dispatch_id', $dispatch->id)->claimable()->exists()) {
                    $service->queueSending($dispatch);
                } else {
                    $service->refreshStatus($dispatch);
                }
            });
    }
}
