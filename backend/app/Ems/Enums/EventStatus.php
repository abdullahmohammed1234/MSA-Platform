<?php

namespace App\Ems\Enums;

/**
 * The event lifecycle states.
 *
 * The legal edges of the state machine live in EventTransition; this enum only
 * describes the states themselves and the presentation metadata the UI needs.
 */
enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case RegistrationOpen = 'registration_open';
    case RegistrationClosed = 'registration_closed';
    case Live = 'live';
    case Completed = 'completed';
    case Archived = 'archived';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::RegistrationOpen => 'Registration Open',
            self::RegistrationClosed => 'Registration Closed',
            self::Live => 'Live',
            self::Completed => 'Completed',
            self::Archived => 'Archived',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * A semantic tone the frontend maps onto its own palette, so status colours
     * are not hard-coded in two places.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Published => 'info',
            self::RegistrationOpen => 'success',
            self::RegistrationClosed => 'warning',
            self::Live => 'live',
            self::Completed => 'muted',
            self::Archived => 'muted',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Whether the event is visible to audiences outside the EMS.
     *
     * Draft stays private. Archived is intentionally excluded from public
     * discovery (direct links also 404). Completed is included so past-event
     * browsing works without exposing operational records.
     */
    public function isPubliclyVisible(): bool
    {
        return in_array($this, [
            self::Published,
            self::RegistrationOpen,
            self::RegistrationClosed,
            self::Live,
            self::Completed,
        ], true);
    }

    /**
     * Terminal states have no outgoing transitions other than administrative
     * recovery, which Phase 1 deliberately does not provide.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Archived, self::Cancelled], true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<int, array{value: string, label: string, tone: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
            'tone' => $status->tone(),
        ], self::cases());
    }
}
