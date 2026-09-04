<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('store:cleanup-expired-orders')->everyFifteenMinutes();
Schedule::command('volunteer:send-daily-digest')->dailyAt('23:55');

