<?php

namespace App\Listeners;

use App\Events\AnnouncementPublishedEvent;
use App\Events\CertificateAwardedEvent;
use App\Events\CourseCompletedEvent;
use App\Events\TrainingScheduledEvent;
use App\Models\NotificationLog;
use App\Models\User;
use App\Notifications\CertificateEarnedNotification;
use App\Notifications\CourseCompletedNotification;
use App\Notifications\NewAnnouncementNotification;
use App\Notifications\UpcomingTrainingNotification;
use App\Services\NewsletterService;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;

class NotificationEventSubscriber
{
    /**
     * Handle notification sent event.
     */
    public function handleNotificationSent(NotificationSent $event): void
    {
        $notifiable = $event->notifiable;
        if ($notifiable instanceof User) {
            NotificationLog::create([
                'user_id' => $notifiable->id,
                'notification_type' => get_class($event->notification),
                'channel' => $this->normalizeChannelName($event->channel),
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        }
    }

    /**
     * Handle notification failed event.
     */
    public function handleNotificationFailed(NotificationFailed $event): void
    {
        $notifiable = $event->notifiable;
        if ($notifiable instanceof User) {
            NotificationLog::create([
                'user_id' => $notifiable->id,
                'notification_type' => get_class($event->notification),
                'channel' => $this->normalizeChannelName($event->channel),
                'status' => 'failed',
                'error_message' => isset($event->data['exception']) ? $event->data['exception']->getMessage() : 'Error during delivery.',
                'sent_at' => null,
            ]);
        }
    }

    /**
     * Handle course completed event.
     */
    public function handleCourseCompleted(CourseCompletedEvent $event): void
    {
        $event->user->notify(new CourseCompletedNotification($event->courseName, $event->completionDate));
    }

    /**
     * Handle announcement published event.
     */
    public function handleAnnouncementPublished(AnnouncementPublishedEvent $event): void
    {
        $query = User::query()->where('is_active', true);

        if ($event->audience !== 'All') {
            $query->whereHas('roles', function ($q) use ($event) {
                $q->where('slug', strtolower($event->audience))
                  ->orWhere('name', $event->audience);
            });
        }

        $users = $query->get();

        foreach ($users as $user) {
            $user->notify(new NewAnnouncementNotification(
                $event->announcementTitle,
                $event->announcementExcerpt,
                $event->announcementSlug,
                $event->audience
            ));
        }

        app(NewsletterService::class)->sendAnnouncementToSubscribers(
            $event->announcementTitle,
            $event->announcementExcerpt,
            $event->announcementSlug
        );
    }

    /**
     * Handle training scheduled event.
     */
    public function handleTrainingScheduled(TrainingScheduledEvent $event): void
    {
        $query = User::query()->where('is_active', true);

        if ($event->targetAudience !== 'All') {
            $query->whereHas('roles', function ($q) use ($event) {
                $q->where('slug', strtolower($event->targetAudience))
                  ->orWhere('name', $event->targetAudience);
            });
        }

        $users = $query->get();

        foreach ($users as $user) {
            $user->notify(new UpcomingTrainingNotification(
                $event->trainingTitle,
                $event->trainingDate,
                $event->trainingLocation,
                $event->trainingDescription,
                $event->trainingSlug
            ));
        }
    }

    /**
     * Handle certificate awarded event.
     */
    public function handleCertificateAwarded(CertificateAwardedEvent $event): void
    {
        $event->award->user->notify(new CertificateEarnedNotification($event->award));
    }

    /**
     * Normalize channel names for human readability and DB consistency.
     */
    protected function normalizeChannelName(string $channel): string
    {
        if (str_contains($channel, 'CustomDatabaseChannel')) {
            return 'in_app';
        }
        if ($channel === 'database') {
            return 'in_app';
        }
        if ($channel === 'mail') {
            return 'email';
        }
        return $channel;
    }

    /**
     * Handle EMS registration created event.
     */
    public function handleEmsRegistrationCreated(\App\Ems\Events\RegistrationCreated $event): void
    {
        /** @var \App\Ems\Models\Registration $registration */
        $registration = $event->subject;

        if (!$registration || !$registration->user_id) {
            return;
        }

        $user = User::find($registration->user_id);
        if (!$user) {
            return;
        }

        $idempotencyKey = "ems_registration_in_app:" . $registration->uuid;
        $alreadyExists = \App\Models\Notification::where('user_id', $user->id)
            ->where('data->idempotency_key', $idempotencyKey)
            ->exists();

        if ($alreadyExists) {
            return;
        }

        $registration->loadMissing(['event']);
        $eventTitle = $registration->event?->name ?? $registration->event?->title ?? 'MSA Event';
        $eventSlug = $registration->event?->slug;

        $user->notify(new \App\Notifications\PlatformNotification(
            "Registration Confirmed: {$eventTitle}",
            "Your registration (#{$registration->reference}) for {$eventTitle} has been confirmed.",
            [
                'type' => 'event',
                'event_slug' => $eventSlug,
                'registration_uuid' => $registration->uuid,
                'reference' => $registration->reference,
                'idempotency_key' => $idempotencyKey,
            ]
        ));
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe($events): array
    {
        return [
            NotificationSent::class => 'handleNotificationSent',
            NotificationFailed::class => 'handleNotificationFailed',
            CourseCompletedEvent::class => 'handleCourseCompleted',
            AnnouncementPublishedEvent::class => 'handleAnnouncementPublished',
            TrainingScheduledEvent::class => 'handleTrainingScheduled',
            CertificateAwardedEvent::class => 'handleCertificateAwarded',
            \App\Ems\Events\RegistrationCreated::class => 'handleEmsRegistrationCreated',
        ];
    }
}
