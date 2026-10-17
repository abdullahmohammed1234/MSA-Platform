<?php

namespace Tests\Feature\Phase27;

use App\Mail\DailyVolunteerDigestMail;
use App\Models\VolunteerRegistration;
use App\Support\ScheduleCadence;
use Cron\CronExpression;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * `schedule:run` only runs tasks due in the minute it is invoked. These tests simulate
 * a host cron that can only invoke it every N minutes (WHC reports a 10-minute minimum).
 */
class SchedulerCadenceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The schedule is registered once per console application, so call this at most
     * once per test.
     *
     * @return array<string, string> label => cron expression
     */
    private function registeredSchedule(int $interval): array
    {
        // Fixed month so lastDayOfMonth() resolves deterministically (October: 31 days).
        $this->travelTo(Carbon::parse('2026-10-14 12:00:00'));
        config(['app.scheduler_cron_interval_minutes' => $interval]);
        Artisan::call('schedule:list');

        $out = [];
        foreach (app(Schedule::class)->events() as $event) {
            $out[$event->description ?: $event->command] = $event->expression;
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $schedule
     * @return array<string, int> label => number of cron invocations that run it during October 2026
     */
    private function runsOnGrid(array $schedule, int $grid): array
    {
        $counts = array_fill_keys(array_keys($schedule), 0);
        $crons = array_map(fn ($e) => new CronExpression($e), $schedule);
        $at = Carbon::parse('2026-10-01 00:00:00');
        $end = Carbon::parse('2026-11-01 00:00:00');

        for (; $at < $end; $at->addMinutes($grid)) {
            foreach ($crons as $label => $cron) {
                if ($cron->isDue($at)) {
                    $counts[$label]++;
                }
            }
        }

        return $counts;
    }

    public function test_default_interval_keeps_every_schedule_exactly_as_defined(): void
    {
        $this->assertSame(1, (int) config('app.scheduler_cron_interval_minutes'));

        $schedule = $this->registeredSchedule(1);

        $this->assertSame('55 * * * *', $this->digestExpression($schedule));
        $this->assertSame('* * * * *', $schedule['EMS: Process due event reminders']);
        $this->assertSame('55 23 * * *', $schedule['Generate Daily Analytics Report']);
        $this->assertSame('*/15 * * * *', $schedule['EMS: Expire abandoned Square checkouts']);
    }

    /** @return array<string, array{int}> */
    public static function coarseCronIntervals(): array
    {
        return ['10-minute cron' => [10], '15-minute cron' => [15]];
    }

    #[DataProvider('coarseCronIntervals')]
    public function test_unaligned_schedule_on_a_coarse_cron_leaves_tasks_that_never_run(int $grid): void
    {
        $schedule = $this->registeredSchedule(1);
        $never = array_keys(array_filter($this->runsOnGrid($schedule, $grid), fn ($n) => $n === 0));
        $expected = [
            'Generate Daily Analytics Report',
            'Generate Monthly Statistics & Leadership Report',
            'Generate Weekly Analytics/Engagement Report',
            $this->digestLabel($schedule),
        ];
        sort($never);
        sort($expected);

        $this->assertSame($expected, $never);
    }

    #[DataProvider('coarseCronIntervals')]
    public function test_aligned_schedule_runs_every_task_on_a_coarse_cron(int $grid): void
    {
        $schedule = $this->registeredSchedule($grid);
        $counts = $this->runsOnGrid($schedule, $grid);

        $this->assertSame([], array_keys(array_filter($counts, fn ($n) => $n === 0)), "every task runs on a {$grid}-minute cron");
        $this->assertSame(31 * 24, $counts[$this->digestLabel($schedule)], 'digest runs hourly');
        $this->assertSame(31, $counts['Generate Daily Analytics Report']);
        $this->assertSame(1, $counts['Generate Monthly Statistics & Leadership Report']);
        $this->assertSame(4, $counts['Generate Weekly Analytics/Engagement Report'], 'Sundays in October 2026');
        $this->assertSame(31 * 24 * (60 / $grid), $counts['EMS: Process due event reminders'], 'every-minute tasks run on every invocation');
    }

    public function test_minute_field_alignment_rules(): void
    {
        $this->assertSame('*/10', ScheduleCadence::alignMinuteField('*', 10));
        $this->assertSame('*/10', ScheduleCadence::alignMinuteField('*/5', 10));
        $this->assertSame('*/20', ScheduleCadence::alignMinuteField('*/15', 10));
        $this->assertSame('*/15', ScheduleCadence::alignMinuteField('*/15', 15));
        $this->assertSame('*/30', ScheduleCadence::alignMinuteField('*/30', 10));
        $this->assertSame('50', ScheduleCadence::alignMinuteField('55', 10));
        $this->assertSame('45', ScheduleCadence::alignMinuteField('59', 15));
        $this->assertSame('0', ScheduleCadence::alignMinuteField('0', 10));
        $this->assertSame('0,30', ScheduleCadence::alignMinuteField('5,35', 10));
        $this->assertSame('0-30/5', ScheduleCadence::alignMinuteField('0-30/5', 10), 'unrecognised forms are left alone');
    }

    public function test_interval_that_does_not_divide_an_hour_is_ignored(): void
    {
        $schedule = $this->registeredSchedule(7);

        $this->assertSame('55 * * * *', $this->digestExpression($schedule));
        $this->assertSame('* * * * *', $schedule['EMS: Process due event reminders']);
        $this->assertSame('59 23 * * 7', $schedule['Generate Weekly Analytics/Engagement Report']);
    }

    public function test_digest_moved_to_minute_50_still_sends_each_closed_period_once(): void
    {
        config(['features.volunteer_digest_enabled' => true]);
        Mail::fake();
        VolunteerRegistration::factory()->create(['created_at' => Carbon::parse('2026-10-13 10:00:00')]);
        VolunteerRegistration::factory()->create(['created_at' => Carbon::parse('2026-10-13 23:52:00')]);

        // 23:50 is before the 23:55 cutoff: the day's period is not closed yet.
        $this->travelTo(Carbon::parse('2026-10-13 23:50:00'));
        $this->artisan('volunteer:send-daily-digest')->assertExitCode(0);
        Mail::assertNothingSent();

        // 00:50 the next day picks up the closed period, including the 23:52 registration.
        $this->travelTo(Carbon::parse('2026-10-14 00:50:00'));
        $this->artisan('volunteer:send-daily-digest')->assertExitCode(0);
        Mail::assertSent(DailyVolunteerDigestMail::class, fn ($mail) => $mail->registrations->count() === 2);

        $this->travelTo(Carbon::parse('2026-10-14 01:50:00'));
        $this->artisan('volunteer:send-daily-digest')->assertExitCode(0);
        Mail::assertSent(DailyVolunteerDigestMail::class, 1);
    }

    /** @param  array<string, string>  $schedule */
    private function digestLabel(array $schedule): string
    {
        foreach (array_keys($schedule) as $label) {
            if (str_contains($label, 'volunteer:send-daily-digest')) {
                return $label;
            }
        }
        $this->fail('digest task is not scheduled');
    }

    /** @param  array<string, string>  $schedule */
    private function digestExpression(array $schedule): string
    {
        return $schedule[$this->digestLabel($schedule)];
    }
}
