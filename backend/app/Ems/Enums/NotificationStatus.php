<?php

namespace App\Ems\Enums;

/**
 * Delivery state for a queued event notification. Phase 5 drives these
 * transitions; Phase 1 only reserves the column.
 */
enum NotificationStatus: string
{
    case Pending = 'pending';
    case Scheduled = 'scheduled';
    case Sent = 'sent';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Scheduled => 'Scheduled',
            self::Sent => 'Sent',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
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
