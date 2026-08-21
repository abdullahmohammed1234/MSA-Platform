<?php

namespace App\Ems\Enums;

enum PaymentSourceChannel: string
{
    case Online = 'online';
    case Pos = 'pos';
    case Terminal = 'terminal';
    case SquareOnlineStore = 'square_online_store';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Pos => 'Square POS',
            self::Terminal => 'Square Terminal',
            self::SquareOnlineStore => 'Square Online Store',
            self::Other => 'Other',
        };
    }

    /**
     * Value stored on registration.metadata.source for admin/attendee display.
     */
    public function registrationSource(): string
    {
        return match ($this) {
            self::SquareOnlineStore => self::SquareOnlineStore->value,
            self::Terminal => self::Terminal->value,
            self::Pos => 'square_pos',
            self::Online => 'ems_checkout',
            self::Other => 'square',
        };
    }

    public function isWalkIn(): bool
    {
        return $this === self::Pos || $this === self::Terminal;
    }

    /**
     * Square Online Store requires a captured payment (COMPLETED/PAID).
     * POS/Terminal historically treat APPROVED as settled; keep that.
     */
    public function treatsApprovedAsSettled(): bool
    {
        return $this !== self::SquareOnlineStore;
    }

    public static function fromSquarePayment(array $payment): self
    {
        if (! empty($payment['terminal_checkout_id'])) {
            return self::Terminal;
        }

        $product = strtoupper((string) data_get($payment, 'application_details.square_product', ''));

        return match ($product) {
            'TERMINAL_API' => self::Terminal,
            'SQUARE_POS', 'VIRTUAL_TERMINAL' => self::Pos,
            'ONLINE_STORE' => self::SquareOnlineStore,
            'ECOMMERCE_API', 'BILLING', 'INVOICES', 'APPOINTMENTS' => self::Online,
            default => self::Other,
        };
    }
}
