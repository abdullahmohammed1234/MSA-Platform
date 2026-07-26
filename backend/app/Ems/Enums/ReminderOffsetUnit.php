<?php

namespace App\Ems\Enums;

enum ReminderOffsetUnit: string
{
    case Minutes = 'minutes';
    case Hours = 'hours';
    case Days = 'days';

    public function label(): string
    {
        return match ($this) {
            self::Minutes => 'Minutes',
            self::Hours => 'Hours',
            self::Days => 'Days',
        };
    }

    public function toMinutes(int $value): int
    {
        return match ($this) {
            self::Minutes => $value,
            self::Hours => $value * 60,
            self::Days => $value * 60 * 24,
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
