<?php

namespace App\Ems\Enums;

/**
 * Payment lifecycle. Phase 1 creates the model and the column only — no
 * provider is wired up and no money moves.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Processing = 'processing';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
    case Abandoned = 'abandoned';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Authorized => 'Authorized',
            self::Processing => 'Processing',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
            self::PartiallyRefunded => 'Partially Refunded',
            self::Abandoned => 'Abandoned',
        };
    }

    public function isSettled(): bool
    {
        return $this === self::Paid;
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [
                self::Authorized,
                self::Processing,
                self::Paid,
                self::Failed,
                self::Cancelled,
                self::Abandoned,
            ],
            self::Authorized => [self::Paid, self::Failed, self::Cancelled, self::Abandoned],
            self::Processing => [self::Paid, self::Failed, self::Cancelled, self::Abandoned],
            self::Paid => [self::Refunded, self::PartiallyRefunded],
            self::PartiallyRefunded => [self::Refunded],
            self::Abandoned => [self::Paid, self::Cancelled],
            self::Cancelled => [],
            self::Failed, self::Refunded => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
