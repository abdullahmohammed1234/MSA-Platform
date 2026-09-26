<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Volunteer Management System (VMS) Configuration
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        'enabled' => (bool) env('VMS_NOTIFICATIONS_ENABLED', true),

        'reminders' => [
            'enabled' => (bool) env('VMS_REMINDERS_ENABLED', true),
            // Timing offsets (in hours before shift start_at) to send reminders
            'timings' => [24, 2],
        ],

        // Administrative notification recipient email addresses
        'admin_recipients' => array_filter(explode(',', (string) env('VMS_ADMIN_NOTIFICATION_EMAILS', ''))),
    ],

    'waitlist' => [
        'promotion_expiry_hours' => (int) env('VMS_WAITLIST_EXPIRY_HOURS', 24),
    ],

];
