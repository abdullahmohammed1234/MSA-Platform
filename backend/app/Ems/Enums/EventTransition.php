<?php

namespace App\Ems\Enums;

use App\Ems\Support\EmsPermissions;

/**
 * The edges of the event state machine.
 *
 * Every legal state change is a named transition with one or more source
 * states, one target state and one required permission. Adding a state or an
 * administrative recovery path in a later phase means adding a case here — no
 * policy, controller, service or component has to change.
 */
enum EventTransition: string
{
    case Publish = 'publish';
    case Unpublish = 'unpublish';
    case OpenRegistration = 'open_registration';
    case CloseRegistration = 'close_registration';
    case MarkLive = 'mark_live';
    case Complete = 'complete';
    case Archive = 'archive';
    case Cancel = 'cancel';

    /**
     * Named fromStatus()/toStatus() rather than from()/to() because a backed
     * enum already declares a static from().
     *
     * Cancel is multi-source — prefer fromStatuses() for that case.
     */
    public function fromStatus(): EventStatus
    {
        return match ($this) {
            self::Publish => EventStatus::Draft,
            self::Unpublish => EventStatus::Published,
            self::OpenRegistration => EventStatus::Published,
            self::CloseRegistration => EventStatus::RegistrationOpen,
            self::MarkLive => EventStatus::RegistrationClosed,
            self::Complete => EventStatus::Live,
            self::Archive => EventStatus::Completed,
            self::Cancel => EventStatus::Published,
        };
    }

    /**
     * @return array<int, EventStatus>
     */
    public function fromStatuses(): array
    {
        return match ($this) {
            self::Cancel => [
                EventStatus::Published,
                EventStatus::RegistrationOpen,
                EventStatus::RegistrationClosed,
                EventStatus::Live,
            ],
            default => [$this->fromStatus()],
        };
    }

    public function toStatus(): EventStatus
    {
        return match ($this) {
            self::Publish => EventStatus::Published,
            self::Unpublish => EventStatus::Draft,
            self::OpenRegistration => EventStatus::RegistrationOpen,
            self::CloseRegistration => EventStatus::RegistrationClosed,
            self::MarkLive => EventStatus::Live,
            self::Complete => EventStatus::Completed,
            self::Archive => EventStatus::Archived,
            self::Cancel => EventStatus::Cancelled,
        };
    }

    public function permission(): string
    {
        return match ($this) {
            self::Publish => EmsPermissions::EVENTS_PUBLISH,
            self::Unpublish => EmsPermissions::EVENTS_UNPUBLISH,
            self::OpenRegistration => EmsPermissions::EVENTS_OPEN_REGISTRATION,
            self::CloseRegistration => EmsPermissions::EVENTS_CLOSE_REGISTRATION,
            self::MarkLive => EmsPermissions::EVENTS_MARK_LIVE,
            self::Complete => EmsPermissions::EVENTS_COMPLETE,
            self::Archive => EmsPermissions::EVENTS_ARCHIVE,
            self::Cancel => EmsPermissions::EVENTS_CANCEL,
        };
    }

    /**
     * The label shown on the action button in the lifecycle panel.
     */
    public function label(): string
    {
        return match ($this) {
            self::Publish => 'Publish',
            self::Unpublish => 'Return to Draft',
            self::OpenRegistration => 'Open Registration',
            self::CloseRegistration => 'Close Registration',
            self::MarkLive => 'Mark Live',
            self::Complete => 'Complete',
            self::Archive => 'Archive',
            self::Cancel => 'Cancel Event',
        };
    }

    /**
     * Copy for the confirmation dialog. Transitions that are hard to walk back
     * get an explicit warning.
     */
    public function confirmation(): string
    {
        return match ($this) {
            self::Publish => 'Publish this event? It becomes visible to audiences outside the EMS.',
            self::Unpublish => 'Return this event to draft? It will no longer be visible outside the EMS.',
            self::OpenRegistration => 'Open registration for this event?',
            self::CloseRegistration => 'Close registration for this event? No further sign-ups will be accepted.',
            self::MarkLive => 'Mark this event as live? Use this when the event is under way.',
            self::Complete => 'Complete this event? This cannot be undone.',
            self::Archive => 'Archive this event? Archived events are read-only and cannot be restored.',
            self::Cancel => 'Cancel this event? Attendees will be notified and refund workflows will be initiated where applicable. This cannot be undone.',
        };
    }

    public function isIrreversible(): bool
    {
        return in_array($this, [self::Complete, self::Archive, self::Cancel], true);
    }

    /**
     * Every transition that is legal from the given state.
     *
     * @return array<int, self>
     */
    public static function availableFrom(EventStatus $status): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $transition): bool => in_array($status, $transition->fromStatuses(), true)
        ));
    }

    /**
     * Resolve the transition that moves an event between two specific states,
     * or null when no such edge exists.
     */
    public static function between(EventStatus $from, EventStatus $to): ?self
    {
        foreach (self::cases() as $transition) {
            if (in_array($from, $transition->fromStatuses(), true) && $transition->toStatus() === $to) {
                return $transition;
            }
        }

        return null;
    }
}
