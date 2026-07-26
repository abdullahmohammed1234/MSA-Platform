<?php

namespace App\Ems\Enums;

/**
 * Registration lifecycle. Phase 1 only persists the column and its default;
 * the workflow that moves registrations between these states is Phase 2/3.
 */
enum RegistrationStatus: string
{
    case Pending = 'pending';
    case AwaitingPayment = 'awaiting_payment';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Waitlisted = 'waitlisted';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::AwaitingPayment => 'Pending Payment',
            self::Confirmed => 'Registered',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
            self::Waitlisted => 'Waitlisted',
        };
    }

    /**
     * Whether the registration counts against the event's capacity.
     */
    public function occupiesCapacity(): bool
    {
        return in_array($this, [self::Pending, self::AwaitingPayment, self::Confirmed], true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
