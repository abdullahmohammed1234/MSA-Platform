<?php

namespace App\Store\Enums;

enum StoreFulfillmentStatus: string
{
    case Pending = 'pending';
    case Preparing = 'preparing';
    case ReadyForPickup = 'ready_for_pickup';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Fulfillment',
            self::Preparing => 'Preparing',
            self::ReadyForPickup => 'Ready for Pickup',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }
}
