<?php

namespace App\Platform\Enums;

enum SystemHealthStatus: string
{
    case OPERATIONAL = 'operational';
    case DEGRADED = 'degraded';
    case UNAVAILABLE = 'unavailable';
    case UNKNOWN = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::OPERATIONAL => 'Operational',
            self::DEGRADED => 'Degraded',
            self::UNAVAILABLE => 'Unavailable',
            self::UNKNOWN => 'Unknown',
        };
    }
}
