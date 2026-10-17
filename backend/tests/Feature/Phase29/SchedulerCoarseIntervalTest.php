<?php

namespace Tests\Feature\Phase29;

use App\Support\ScheduleCadence;
use Cron\CronExpression;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Coarse cron intervals a shared host might impose (15, 20, 30, 60 minutes): no task may be
 * silently skipped, report dates stay on their calendar day, and unsupported values are refused.
 */
class SchedulerCoarseIntervalTest extends TestCase
{
    use RefreshDatabase;

    private const MONTHLY = 'Generate Monthly Statistics & Leadership Report';
    private const WEEKLY = 'Generate Weekly Analytics/Engagement Report';
    private const ARCHIVE = 'Archive Job History Logs to Disk';

    /** @return array<string, array{string, int}> */
    public static function cases(): array
    {
        $cases = [];
        foreach (['2026-12', '2027-02', '2028-02'] as $month) {
            foreach ([15, 20, 30, 60] as $interval) {
                $cases["{$month}, {$interval}-minute cron"] = [$month, $interval];
            }
        }

        return $cases;
    }

    #[DataProvider('cases')]
    public function test_every_task_still_runs_on_a_coarse_cron(string $month, int $interval): void
    {
        $start = Carbon::parse("{$month}-01 00:00:00", 'UTC');
        $end = $start->copy()->addMonth();
        $lastDay = $start->copy()->endOfMonth()->startOfDay();

        $coarse = $this->scheduleRegisteredAt($lastDay->copy()->setTime(12, 0), $interval);
        $this->assertCount(24, $coarse, 'all 24 scheduled tasks (8 console, 16 bootstrap) are registered');

        $monthly = $this->dueTimes($coarse[self::MONTHLY], $start, $end, $interval);
        $this->assertCount(1, $monthly);
        $this->assertSame($lastDay->toDateString(), $monthly[0]->toDateString());

        $weekly = $this->dueTimes($coarse[self::WEEKLY], $start, $end, $interval);
        foreach ($weekly as $at) {
            $this->assertTrue($at->isSunday(), "weekly report on {$at}");
        }
        $this->assertCount(
            count(array_filter(iterator_to_array($start->toPeriod($end->copy()->subDay())), fn ($d) => $d->isSunday())),
            $weekly
        );

        $archive = $this->dueTimes($coarse[self::ARCHIVE], $start, $end, $interval);
        $this->assertCount(1, $archive);
        $this->assertSame(1, $archive[0]->day);

        // Two sample days. Alignment rewrites only the minute field, so a task whose hour field
        // is "*" was designed to run at least hourly and must still do so; every other task
        // (bar the weekly/monthly ones) runs every day.
        $dayStart = $lastDay->copy()->subDay();
        $dayEnd = $lastDay->copy()->addDay();
        foreach ($coarse as $label => $expression) {
            if (in_array($label, [self::MONTHLY, self::WEEKLY, self::ARCHIVE], true)) {
                continue;
            }
            $runs = count($this->dueTimes($expression, $dayStart, $dayEnd, $interval));

            $this->assertGreaterThanOrEqual(2, $runs, "{$label} must run every day ({$expression})");
            if (explode(' ', $expression)[1] === '*') {
                $this->assertGreaterThanOrEqual(48, $runs, "{$label} must still run at least hourly ({$expression})");
            }
        }
    }

    /** @return array<string, array{int, bool}> */
    public static function intervals(): array
    {
        return [
            '1 (default)' => [1, true],
            '10' => [10, true],
            '15' => [15, true],
            '60' => [60, true],
            '7' => [7, false],
            '45' => [45, false],
            '0' => [0, false],
            '90' => [90, false],
        ];
    }

    #[DataProvider('intervals')]
    public function test_production_check_refuses_an_interval_the_cron_cannot_honour(int $interval, bool $supported): void
    {
        config([
            'app.env' => 'production', 'app.debug' => false, 'app.url' => 'https://api.example.test',
            'app.frontend_url' => 'https://example.test',
            'queue.default' => 'database', 'announcements.delivery.email_hourly_cap' => '100',
            'app.scheduler_cron_interval_minutes' => $interval,
        ]);

        $exit = Artisan::call('msa:production-check');
        $output = Artisan::output();

        $this->assertSame($supported, ScheduleCadence::isSupported($interval));
        $this->assertSame($supported ? 0 : 1, $exit, $output);
        $this->assertSame(! $supported, str_contains($output, 'FAIL: SCHEDULER_CRON_INTERVAL_MINUTES'), $output);
    }

    public function test_hourly_alignment_produces_valid_cron_expressions(): void
    {
        $this->assertSame('0', ScheduleCadence::alignMinuteField('*', 60));
        $this->assertSame('0', ScheduleCadence::alignMinuteField('*/5', 60));
        $this->assertSame('0', ScheduleCadence::alignMinuteField('*/15', 60));
        $this->assertSame('0', ScheduleCadence::alignMinuteField('55', 60));
        $this->assertSame('0', ScheduleCadence::alignMinuteField('0,30', 60));

        foreach ($this->scheduleRegisteredAt(Carbon::parse('2027-02-28 12:00:00', 'UTC'), 60) as $label => $expression) {
            $this->assertTrue(CronExpression::isValidExpression($expression), "{$label}: {$expression}");
            $this->assertSame('0', explode(' ', $expression)[0], "{$label}: {$expression}");
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
