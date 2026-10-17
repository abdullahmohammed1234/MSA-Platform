<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Connect Features Publication Flags
    |--------------------------------------------------------------------------
    |
    | Controls whether specific features within MSA Connect are enabled
    | and visible to members. Defaults to false (hidden by default).
    |
    */
    'connect_resources_enabled' => (bool) env('FEATURE_CONNECT_RESOURCES_ENABLED', false),

    /*
    | Out-of-scope scheduled side effects. Off unless an operator opts in.
    | EMS notifications use EMS_NOTIFICATIONS_ENABLED instead.
    */
    'volunteer_digest_enabled' => (bool) env('VOLUNTEER_DIGEST_ENABLED', false),
    'mlibms_overdue_processing_enabled' => (bool) env('MLIBMS_OVERDUE_PROCESSING_ENABLED', false),
    'database_optimize_enabled' => (bool) env('DB_OPTIMIZE_ENABLED', false),
];
