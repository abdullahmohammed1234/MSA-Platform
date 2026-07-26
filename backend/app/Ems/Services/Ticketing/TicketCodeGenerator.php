<?php

namespace App\Ems\Services\Ticketing;

use App\Ems\Models\Ticket;

/**
 * Generates globally unique, non-sequential, human-readable ticket codes.
 *
 * Format: {PREFIX}-{CrockfordBase32} — e.g. MSA-7K9MQ2X4P8.
 * Codes are stable once issued and suitable for QR encoding.
 */
class TicketCodeGenerator
{
    /**
     * Crockford Base32 alphabet — no I, L, O, U to reduce transcription errors.
     */
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function generate(): string
    {
        $prefix = strtoupper((string) config('ems.tickets.code_prefix', 'MSA'));
        $length = (int) config('ems.tickets.code_length', 10);

        do {
            $code = $prefix . '-' . $this->randomSegment($length);
        } while (Ticket::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    /**
     * Human-quotable registration reference, e.g. REG-8F3K2A.
     */
    public function registrationReference(): string
    {
        do {
            $reference = 'REG-' . $this->randomSegment(6);
        } while (\App\Ems\Models\Registration::withTrashed()->where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * Human-quotable order reference, e.g. ORD-8F3K2A.
     */
    public function orderReference(): string
    {
        do {
            $reference = 'ORD-' . $this->randomSegment(8);
        } while (\App\Ems\Models\Order::withTrashed()->where('reference', $reference)->exists());

        return $reference;
    }

    private function randomSegment(int $length): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $segment = '';

        for ($i = 0; $i < $length; $i++) {
            $segment .= $alphabet[random_int(0, $max)];
        }

        return $segment;
    }
}
