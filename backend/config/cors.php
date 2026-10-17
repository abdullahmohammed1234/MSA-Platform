<?php

$frontendUrl = rtrim(trim((string) env('FRONTEND_URL', 'http://localhost:5173')), '/');
$connectUrl = rtrim(trim((string) env('CONNECT_URL', 'http://localhost:5174')), '/');
$extraOrigins = array_values(array_filter(array_map(
    fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
), fn (string $origin): bool => $origin !== ''));

$allowedOrigins = array_values(array_unique(array_filter([
    $frontendUrl,
    $connectUrl,
    'http://localhost:5173',
    'http://localhost:5174',
    'http://127.0.0.1:5173',
    'http://127.0.0.1:5174',
    ...$extraOrigins,
])));

// Dev-server origins are credentialed CORS grants; production must not trust loopback or wildcards.
if (strtolower((string) env('APP_ENV')) === 'production') {
    $allowedOrigins = array_values(array_filter(
        $allowedOrigins,
        fn (string $origin): bool => $origin !== '*' && ! in_array(parse_url($origin, PHP_URL_HOST), ['localhost', '127.0.0.1', '[::1]', '::1'], true),
    ));
}

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-Requested-With',
        'X-XSRF-TOKEN',
    ],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
