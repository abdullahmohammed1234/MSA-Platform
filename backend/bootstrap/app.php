<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        then: function (): void {
            \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::except('/up');
            \Illuminate\Support\Facades\Route::get('/up', \App\Http\Controllers\HealthController::class);
        },
    )
    // Listeners are registered explicitly in AppServiceProvider. With discovery on,
    // `event:cache` also captured the subscriber's handle* methods, registering each
    // handler twice so every domain event sent its notifications twice.
    ->withEvents(discover: false)
    ->withMiddleware(function (Middleware $middleware): void {
        // TRUSTED_PROXIES: comma-separated proxy IPs/CIDRs. Unset or invalid
        // trusts nobody, so forwarded headers from direct clients are ignored.
        // Set it only to the verified address of a proxy in front of the app.
        $middleware->trustProxies(
            at: \App\Support\TrustedProxies::resolve(env('TRUSTED_PROXIES')),
            headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->statefulApi();
        $middleware->prepend(\App\Http\Middleware\ScopeAuthorizationMemo::class);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->append(\App\Http\Middleware\PerformanceMonitoringMiddleware::class);
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
            'verified' => \App\Http\Middleware\VerifiedMiddleware::class,
            'app.access' => \App\Http\Middleware\ApplicationAccessMiddleware::class,
            'vms.can' => \App\Http\Middleware\VolunteeringPermission::class,
        ]);
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        // Analytics
        // Runs at 00:00, so it aggregates the day that just finished.
        $schedule->job(new \App\Jobs\Analytics\AggregateAnalyticsMetricsJob(\Illuminate\Support\Carbon::yesterday()))->daily()->description('Aggregate Analytics Metrics');
        
        // Maintenance
        $schedule->job(new \App\Jobs\Maintenance\CertificateVerificationCleanupJob)->daily()->description('Clean Certificate Verification Logs');
        $schedule->job(new \App\Jobs\Maintenance\DatabaseMaintenanceJob)->daily()->description('Optimize Database & Prune Temp Disk Reports');
        $schedule->job(new \App\Jobs\Maintenance\NotificationCleanupJob)->daily()->description('Clean In-App Notifications History');
        $schedule->job(new \App\Jobs\Maintenance\ArchiveOldLogsJob)->monthlyOn(1, '01:00')->description('Archive Job History Logs to Disk');

        // Reports
        $schedule->job(new \App\Jobs\Reports\GenerateDailyReportJob)->dailyAt('23:55')->description('Generate Daily Analytics Report');
        $schedule->job(new \App\Jobs\Reports\GenerateWeeklyReportJob)->weeklyOn(7, '23:59')->description('Generate Weekly Analytics/Engagement Report');
        $schedule->job(new \App\Jobs\Reports\GenerateMonthlyReportJob)->lastDayOfMonth('23:59')->description('Generate Monthly Statistics & Leadership Report');

        // Announcements
        $schedule->job(new \App\Jobs\Announcements\RecoverAnnouncementDeliveriesJob)
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->description('Announcements: Resume interrupted deliveries and due retries');

        // EMS Phase 5 — communications
        $schedule->job(new \App\Ems\Jobs\ProcessDueRemindersJob)
            ->everyMinute()
            ->withoutOverlapping()
            ->description('EMS: Process due event reminders');
        $schedule->job(new \App\Ems\Jobs\ProcessDueNotificationsJob)
            ->everyMinute()
            ->withoutOverlapping()
            ->description('EMS: Process due scheduled notifications');

        $schedule->job(new \App\Ems\Jobs\ExpireAbandonedCheckoutsJob)
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->description('EMS: Expire abandoned Square checkouts');

        $schedule->job(new \App\Ems\Jobs\RecoverSquareWebhookEventsJob)
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->description('EMS: Recover stuck or failed Square webhooks');

        $schedule->job(new \App\Ems\Jobs\ExpireStalePromotionsJob)
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->description('EMS: Expire stale waitlist promotions');

        $schedule->job(new \App\Ems\Jobs\ReconcileSquareSalesJob)
            ->hourly()
            ->withoutOverlapping()
            ->description('EMS: Reconcile Square POS and payment sales');

        $schedule->job(new \App\Ems\Jobs\CleanupStalePreviewsJob)
            ->hourly()
            ->withoutOverlapping()
            ->description('EMS: Clean up stale uncommitted import preview files');

        // Must stay last: routes/console.php tasks are registered before this callback runs.
        \App\Support\ScheduleCadence::align($schedule, (int) config('app.scheduler_cron_interval_minutes', 1));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Scoped to /api/v1/ems/* — every other module keeps the framework
        // defaults it has today.
        \App\Ems\Support\EmsExceptionHandler::register($exceptions);
    })->create();
