<?php

namespace App\Ems\Jobs;

use App\Ems\Enums\NotificationStatus;
use App\Ems\Mail\EventNotificationMail;
use App\Ems\Models\EventNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Delivers a single EMS notification via the platform mail stack.
 */
class SendEventNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $notificationId)
    {
        $this->onQueue((string) config('ems.notifications.queue', 'ems-notifications'));
    }

    public function handle(): void
    {
        if (! config('ems.notifications.enabled', false)) {
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.notifications.send_skipped_disabled', [
                    'notification_id' => $this->notificationId,
                ]);

            return;
        }

        /** @var EventNotification|null $notification */
        $notification = EventNotification::query()->find($this->notificationId);

        if ($notification === null) {
            return;
        }

        if ($notification->status === NotificationStatus::Sent) {
            return;
        }

        if ($notification->status === NotificationStatus::Cancelled) {
            return;
        }

        if (blank($notification->recipient_email)) {
            $notification->markFailed('Missing recipient email.', false);

            return;
        }

        $notification->last_attempt_at = now();
        $notification->queue_status = 'processing';
        $notification->save();

        try {
            Mail::to($notification->recipient_email)
                ->send(new EventNotificationMail($notification));

            $notification->markSent();

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.notifications.sent', [
                    'notification_uuid' => $notification->uuid,
                    'type' => $notification->type,
                    'event_id' => $notification->event_id,
                ]);
        } catch (Throwable $e) {
            $notification->markFailed($e->getMessage());

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->warning('ems.notifications.send_failed', [
                    'notification_uuid' => $notification->uuid,
                    'type' => $notification->type,
                    'retry_count' => $notification->retry_count,
                    'attempt' => $this->attempts(),
                ]);

            throw $e;
        }
    }

    public function failed(?Throwable $e): void
    {
        $notification = EventNotification::query()->find($this->notificationId);

        if ($notification === null || $notification->status === NotificationStatus::Sent) {
            return;
        }

        $notification->markFailed($e?->getMessage() ?? 'Permanent delivery failure.', false);

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->error('ems.notifications.permanently_failed', [
                'notification_uuid' => $notification->uuid,
                'type' => $notification->type,
            ]);
    }
}
