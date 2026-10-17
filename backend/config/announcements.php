<?php

return [
    /*
    | Per-recipient announcement delivery. Each recipient/channel row is claimed
    | before sending and retried with backoff; a row is never resent after it is
    | recorded as sent. See App\Services\Announcements\AnnouncementDeliveryService.
    */
    'delivery' => [
        'queue' => env('ANNOUNCEMENT_DELIVERY_QUEUE', 'default'),
        'max_attempts' => (int) env('ANNOUNCEMENT_DELIVERY_MAX_ATTEMPTS', 5),
        // Minutes to wait before attempt N+1 (indexed by attempts already made, last value repeats).
        'backoff_minutes' => [1, 5, 15, 60],
        // A row left "sending" longer than this is presumed abandoned by a crashed worker.
        'sending_lease_seconds' => (int) env('ANNOUNCEMENT_DELIVERY_LEASE_SECONDS', 600),
        // Bounded work per job so a single run fits shared-hosting execution limits.
        'batch_size' => (int) env('ANNOUNCEMENT_DELIVERY_BATCH_SIZE', 200),
        'time_budget_seconds' => (int) env('ANNOUNCEMENT_DELIVERY_TIME_BUDGET', 30),
        // Mail-host limits (e.g. WHC shared hosting: a per-account hourly cap and a
        // 10-second spacing for bulk sends). The hourly cap counts announcement email
        // attempts only; leave headroom for other mail. It must be a positive integer,
        // or the literal "unlimited" for a host without a cap. When it is missing or
        // invalid, announcement email is held (in-app and push still go out) and a
        // platform alert is raised; mail beyond a host cap is silently dropped.
        'email_hourly_cap' => env('ANNOUNCEMENT_EMAIL_HOURLY_CAP'),
        'email_min_interval_seconds' => (int) env('ANNOUNCEMENT_EMAIL_MIN_INTERVAL_SECONDS', 0),
        // When the hourly cap is reached, the sending job is re-queued after this delay.
        'throttle_requeue_minutes' => (int) env('ANNOUNCEMENT_THROTTLE_REQUEUE_MINUTES', 10),
    ],
];
