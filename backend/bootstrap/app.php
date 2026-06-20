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
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->append(\App\Http\Middleware\PerformanceMonitoringMiddleware::class);
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
            'verified' => \App\Http\Middleware\VerifiedMiddleware::class,
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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
