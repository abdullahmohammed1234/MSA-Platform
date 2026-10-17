<?php

namespace Tests\Feature\Phase28;

use Cron\CronExpression;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Month-end, leap-year and week boundaries for the report schedules on the default
 * per-minute cron and on an aligned 10-minute cron. `schedule:run` re-registers the
 * schedule on every invocation, so each case registers it on a day of the month under test.
 */
class SchedulerCalendarTest extends TestCase
{
    use RefreshDatabase;

    private const MONTHLY = 'Generate Monthly Statistics & Leadership Report';
    private const WEEKLY = 'Generate Weekly Analytics/Engagement Report';
    private const DAILY = 'Generate Daily Analytics Report';
    private const ARCHIVE = 'Archive Job History Logs to Disk';

    /** @return array<string, array{string, int}> */
    public static function months(): array
    {
        $cases = [];
        foreach (['2026-12', '2027-01', '2027-02', '2027-03', '2027-04', '2027-06', '2027-09', '2027-11', '2028-02'] as $month) {
            foreach ([1, 10] as $interval) {
                $cases["{$month}, {$interval}-minute cron"] = [$month, $interval];
            }
        }

        return $cases;
    }

    #[DataProvider('months')]
    public function test_monthly_report_runs_once_on_the_last_day_of_every_month(string $month, int $interval): void
    {
        $start = Carbon::parse("{$month}-01 00:00:00", 'UTC');
        $lastDay = $start->copy()->endOfMonth()->startOfDay();
        $schedule = $this->scheduleRegisteredAt($lastDay->copy()->setTime(12, 0), $interval);

        $due = $this->dueTimes($schedule[self::MONTHLY], $start, $start->copy()->addMonth(), $interval);

        $this->assertCount(1, $due, "{$month}: exactly one monthly run");
        $this->assertSame($lastDay->toDateString(), $due[0]->toDateString(), "{$month}: on the last day");
        $this->assertGreaterThanOrEqual(23, $due[0]->hour, 'late on the last day');

        // Weekly: every Sunday of the month, once. Daily: once per day. Archive: the 1st.
        $sundays = 0;
        for ($d = $start->copy(); $d < $start->copy()->addMonth(); $d->addDay()) {
            $sundays += $d->isSunday() ? 1 : 0;
        }
        $weekly = $this->dueTimes($schedule[self::WEEKLY], $start, $start->copy()->addMonth(), $interval);
        $this->assertCount($sundays, $weekly);
        foreach ($weekly as $at) {
            $this->assertTrue($at->isSunday());
        }
        $this->assertCount($start->daysInMonth, $this->dueTimes($schedule[self::DAILY], $start, $start->copy()->addMonth(), $interval));
        $archive = $this->dueTimes($schedule[self::ARCHIVE], $start, $start->copy()->addMonth(), $interval);
        $this->assertCount(1, $archive);
        $this->assertSame(1, $archive[0]->day);
    }

    public function test_the_application_and_scheduler_run_in_utc(): void
    {
        // Host cron runs in the server's local zone, but Laravel decides what is due in
        // app time; UTC has no daylight-saving gaps or repeated hours.
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('UTC', date_default_timezone_get());

        $schedule = $this->scheduleRegisteredAt(Carbon::parse('2027-03-14 12:00:00', 'UTC'), 10);
        // 2027-03-14 is the North American spring-forward Sunday: the 03:00 and 03:30 maintenance
        // tasks still run exactly once that day.
        $day = Carbon::parse('2027-03-14 00:00:00', 'UTC');
        foreach (['platform:prune-logs', 'sanctum:prune-expired'] as $command) {
            $label = collect(array_keys($schedule))->first(fn ($l) => str_contains($l, $command));
            $this->assertNotNull($label, $command);
            $this->assertCount(1, $this->dueTimes($schedule[$label], $day, $day->copy()->addDay(), 10), $command);
        }
    }

    /** @return array<string, string> */
    private function scheduleRegisteredAt(Carbon $at, int $interval): array
    {
        $this->travelTo($at);
        config(['app.scheduler_cron_interval_minutes' => $interval]);
        Artisan::call('schedule:list');

        $out = [];
        foreach (app(Schedule::class)->events() as $event) {
            $out[$event->description ?: $event->command] = $event->expression;
        }

        return $out;
    }

    /** @return list<Carbon> */
    private function dueTimes(string $expression, Carbon $from, Carbon $until, int $grid): array
    {
        // Every run time must fall on a minute the host cron actually invokes.
        $cron = new CronExpression($expression);
        $due = [];
        $at = Carbon::instance($cron->getNextRunDate($from, 0, true, 'UTC'));
        while ($at < $until) {
            $this->assertSame(0, $at->minute % $grid, "{$expression} at {$at} is off the {$grid}-minute cron grid");
            $due[] = $at;
            $at = Carbon::instance($cron->getNextRunDate($at, 0, false, 'UTC'));
        }

        return $due;
    }
}
