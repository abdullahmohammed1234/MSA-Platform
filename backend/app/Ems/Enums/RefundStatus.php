<?php

namespace App\Ems\Enums;

/**
 * Refund processing statuses for registrations.
 *
 * Kept independent of RegistrationStatus so a cancelled registration without
 * a refund remains distinguishable from a refunded registration.
 */
enum RefundStatus: string
{
    case NotApplicable = 'not_applicable';
    case NotRequested = 'not_requested';
    case Pending = 'pending';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case PartiallyRefunded = 'partially_refunded';

    public function label(): string
    {
        return match ($this) {
            self::NotApplicable => 'Not Applicable',
            self::NotRequested => 'Not Requested',
            self::Pending => 'Refund Pending',
            self::Processing => 'Refund Processing',
            self::Succeeded => 'Refunded',
            self::Failed => 'Refund Failed',
            self::PartiallyRefunded => 'Partially Refunded',
        };
    }

    public function isCompleted(): bool
    {
        return in_array($this, [self::Succeeded, self::PartiallyRefunded], true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

