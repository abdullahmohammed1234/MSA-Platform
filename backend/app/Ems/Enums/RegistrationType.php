<?php

namespace App\Ems\Enums;

/**
 * Distinguishes free registrations from ones that require a payment. Phase 1
 * establishes the column so Phase 3 can branch on it without a migration.
 */
enum RegistrationType: string
{
    case Free = 'free';
    case Paid = 'paid';

    public function requiresPayment(): bool
    {
        return $this === self::Paid;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
