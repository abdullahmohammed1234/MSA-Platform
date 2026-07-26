<?php

namespace App\Ems\Enums;

/**
 * How an attendee was checked in. Phase 1 persists the column; the scanner and
 * walk-in flows are Phase 4.
 */
enum CheckInMethod: string
{
    case Manual = 'manual';
    case QrScan = 'qr_scan';
    case WalkIn = 'walk_in';
    case Import = 'import';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::QrScan => 'QR Scan',
            self::WalkIn => 'Walk-in',
            self::Import => 'Imported',
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
