<?php

return [
    /*
    |--------------------------------------------------------------------------
    | VAPID Configuration
    |--------------------------------------------------------------------------
    | Voluntary Application Server Identification (RFC 8292).
    | The public key is shared with the client to subscribe with the browser's
    | PushManager, while the private key signs push requests server-side.
    */
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@sfumsa.ca'),
        'public_key' => env('VAPID_PUBLIC_KEY', null),
        'private_key' => env('VAPID_PRIVATE_KEY', null),
        'pem_file' => env('VAPID_PEM_FILE', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transport & Connection Timeouts
    |--------------------------------------------------------------------------
    | Strict bounded connection and total-response timeouts for push delivery.
    | Prevents hanging external HTTP push requests from stalling synchronous requests.
    */
    'timeout' => (float) env('WEBPUSH_TIMEOUT', 5.0),
    'connect_timeout' => (float) env('WEBPUSH_CONNECT_TIMEOUT', 3.0),

    /*
    |--------------------------------------------------------------------------
    | Maximum Payload Size (Bytes)
    |--------------------------------------------------------------------------
    | Standard Web Push RFC 8291 limits payloads to 4078 bytes.
    */
    'max_payload_bytes' => (int) env('WEBPUSH_MAX_PAYLOAD_BYTES', 3072),

    /*
    |--------------------------------------------------------------------------
    | Device Limits and Delivery Bounds
    |--------------------------------------------------------------------------
    | Maximum active device subscriptions allowed per user and maximum devices
    | attempted per synchronous push delivery. Bounds synchronous fan-out latency.
    */
    'max_devices_per_user' => (int) env('WEBPUSH_MAX_DEVICES_PER_USER', 10),
    'max_devices_per_delivery' => (int) env('WEBPUSH_MAX_DEVICES_PER_DELIVERY', 10),

    /*
    |--------------------------------------------------------------------------
    | SSRF DNS Validation
    |--------------------------------------------------------------------------
    | Resolves push endpoint hostnames to verify they do not point to internal/
    | loopback or private network IP ranges.
    */
    'validate_dns' => (bool) env('WEBPUSH_VALIDATE_DNS', true),

    /*
    |--------------------------------------------------------------------------
    | Allowed Navigation Target Prefixes
    |--------------------------------------------------------------------------
    | Strict allowlist for notification click navigation URLs. Prevents
    | open redirect attacks and restricts navigation to internal app paths.
    */
    'allowed_navigation_prefixes' => [
        '/app/',
        '/',
    ],
];
