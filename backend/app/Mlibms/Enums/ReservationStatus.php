<?php

namespace App\Mlibms\Enums;

enum ReservationStatus: string
{
    CASE PENDING = 'pending';
    CASE READY_FOR_PICKUP = 'ready_for_pickup';
    CASE FULFILLED = 'fulfilled';
    CASE CANCELLED = 'cancelled';
    CASE EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Queue',
            self::READY_FOR_PICKUP => 'Ready for Pickup',
            self::FULFILLED => 'Fulfilled',
            self::CANCELLED => 'Cancelled',
            self::EXPIRED => 'Expired',
        };
    }
}
