<?php

namespace App\Ems\Enums;

enum WaitlistStatus: string
{
    case Waiting = 'waiting';
    case Promoted = 'promoted';
    case Left = 'left';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Waiting',
            self::Promoted => 'Promoted',
            self::Left => 'Left',
            self::Expired => 'Expired',
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
