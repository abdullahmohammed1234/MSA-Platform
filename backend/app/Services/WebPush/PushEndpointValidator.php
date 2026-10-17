<?php

namespace App\Services\WebPush;

class PushEndpointValidator
{
    /**
     * Optional custom DNS resolver callback for testing without real network calls.
     *
     * @var (callable(string): array<int, string>)|null
     */
    protected static $dnsResolver = null;

    /**
     * Set a custom DNS resolver callback for deterministic testing.
     *
     * @param (callable(string): array<int, string>)|null $resolver
     */
    public static function setDnsResolver(?callable $resolver): void
    {
        self::$dnsResolver = $resolver;
    }

    /**
     * Reset the DNS resolver to default network resolver.
     */
    public static function resetDnsResolver(): void
    {
        self::$dnsResolver = null;
    }

    /**
     * Validate external push service endpoint for SSRF safety.
     */
    public static function validate(string $url): bool
    {
        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
            return false;
        }

        // 1. Scheme must be strictly HTTPS
        if (strtolower($parsed['scheme']) !== 'https') {
            return false;
        }

        // 2. Prohibit embedded credentials (user:pass@host)
        if (!empty($parsed['user']) || !empty($parsed['pass'])) {
            return false;
        }

        // 3. Prohibit non-standard ports (must be 443 or omitted)
        if (!empty($parsed['port']) && (int) $parsed['port'] !== 443) {
            return false;
        }

        $host = strtolower($parsed['host']);

        // 4. Prohibit control characters, whitespace, and percent-encoded zone IDs
        if (preg_match('/[\s\x00-\x1F\x7F]/', $host)) {
            return false;
        }
        if (str_contains($host, '%')) {
            // Rejects IPv6 zone identifiers like fe80::1%eth0 or %25
            return false;
        }

        // 5. Prohibit internal, loopback, and reserved domain suffixes
        if (
            $host === 'localhost' ||
            str_ends_with($host, '.localhost') ||
            str_ends_with($host, '.internal') ||
            str_ends_with($host, '.local') ||
            str_ends_with($host, '.lan') ||
            str_ends_with($host, '.home') ||
            str_ends_with($host, '.corp') ||
            str_ends_with($host, '.test') ||
            str_ends_with($host, '.example') ||
            str_ends_with($host, '.invalid') ||
            str_ends_with($host, '.arpa') ||
            str_ends_with($host, '.onion')
        ) {
            return false;
        }

        // 6. Prohibit alternate numeric IP encodings (pure integer, octal, hex)
        if (ctype_digit($host) || preg_match('/^0[0-9xX]/', $host) || str_contains($host, '0x')) {
            return false;
        }
        // Abbreviated numeric IP (e.g. 127.1, 10.1)
        if (preg_match('/^\d+(\.\d+){1,2}$/', $host)) {
            return false;
        }

        // 7. Check if host is an IP literal
        $cleanHost = trim($host, '[]');
        if (filter_var($cleanHost, FILTER_VALIDATE_IP)) {
            return self::isPublicIp($cleanHost);
        }

