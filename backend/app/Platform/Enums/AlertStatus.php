<?php

namespace App\Platform\Enums;

enum AlertStatus: string
{
    case NEW = 'new';
    case ACKNOWLEDGED = 'acknowledged';
    case RESOLVED = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'New Alert',
            self::ACKNOWLEDGED => 'Acknowledged',
            self::RESOLVED => 'Resolved',
        };
    }
}
