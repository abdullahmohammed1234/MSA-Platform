<?php

namespace App\Ems\Services\Notifications;

use App\Ems\Contracts\EventNotificationDispatcher;
use App\Ems\Enums\NotificationAudience;
use App\Ems\Enums\NotificationType;
use App\Ems\Models\Event;
use Illuminate\Support\Facades\Log;

/**
 * Post-event thank-you, feedback, recap, and certificate-available foundation.
 */
class PostEventCommunicationService
{
    public function __construct(
        private readonly EventNotificationDispatcher $dispatcher,
    ) {
    }

    /**
     * @return array{thank_you: int, feedback: int}
     */
    public function handleCompleted(Event $event): array
    {
        $thankYou = $this->dispatcher->broadcastToEvent($event, NotificationType::ThankYou->value, [
            'audience' => NotificationAudience::TicketHolders->value,
            'idempotency_suffix' => 'thank_you',
        ]);

        $feedback = $this->dispatcher->broadcastToEvent($event, NotificationType::FeedbackRequest->value, [
            'audience' => NotificationAudience::TicketHolders->value,
            'idempotency_suffix' => 'feedback',
        ]);

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.notifications.post_event_queued', [
                'event_uuid' => $event->uuid,
                'thank_you' => $thankYou,
                'feedback' => $feedback,
            ]);

        return [
            'thank_you' => $thankYou,
            'feedback' => $feedback,
        ];
    }

    public function sendRecap(Event $event, string $highlights = ''): int
    {
        return $this->dispatcher->broadcastToEvent($event, NotificationType::EventRecap->value, [
            'audience' => NotificationAudience::TicketHolders->value,
            'change_summary' => $highlights,
            'idempotency_suffix' => 'recap:' . now()->timestamp,
            'force' => true,
        ]);
    }

    /**
     * Foundation only — certificate generation is a future phase.
     */
    public function notifyCertificateAvailable(Event $event): int
    {
        return $this->dispatcher->broadcastToEvent($event, NotificationType::CertificateAvailable->value, [
            'audience' => NotificationAudience::TicketHolders->value,
            'idempotency_suffix' => 'certificate',
            'force' => true,
        ]);
    }
}
