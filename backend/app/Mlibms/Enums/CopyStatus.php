<?php

namespace App\Mlibms\Enums;

enum CopyStatus: string
{
    CASE AVAILABLE = 'available';
    CASE CHECKED_OUT = 'checked_out';
    CASE RESERVED = 'reserved';
    CASE LOST = 'lost';
    CASE DAMAGED = 'damaged';
    CASE MAINTENANCE = 'maintenance';
    CASE RETIRED = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Available',
            self::CHECKED_OUT => 'Checked Out',
            self::RESERVED => 'Reserved',
            self::LOST => 'Lost',
            self::DAMAGED => 'Damaged',
            self::MAINTENANCE => 'Maintenance',
            self::RETIRED => 'Retired',
        };
    }

    public function isBorrowable(): bool
    {
        return $this === self::AVAILABLE;
    }
}
