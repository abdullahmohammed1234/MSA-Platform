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
        $this->afterCommit = true;
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
        $notification = \Illuminate\Support\Facades\DB::transaction(function () {
            $locked = EventNotification::query()
                ->whereKey($this->notificationId)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                return null;
            }

            if ($locked->status === NotificationStatus::Sent || $locked->status === NotificationStatus::Cancelled) {
                return null;
            }

            if (blank($locked->recipient_email)) {
                $locked->markFailed('Missing recipient email.', false);
                return null;
            }

            // Concurrency protection: skip if another worker is already processing first attempt
            if ($locked->queue_status === 'processing' && $this->attempts() === 1) {
                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->warning('ems.notifications.concurrent_processing_ignored', [
                        'notification_uuid' => $locked->uuid,
                    ]);
                return null;
            }

            $locked->last_attempt_at = now();
            $locked->queue_status = 'processing';
            $locked->save();

            return $locked;
        });

        if ($notification === null) {
            return;
        }

        try {
            $sentMessage = Mail::to($notification->recipient_email)
                ->send(new EventNotificationMail($notification));

            $messageId = $sentMessage?->getMessageId();
            if ($messageId) {
                $notification->provider_message_id = $messageId;
            }

            $notification->markSent();

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.notifications.sent', [
                    'notification_uuid' => $notification->uuid,
                    'type' => $notification->type,
                    'event_id' => $notification->event_id,
                    'provider_message_id' => $messageId,
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

            app(\App\Ems\Services\Notifications\NotificationFailureAlertService::class)->sendAlert($notification, $e->getMessage());

            throw $e;
        }
    }

    public function failed(?Throwable $e): void
    {
        $notification = EventNotification::query()->find($this->notificationId);

        if ($notification === null || $notification->status === NotificationStatus::Sent) {
            return;
        }

        $errorMsg = $e?->getMessage() ?? 'Permanent delivery failure.';
        $notification->markFailed($errorMsg, false);

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->error('ems.notifications.permanently_failed', [
                'notification_uuid' => $notification->uuid,
                'type' => $notification->type,
            ]);

        app(\App\Ems\Services\Notifications\NotificationFailureAlertService::class)->sendAlert($notification, $errorMsg);
    }
}
