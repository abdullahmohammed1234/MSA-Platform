<?php

namespace App\Support;

/**
 * Parses TRUSTED_PROXIES into a value for Middleware::trustProxies(at: ...).
 *
 * Fails closed: unset, empty, or invalid input trusts no proxy, so client
 * supplied X-Forwarded-* headers are ignored. The wildcard is honoured only
 * when the entire value is exactly "*", because it trusts whichever peer
 * connects and lets direct clients choose their own IP and scheme.
 */
final class TrustedProxies
{
    /**
     * @return array<int, string>|string
     */
    public static function resolve(?string $raw): array|string
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return [];
        }

        if ($raw === '*') {
            return '*';
        }

        $valid = [];

        foreach (explode(',', $raw) as $entry) {
            $entry = trim($entry);

            if ($entry !== '' && self::isIpOrCidr($entry)) {
                $valid[] = $entry;
            }
        }

        return array_values(array_unique($valid));
    }

    private static function isIpOrCidr(string $entry): bool
    {
        if (filter_var($entry, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        $parts = explode('/', $entry);

        if (count($parts) !== 2 || ! ctype_digit($parts[1])) {
            return false;
        }

        [$address, $bits] = [$parts[0], (int) $parts[1]];

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $bits >= 0 && $bits <= 32;
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return $bits >= 0 && $bits <= 128;
        }

        return false;
    }
}
