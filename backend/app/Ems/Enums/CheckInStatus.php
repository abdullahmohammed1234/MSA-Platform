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

    public function label(): string
    {
        return match ($this) {
            self::NotCheckedIn => 'Not Checked In',
            self::CheckedIn => 'Checked In',
            self::CheckedOut => 'Checked Out',
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
