<?php

namespace App\Ems\Enums;

/**
 * Delivery channels for event notifications. Phase 5 implements the dispatch;
 * Phase 1 only reserves the column.
 */
enum NotificationChannel: string
{
    case Mail = 'mail';
    case InApp = 'in_app';
    case Sms = 'sms';

    public function label(): string
    {
        return match ($this) {
            self::Mail => 'Email',
            self::InApp => 'In-app',
            self::Sms => 'SMS',
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
