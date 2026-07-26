<?php

namespace App\Ems\Services\Notifications;

use App\Ems\Contracts\EventNotificationDispatcher;
use App\Ems\Enums\NotificationAudience;
use App\Ems\Enums\NotificationChannel;
use App\Ems\Enums\NotificationStatus;
use App\Ems\Enums\NotificationType;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Jobs\SendEventNotificationJob;
use App\Ems\Models\Event;
use App\Ems\Models\EventNotification;
use App\Ems\Models\Registration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Creates ledger rows and queues delivery through Platform Queues.
 *
 * Never sends mail synchronously — callers always return before delivery.
 */
class QueuedEventNotificationDispatcher implements EventNotificationDispatcher
{
    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly PreferenceResolver $preferences,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function notifyRegistration(
        Registration $registration,
        string $type,
        array $payload = [],
        ?Carbon $scheduledAt = null
    ): EventNotification {
        $notificationType = NotificationType::tryFrom($type) ?? NotificationType::EventUpdated;
        $force = (bool) ($payload['force'] ?? false);
        $skipPreference = (bool) ($payload['skip_preference_check'] ?? false);

        $registration->loadMissing(['event.organizer', 'ticketType', 'tickets', 'order', 'settledPayment', 'user']);

        if (! $skipPreference && ! $this->preferences->allows($notificationType, $registration)) {
            return $this->cancelledStub($registration, $notificationType, 'Suppressed by notification preferences.');
        }

        $idempotency = $payload['idempotency_key']
            ?? sprintf('%s:%s:%s', $notificationType->value, $registration->id, $payload['idempotency_suffix'] ?? 'default');

        if (! $force) {
            $existing = EventNotification::query()
                ->where('idempotency_key', $idempotency)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        } else {
            $idempotency .= ':resend:' . now()->timestamp . ':' . uniqid();
        }

        $templateKey = (string) ($payload['template_key'] ?? $notificationType->value);
        $rendered = $this->renderer->render($templateKey, $payload, $registration);

        $status = $scheduledAt !== null && $scheduledAt->isFuture()
            ? NotificationStatus::Scheduled
            : NotificationStatus::Pending;

        $notification = new EventNotification();
        $notification->event_id = $registration->event_id;
        $notification->registration_id = $registration->id;
        $notification->order_id = $registration->order_id;
        $notification->payment_id = $payload['payment_id'] ?? $registration->settledPayment?->id;
        $notification->ticket_id = $payload['ticket_id'] ?? $registration->tickets->first()?->id;
        $notification->user_id = $registration->user_id;
        $notification->recipient_email = $registration->attendee_email;
        $notification->channel = NotificationChannel::Mail;
        $notification->type = $notificationType->value;
        $notification->template_key = $rendered['template_key'];
        $notification->idempotency_key = $idempotency;
        $notification->subject = $rendered['subject'];
        $notification->body = $rendered['body_text'];
        $notification->status = $status;
        $notification->scheduled_at = $scheduledAt ?? now();
        $notification->payload = array_merge($payload, [
            'body_html' => $rendered['body_html'],
            'body_text' => $rendered['body_text'],
            'ticket_codes' => $registration->tickets->pluck('code')->values()->all(),
        ]);
        $notification->save();

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.notifications.created', [
                'notification_uuid' => $notification->uuid,
                'type' => $notification->type,
                'event_id' => $notification->event_id,
                'registration_id' => $registration->id,
            ]);

        $this->queueIfDue($notification);

        return $notification;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function broadcastToEvent(Event $event, string $type, array $payload = []): int
    {
        $audience = NotificationAudience::tryFrom((string) ($payload['audience'] ?? NotificationAudience::Registered->value))
            ?? NotificationAudience::Registered;

        if ($audience === NotificationAudience::None) {
            return 0;
        }

        $query = Registration::query()
            ->where('event_id', $event->id)
            ->with(['event.organizer', 'ticketType', 'tickets', 'order', 'settledPayment', 'user']);

        $query = match ($audience) {
            NotificationAudience::Everyone => $query->whereIn('status', [
                RegistrationStatus::Confirmed->value,
                RegistrationStatus::Pending->value,
                RegistrationStatus::AwaitingPayment->value,
                RegistrationStatus::Waitlisted->value,
            ]),
            NotificationAudience::TicketHolders => $query
                ->where('status', RegistrationStatus::Confirmed->value)
                ->whereHas('tickets'),
            NotificationAudience::Registered => $query->whereIn('status', [
                RegistrationStatus::Confirmed->value,
                RegistrationStatus::Pending->value,
                RegistrationStatus::AwaitingPayment->value,
            ]),
            NotificationAudience::None => $query->whereRaw('1 = 0'),
        };

        $count = 0;

        $query->orderBy('id')->chunkById(100, function ($registrations) use ($type, $payload, &$count): void {
            foreach ($registrations as $registration) {
                $this->notifyRegistration($registration, $type, array_merge($payload, [
                    'idempotency_suffix' => ($payload['idempotency_suffix'] ?? 'broadcast') . ':' . ($payload['batch'] ?? now()->timestamp),
                ]));
                $count++;
            }
        });

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.notifications.broadcast', [
                'event_uuid' => $event->uuid,
                'type' => $type,
                'audience' => $audience->value,
                'queued' => $count,
            ]);

        return $count;
    }

    public function queueIfDue(EventNotification $notification): void
    {
        if ($notification->status === NotificationStatus::Cancelled) {
            return;
        }

        if ($notification->status === NotificationStatus::Sent) {
            return;
        }

        if ($notification->scheduled_at !== null && $notification->scheduled_at->isFuture()) {
            return;
        }

        $notification->markQueued();
        SendEventNotificationJob::dispatch($notification->id);

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.notifications.queued', [
                'notification_uuid' => $notification->uuid,
                'type' => $notification->type,
            ]);
    }

    public function retry(EventNotification $notification): EventNotification
    {
        $notification->status = NotificationStatus::Pending;
        $notification->queue_status = 'retrying';
        $notification->error = null;
        $notification->failed_at = null;
        $notification->scheduled_at = now();
        $notification->save();

        $this->queueIfDue($notification);

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.notifications.manual_retry', [
                'notification_uuid' => $notification->uuid,
            ]);

        return $notification->fresh();
    }

    private function cancelledStub(
        Registration $registration,
        NotificationType $type,
        string $reason
    ): EventNotification {
        $notification = new EventNotification();
        $notification->event_id = $registration->event_id;
        $notification->registration_id = $registration->id;
        $notification->user_id = $registration->user_id;
        $notification->recipient_email = $registration->attendee_email;
        $notification->channel = NotificationChannel::Mail;
        $notification->type = $type->value;
        $notification->status = NotificationStatus::Cancelled;
        $notification->queue_status = 'suppressed';
        $notification->error = $reason;
        $notification->scheduled_at = now();
        $notification->payload = ['suppressed' => true];
        $notification->idempotency_key = sprintf(
            'suppressed:%s:%s:%s',
            $type->value,
            $registration->id,
            now()->timestamp
        );
        $notification->save();

        return $notification;
    }
}
