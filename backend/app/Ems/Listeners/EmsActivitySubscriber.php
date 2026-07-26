<?php

namespace App\Ems\Listeners;

use App\Ems\Events\AttendeeCheckedIn;
use App\Ems\Events\AttendeesImported;
use App\Ems\Events\CheckInUndone;
use App\Ems\Events\EmsDomainEvent;
use App\Ems\Events\EventCategoryChanged;
use App\Ems\Events\EventCreated;
use App\Ems\Events\EventDeleted;
use App\Ems\Events\EventStatusChanged;
use App\Ems\Events\EventUpdated;
use App\Ems\Events\RegistrationCreated;
use App\Ems\Events\TicketIssued;
use App\Ems\Events\WalkInRegistered;
use App\Ems\Services\EmsActivityLogger;
use Illuminate\Events\Dispatcher;

/**
 * Turns EMS domain events into audit entries.
 *
 * Keeping this in one subscriber means services stay focused on their domain
 * work and there is exactly one place that decides what an EMS action looks
 * like in the audit trail.
 */
class EmsActivitySubscriber
{
    public function __construct(private readonly EmsActivityLogger $logger)
    {
    }

    public function handle(EmsDomainEvent $event): void
    {
        $this->logger->log(
            $event->action(),
            $event->subject,
            $event->description(),
            $event->payload(),
        );
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            EventCreated::class => 'handle',
            EventUpdated::class => 'handle',
            EventDeleted::class => 'handle',
            EventStatusChanged::class => 'handle',
            EventCategoryChanged::class => 'handle',
            RegistrationCreated::class => 'handle',
            TicketIssued::class => 'handle',
            AttendeeCheckedIn::class => 'handle',
            CheckInUndone::class => 'handle',
            AttendeesImported::class => 'handle',
            WalkInRegistered::class => 'handle',
        ];
    }
}
