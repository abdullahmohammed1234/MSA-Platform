<?php

namespace App\Ems\Services\Payments;

/**
 * Canonical hash of every field that affects a Square Payment Link.
 *
 * Field order is sorted so equivalent checkouts compare equal.
 */
class CheckoutFingerprint
{
    /**
     * @param  array{
     *     event_uuid: string,
     *     ticket_type_uuid: string,
     *     quantity: int,
     *     unit_price: float|int|string,
     *     subtotal: float|int|string,
     *     discount_amount?: float|int|string,
     *     fees?: float|int|string,
     *     taxes?: float|int|string,
     *     total: float|int|string,
     *     currency: string,
     *     email?: string|null,
     *     promo_code?: string|null,
     *     catalog_variation_id?: string|null
     * }  $details
     */
    public static function hash(array $details): string
    {
        $canonical = [
            'catalog_variation_id' => (string) ($details['catalog_variation_id'] ?? ''),
            'currency' => strtoupper((string) $details['currency']),
            'discount_amount' => self::money($details['discount_amount'] ?? 0),
            'email' => strtolower(trim((string) ($details['email'] ?? ''))),
            'event_uuid' => (string) $details['event_uuid'],
            'fees' => self::money($details['fees'] ?? 0),
            'promo_code' => strtoupper(trim((string) ($details['promo_code'] ?? ''))),
            'quantity' => (int) $details['quantity'],
            'subtotal' => self::money($details['subtotal']),
            'taxes' => self::money($details['taxes'] ?? 0),
            'ticket_type_uuid' => (string) $details['ticket_type_uuid'],
            'total' => self::money($details['total']),
            'unit_price' => self::money($details['unit_price']),
        ];

        ksort($canonical);

        return hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public static function prefix(?string $hash, int $length = 12): string
    {
        if ($hash === null || $hash === '') {
            return '';
        }

        return substr($hash, 0, $length);
    }

    private static function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
};
