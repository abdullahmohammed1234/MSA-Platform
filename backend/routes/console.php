<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// withoutOverlapping() uses the default cache store's lock (file cache = single
// server only). The explicit expiry bounds how long a crashed run blocks the next.
Schedule::command('store:cleanup-expired-orders')->everyFifteenMinutes()->withoutOverlapping(30);
// Hourly at :55: the 23:55 run opens the day's period; the other runs only retry unsent
// periods. Period assignment is fixed by the 23:55 cutoff, not by when cron fires.
Schedule::command('volunteer:send-daily-digest')->hourlyAt(55)->withoutOverlapping(60);
Schedule::command('mlibms:process-overdue-and-reminders')->dailyAt('08:00')->withoutOverlapping(120);
Schedule::command('platform:monitor-health-and-alerts')->everyMinute()->withoutOverlapping(10);
Schedule::command('operations:detect')->everyFiveMinutes()->withoutOverlapping(30);
Schedule::command('platform:prune-logs')->dailyAt('03:00')->withoutOverlapping(120);
Schedule::command('vms:send-reminders')->everyFifteenMinutes()->withoutOverlapping(30);
// Expiry is enforced at authentication time by Sanctum; this only reclaims rows.
// --hours must stay non-negative: a negative value deletes still-valid tokens.
Schedule::command('sanctum:prune-expired', ['--hours' => 24])->dailyAt('03:30')->withoutOverlapping(60);

// Bounded queue worker for shared hosting (CloudLinux/cPanel): processes queued jobs
// without maintaining a long-running supervisor daemon.
// Under the actual 15-minute cPanel cron schedule, --max-time=800 allows the worker to continue
// processing newly arriving jobs for 13.3 minutes before exiting cleanly ahead of the next invocation.
// withoutOverlapping(15) prevents duplicate concurrent workers and expires safely on the 15-minute cron boundary.
Schedule::command('queue:work --max-time=800 --sleep=3 --tries=3 --timeout=60 --queue=ems-payments,ems-notifications,ems-operations,high,default,low')
    ->everyFifteenMinutes()
    ->withoutOverlapping(20);

// Square payment reconciliation runs every ten minutes to correlate missed webhooks,
// settle captured authorizations, and resolve ambiguous checkouts.
// withoutOverlapping(20) bounds lock duration so crashed runs never block indefinitely.
Schedule::command('ems:square-reconcile')->everyFifteenMinutes()->withoutOverlapping(20);