        // 8. Hostname DNS resolution check (both IPv4 A and IPv6 AAAA)
        if (config('webpush.validate_dns', true)) {
            $records = self::resolveDnsRecords($cleanHost);
            if (!empty($records)) {
                foreach ($records as $resolvedIp) {
                    if (!self::isPublicIp($resolvedIp)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Check if an IP address is a valid, publicly routable destination suitable for Web Push.
     * Rejects loopback, private, link-local, carrier-grade NAT, multicast, reserved, and documentation ranges.
     */
    public static function isPublicIp(string $ip): bool
    {
        // IPv4 validation
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }

            $long = ip2long($ip);
            if ($long === false) {
                return false;
            }

            $uLong = sprintf('%u', $long);

            // 0.0.0.0/8 (Current network)
            if (str_starts_with($ip, '0.') || $ip === '0.0.0.0') {
                return false;
            }

            // 127.0.0.0/8 (Loopback)
            if (str_starts_with($ip, '127.')) {
                return false;
            }

            // 169.254.0.0/16 (Link-local)
            if (str_starts_with($ip, '169.254.')) {
                return false;
            }

            // 100.64.0.0/10 (Carrier-Grade NAT, RFC 6598)
            $cgnatStart = sprintf('%u', ip2long('100.64.0.0'));
            $cgnatEnd = sprintf('%u', ip2long('100.127.255.255'));
            if ($uLong >= $cgnatStart && $uLong <= $cgnatEnd) {
                return false;
            }

            // 224.0.0.0/4 (Multicast: 224.0.0.0 - 239.255.255.255)
            $multiStart = sprintf('%u', ip2long('224.0.0.0'));
            $multiEnd = sprintf('%u', ip2long('239.255.255.255'));
            if ($uLong >= $multiStart && $uLong <= $multiEnd) {
                return false;
            }

            // 240.0.0.0/4 (Reserved / Broadcast: 240.0.0.0 - 255.255.255.255)
            $resStart = sprintf('%u', ip2long('240.0.0.0'));
            if ($uLong >= $resStart) {
                return false;
            }

            return true;
        }

        // IPv6 validation
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // Strictly reject IPv4-mapped IPv6 (e.g. ::ffff:127.0.0.1 or ::ffff:7f00:1)
            $lower = strtolower($ip);
            if (str_contains($lower, '::ffff:') || str_contains($lower, 'ffff:')) {
                return false;
            }

            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }

            $bin = @inet_pton($ip);
            if ($bin === false || strlen($bin) !== 16) {
                return false;
            }

            $b0 = ord($bin[0]);
            $b1 = ord($bin[1]);

            // :: (unspecified) or ::1 (loopback)
            if ($bin === str_repeat("\x00", 16) || $bin === (str_repeat("\x00", 15) . "\x01")) {
                return false;
            }

            // ::ffff:0:0/96 (IPv4-mapped)
            if (substr($bin, 0, 12) === (str_repeat("\x00", 10) . "\xff\xff")) {
                return false;
            }

            // 64:ff9b::/96 (NAT64 well-known prefix)
            if (substr($bin, 0, 8) === "\x00\x64\xff\x9b\x00\x00\x00\x00") {
                return false;
            }

            // 100::/64 (Discard-only address block, RFC 6666)
            if (substr($bin, 0, 8) === "\x01\x00\x00\x00\x00\x00\x00\x00") {
                return false;
            }

            // 2001:db8::/32 (Documentation prefix, RFC 3849)
            if ($b0 === 0x20 && $b1 === 0x01 && ord($bin[2]) === 0x0d && ord($bin[3]) === 0xb8) {
                return false;
            }

            // 2002::/16 (6to4 prefix, RFC 3056)
            if ($b0 === 0x20 && $b1 === 0x02) {
                return false;
            }

            // fc00::/7 (Unique Local Address / ULA - private, RFC 4193)
            if (($b0 & 0xfe) === 0xfc) {
                return false;
            }

            // fe80::/10 (Link-local unicast, RFC 4291)
            if ($b0 === 0xfe && ($b1 & 0xc0) === 0x80) {
                return false;
            }

            // fec0::/10 (Site-local, deprecated RFC 3879)
            if ($b0 === 0xfe && ($b1 & 0xc0) === 0xc0) {
                return false;
            }

            // ff00::/8 (Multicast, RFC 4291)
            if ($b0 === 0xff) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Resolve DNS records for host, querying both IPv4 A and IPv6 AAAA records.
     *
     * @return array<int, string>
     */
    public static function resolveDnsRecords(string $host): array
    {
        if (self::$dnsResolver !== null) {
            return (self::$dnsResolver)($host);
        }

        $records = [];

        if (function_exists('dns_get_record')) {
            $aRecords = @dns_get_record($host, DNS_A);
            if (is_array($aRecords)) {
                foreach ($aRecords as $rec) {
                    if (!empty($rec['ip'])) {
                        $records[] = $rec['ip'];
                    }
                }
            }

            $aaaaRecords = @dns_get_record($host, DNS_AAAA);
            if (is_array($aaaaRecords)) {
                foreach ($aaaaRecords as $rec) {
                    if (!empty($rec['ipv6'])) {
                        $records[] = $rec['ipv6'];
                    }
                }
            }
        }

        if (empty($records) && function_exists('gethostbynamel')) {
            $ips = @gethostbynamel($host);
            if (is_array($ips)) {
                $records = array_merge($records, $ips);
            }
        }

        return array_values(array_unique($records));
    }
}
