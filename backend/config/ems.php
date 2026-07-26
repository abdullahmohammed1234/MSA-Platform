<?php

/*
|--------------------------------------------------------------------------
| MSA Event Management System (EMS)
|--------------------------------------------------------------------------
|
| Configuration for the EMS module. The EMS is a bounded module inside the
| MSA platform: it owns its own tables (ems_*), its own API surface
| (/api/v1/ems/*) and its own front-end area (/ems), while reusing the
| platform's users, Sanctum authentication and RBAC tables.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | The EMS mounts under its own prefix so it never collides with the public
    | website events (/api/v1/website/events) or the CMS events module
    | (/api/v1/admin/cms/events).
    |
    */

    'route' => [
        'prefix' => env('EMS_API_PREFIX', 'api/v1/ems'),
        'middleware' => ['api'],
        'throttle' => env('EMS_API_THROTTLE', 'ems_api'),
        'rate_limit_per_minute' => (int) env('EMS_API_RATE_LIMIT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public surface (Phase 2)
    |--------------------------------------------------------------------------
    |
    | Unauthenticated discovery, registration and ticket endpoints mount under
    | {prefix}/public. Registration is throttled more aggressively.
    |
    */

    'public' => [
        'throttle' => env('EMS_PUBLIC_THROTTLE', 'ems_public'),
        'rate_limit_per_minute' => (int) env('EMS_PUBLIC_RATE_LIMIT', 60),
        'registration_throttle' => env('EMS_REGISTRATION_THROTTLE', 'ems_registration'),
        'registration_rate_limit_per_minute' => (int) env('EMS_REGISTRATION_RATE_LIMIT', 5),
        'calendar_max_events' => (int) env('EMS_PUBLIC_CALENDAR_MAX', 500),

        // Public marketing / ticket pages (outside the /ems admin shell).
        'frontend_url' => rtrim((string) env(
            'EMS_PUBLIC_FRONTEND_URL',
            rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/')
        ), '/'),

        // Optional absolute base for QR validation URLs. When empty, QR codes
        // encode {frontend_url}/tickets/{code}.
        'ticket_validation_url' => rtrim((string) env('EMS_TICKET_VALIDATION_URL', ''), '/'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Frontend
    |--------------------------------------------------------------------------
    |
    | Used when building absolute links back into the EMS UI (notifications,
    | e-mails). Defaults to the platform frontend with the /ems path.
    |
    */

    'frontend_url' => rtrim((string) env('EMS_FRONTEND_URL', rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/') . '/ems'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'channel' => env('EMS_LOG_CHANNEL', 'ems'),

        // Context keys that must never reach the log files or the audit trail.
        'redacted_keys' => [
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'access_token',
            'refresh_token',
            'api_key',
            'secret',
            'authorization',
            'card',
            'card_number',
            'cvv',
            'square_access_token',
            'nonce',
            'source_id',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'timezone' => env('EMS_DEFAULT_TIMEZONE', 'America/Vancouver'),
        'currency' => env('EMS_DEFAULT_CURRENCY', 'CAD'),
        'per_page' => (int) env('EMS_DEFAULT_PER_PAGE', 15),
        'max_per_page' => (int) env('EMS_MAX_PER_PAGE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    'dashboard' => [
        'upcoming_limit' => (int) env('EMS_DASHBOARD_UPCOMING_LIMIT', 5),
        'activity_limit' => (int) env('EMS_DASHBOARD_ACTIVITY_LIMIT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Seeding
    |--------------------------------------------------------------------------
    |
    | Development users are only seeded when this is enabled AND the
    | application is not running in production.
    |
    */

    'seed_development_users' => (bool) env('EMS_SEED_DEV_USERS', false),

    /*
    |--------------------------------------------------------------------------
    | Payments (Phase 3)
    |--------------------------------------------------------------------------
    |
    | Square Hosted Checkout. Credentials never leave the server. Leave
    | EMS_PAYMENTS_ENABLED=false until Square sandbox/production is configured.
    |
    */

    'payments' => [
        'default_provider' => env('EMS_PAYMENT_PROVIDER', 'square'),
        'enabled' => (bool) env('EMS_PAYMENTS_ENABLED', false),
        'queue' => env('EMS_PAYMENTS_QUEUE', 'ems-payments'),
        'webhook_throttle' => env('EMS_WEBHOOK_THROTTLE', 'ems_webhooks'),
        'webhook_rate_limit_per_minute' => (int) env('EMS_WEBHOOK_RATE_LIMIT', 120),

        'square' => [
            'environment' => env('SQUARE_ENVIRONMENT', 'sandbox'),
            'application_id' => env('SQUARE_APPLICATION_ID'),
            'access_token' => env('SQUARE_ACCESS_TOKEN'),
            'location_id' => env('SQUARE_LOCATION_ID'),
            'webhook_signature_key' => env('SQUARE_WEBHOOK_SIGNATURE_KEY'),
            // Must exactly match the URL configured in the Square Developer Dashboard.
            'webhook_notification_url' => env(
                'SQUARE_WEBHOOK_NOTIFICATION_URL',
                rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/api/v1/webhooks/square'
            ),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ticketing (Phase 2/4 placeholder)
    |--------------------------------------------------------------------------
    */

    'tickets' => [
        'enabled' => (bool) env('EMS_TICKETS_ENABLED', true),
        'code_prefix' => env('EMS_TICKET_PREFIX', 'MSA'),
        'code_length' => (int) env('EMS_TICKET_CODE_LENGTH', 10),
        'qr_enabled' => (bool) env('EMS_TICKET_QR_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Operations / Check-in (Phase 4)
    |--------------------------------------------------------------------------
    */

    'operations' => [
        'queue' => env('EMS_OPERATIONS_QUEUE', 'ems-operations'),
        'import_chunk' => (int) env('EMS_IMPORT_CHUNK', 100),
        'import_sync_threshold' => (int) env('EMS_IMPORT_SYNC_THRESHOLD', 50),
        'check_in_throttle' => env('EMS_CHECK_IN_THROTTLE', 'ems_check_in'),
        'check_in_rate_limit_per_minute' => (int) env('EMS_CHECK_IN_RATE_LIMIT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage (future media / ticket assets)
    |--------------------------------------------------------------------------
    */

    'storage' => [
        'disk' => env('EMS_STORAGE_DISK', env('FILESYSTEM_DISK', 'local')),
        'path' => env('EMS_STORAGE_PATH', 'ems'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications / Queues (Phase 5)
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        'enabled' => (bool) env('EMS_NOTIFICATIONS_ENABLED', false),
        'queue' => env('EMS_NOTIFICATIONS_QUEUE', 'ems-notifications'),
        'from_address' => env('EMS_MAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),
        'from_name' => env('EMS_MAIL_FROM_NAME', 'SFU MSA Events'),
        'max_retries' => (int) env('EMS_NOTIFICATIONS_MAX_RETRIES', 3),
        'process_due_every_minutes' => (int) env('EMS_NOTIFICATIONS_PROCESS_EVERY', 1),
        'default_reminders_enabled' => (bool) env('EMS_DEFAULT_REMINDERS_ENABLED', false),
    ],

];
