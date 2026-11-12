<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SFU MSA Project Management System (PMS) Configuration
    |--------------------------------------------------------------------------
    |
    | Authoritative configuration for scheduling, effort inference, capacity
    | management, and resource balancing.
    |
    */

    'effort' => [
        // Default hours inferred per active working day when estimated_hours is omitted
        'default_inferred_hours_per_day' => (float) env('PM_DEFAULT_INFERRED_HOURS_PER_DAY', 2.0),

        // Default working days of the week (1 = Monday, 5 = Friday, ISO-8601 standard)
        'default_working_days' => [1, 2, 3, 4, 5],
    ],

    'capacity' => [
        // Default provisional student volunteer availability fallbacks
        'default_weekly_hours' => (float) env('PM_DEFAULT_WEEKLY_CAPACITY_HOURS', 10.0),
        'default_daily_hours' => (float) env('PM_DEFAULT_DAILY_CAPACITY_HOURS', 2.0),
        'default_max_concurrent_tasks' => (int) env('PM_DEFAULT_MAX_CONCURRENT_TASKS', 5),

        // Utilization thresholds
        'thresholds' => [
            'over_allocated' => 100.0, // Above 100% is over-allocated
            'optimal_min' => 70.0,    // 70% to 100% is optimal/balanced
            'under_utilized_min' => 0.0, // Above 0% and below 70% is under-utilized
        ],
    ],

    'bulk' => [
        // Strict safety cap on batch task operations
        'max_batch_size' => 50,
    ],
];

