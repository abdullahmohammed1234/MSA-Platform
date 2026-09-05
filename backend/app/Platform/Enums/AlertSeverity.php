<?php

namespace App\Platform\Enums;

enum AlertSeverity: string
{
    case INFO = 'info';
    case WARNING = 'warning';
    case CRITICAL = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::INFO => 'Informational',
            self::WARNING => 'Warning',
            self::CRITICAL => 'Critical',
        };
    }
}
