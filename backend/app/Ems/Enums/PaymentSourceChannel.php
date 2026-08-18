<?php

namespace App\Ems\Enums;

enum PaymentSourceChannel: string
{
    case Online = 'online';
    case Pos = 'pos';
    case Terminal = 'terminal';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Pos => 'Square POS',
            self::Terminal => 'Square Terminal',
            self::Other => 'Other',
        };
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
            'ECOMMERCE_API', 'BILLING', 'INVOICES', 'APPOINTMENTS' => self::Online,
            default => self::Other,
        };
    }
}
