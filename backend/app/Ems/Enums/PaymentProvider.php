<?php

namespace App\Ems\Enums;

/**
 * Payment providers the EMS can record against a payment.
 *
 * Square is listed because Phase 3 targets it, but no Square SDK, credential
 * or webhook exists in Phase 1 — see App\Ems\Contracts\PaymentGateway for the
 * seam the integration will plug into.
 */
enum PaymentProvider: string
{
    case Square = 'square';
    case Stripe = 'stripe';
    case PayPal = 'paypal';
    case Manual = 'manual';
    case Waived = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::Square => 'Square',
            self::Stripe => 'Stripe',
            self::PayPal => 'PayPal',
            self::Manual => 'Manual / Offline',
            self::Waived => 'Waived',
        };
    }

    /**
     * Whether settlement is driven by an external provider webhook rather than
     * an EMS operator.
     */
    public function isExternal(): bool
    {
        return in_array($this, [self::Square, self::Stripe, self::PayPal], true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
