<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Registered in bootstrap/app.php for Laravel 11.
        // Copied here as reference:
        
        // $schedule->job(new \App\Jobs\Analytics\AggregateAnalyticsMetricsJob)->daily()->description('Aggregate Analytics Metrics');
        // $schedule->job(new \App\Jobs\Maintenance\CertificateVerificationCleanupJob)->daily()->description('Clean Certificate Verification Logs');
        // $schedule->job(new \App\Jobs\Maintenance\DatabaseMaintenanceJob)->daily()->description('Optimize Database & Prune Temp Disk Reports');
        // $schedule->job(new \App\Jobs\Maintenance\NotificationCleanupJob)->daily()->description('Clean In-App Notifications History');
        // $schedule->job(new \App\Jobs\Maintenance\ArchiveOldLogsJob)->monthlyOn(1, '01:00')->description('Archive Job History Logs to Disk');
        // $schedule->job(new \App\Jobs\Reports\GenerateDailyReportJob)->dailyAt('23:55')->description('Generate Daily Analytics Report');
        // $schedule->job(new \App\Jobs\Reports\GenerateWeeklyReportJob)->weeklyOn(7, '23:59')->description('Generate Weekly Analytics/Engagement Report');
        // $schedule->job(new \App\Jobs\Reports\GenerateMonthlyReportJob)->lastDayOfMonth('23:59')->description('Generate Monthly Statistics & Leadership Report');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
