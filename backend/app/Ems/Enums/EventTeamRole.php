<?php

namespace App\Ems\Enums;

/**
 * How a user is attached to an event's delivery team.
 *
 * Organizer roles live on ems_event_organizers, staff roles on ems_event_staff.
 * Both use this enum so a single vocabulary describes an event's team.
 */
enum EventTeamRole: string
{
    case LeadOrganizer = 'lead_organizer';
    case CoOrganizer = 'co_organizer';
    case Staff = 'staff';
    case CheckInStaff = 'check_in_staff';
    case Volunteer = 'volunteer';

    public function label(): string
    {
        return match ($this) {
            self::LeadOrganizer => 'Lead Organizer',
            self::CoOrganizer => 'Co-organizer',
            self::Staff => 'Event Staff',
            self::CheckInStaff => 'Check-in Staff',
            self::Volunteer => 'Volunteer',
        };
    }

    public function isOrganizer(): bool
    {
        return in_array($this, [self::LeadOrganizer, self::CoOrganizer], true);
    }

    /**
     * @return array<int, string>
     */
    public static function organizerValues(): array
    {
        return [self::LeadOrganizer->value, self::CoOrganizer->value];
    }

    /**
     * @return array<int, string>
     */
    public static function staffValues(): array
    {
        return [self::Staff->value, self::CheckInStaff->value, self::Volunteer->value];
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
