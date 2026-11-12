<?php

namespace App\Pm\Enums;

enum MilestoneStatus: string
{
    case Pending = 'pending';
    case Reached = 'reached';
    case Delayed = 'delayed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Reached => 'Reached',
            self::Delayed => 'Delayed',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
