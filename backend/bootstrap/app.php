<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // cPanel / reverse proxies: trust X-Forwarded-* so $request->ip(),
        // secure(), rate limits, and HSTS behave correctly in production.
        // Override via TRUSTED_PROXIES (comma-separated IPs) if locking down.
        $trusted = env('TRUSTED_PROXIES', '*');
        $middleware->trustProxies(
            at: $trusted === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', (string) $trusted)))),
            headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->append(\App\Http\Middleware\PerformanceMonitoringMiddleware::class);
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
            'verified' => \App\Http\Middleware\VerifiedMiddleware::class,
            'app.access' => \App\Http\Middleware\ApplicationAccessMiddleware::class,
        ]);
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        // Analytics
        $schedule->job(new \App\Jobs\Analytics\AggregateAnalyticsMetricsJob)->daily()->description('Aggregate Analytics Metrics');
        
        // Maintenance
        $schedule->job(new \App\Jobs\Maintenance\CertificateVerificationCleanupJob)->daily()->description('Clean Certificate Verification Logs');
        $schedule->job(new \App\Jobs\Maintenance\DatabaseMaintenanceJob)->daily()->description('Optimize Database & Prune Temp Disk Reports');
        $schedule->job(new \App\Jobs\Maintenance\NotificationCleanupJob)->daily()->description('Clean In-App Notifications History');
        $schedule->job(new \App\Jobs\Maintenance\ArchiveOldLogsJob)->monthlyOn(1, '01:00')->description('Archive Job History Logs to Disk');

        // Reports
        $schedule->job(new \App\Jobs\Reports\GenerateDailyReportJob)->dailyAt('23:55')->description('Generate Daily Analytics Report');
        $schedule->job(new \App\Jobs\Reports\GenerateWeeklyReportJob)->weeklyOn(7, '23:59')->description('Generate Weekly Analytics/Engagement Report');
        $schedule->job(new \App\Jobs\Reports\GenerateMonthlyReportJob)->lastDayOfMonth('23:59')->description('Generate Monthly Statistics & Leadership Report');

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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Scoped to /api/v1/ems/* — every other module keeps the framework
        // defaults it has today.
        \App\Ems\Support\EmsExceptionHandler::register($exceptions);
    })->create();
