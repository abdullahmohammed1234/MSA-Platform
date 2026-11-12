<?php

$isProduction = strtolower((string) env('APP_ENV')) === 'production';

$defaultFrontend = $isProduction ? 'https://sfumsa.ca' : 'http://localhost:5173';
$defaultConnect  = $isProduction ? 'https://connect.sfumsa.ca' : 'http://localhost:5174';
$defaultProjects = $isProduction ? 'https://projects.sfumsa.ca' : 'http://localhost:5175';

$frontendUrl = rtrim(trim((string) env('FRONTEND_URL', $defaultFrontend)), '/');
$connectUrl = rtrim(trim((string) env('CONNECT_URL', $defaultConnect)), '/');
$projectsUrl = rtrim(trim((string) env('PROJECTS_URL', $defaultProjects)), '/');

$extraOrigins = array_values(array_filter(array_map(
    fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
), fn (string $origin): bool => $origin !== ''));

$canonicalProductionOrigins = [
    'https://sfumsa.ca',
    'https://www.sfumsa.ca',
    'https://connect.sfumsa.ca',
    'https://projects.sfumsa.ca',
];

$allowedOrigins = array_values(array_unique(array_filter([
    $frontendUrl,
    $connectUrl,
    $projectsUrl,
    ...($isProduction ? $canonicalProductionOrigins : [
        'http://localhost:5173',
        'http://localhost:5174',
        'http://localhost:5175',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:5174',
        'http://127.0.0.1:5175',
        ...$canonicalProductionOrigins,
    ]),
    ...$extraOrigins,
])));

// Dev-server origins are credentialed CORS grants; production must not trust loopback or wildcards.
if ($isProduction) {
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
        'X-CSRF-TOKEN',
        'Cache-Control',
        'Pragma',
    ],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
