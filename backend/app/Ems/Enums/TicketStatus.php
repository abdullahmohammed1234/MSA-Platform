<?php

namespace App\Ems\Enums;

/**
 * Ticket lifecycle. Issuing, QR generation and redemption are Phase 2/4.
 */
enum TicketStatus: string
{
    case Issued = 'issued';
    case Redeemed = 'redeemed';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Issued',
            self::Redeemed => 'Redeemed',
            self::Revoked => 'Revoked',
            self::Expired => 'Expired',
        };
    }

    /**
     * Whether the ticket can still be presented at check-in.
     */
    public function isRedeemable(): bool
    {
        return $this === self::Issued;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
