<?php

namespace App\Ems\Enums;

/**
 * Derived attendance state for attendee list filters and display.
 */
enum CheckInStatus: string
{
    case NotCheckedIn = 'not_checked_in';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::NotCheckedIn => 'Not Checked In',
            self::CheckedIn => 'Attending',
            self::CheckedOut => 'Checked Out',
            self::NoShow => "Didn't come",
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
