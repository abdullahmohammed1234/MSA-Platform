<?php

namespace Tests\Feature\Phase28;

use App\Jobs\Analytics\GenerateScheduledReportsJob;
use App\Jobs\Reports\GenerateDailyReportJob;
use App\Jobs\Reports\GenerateMonthlyReportJob;
use App\Jobs\Reports\GenerateWeeklyReportJob;
use App\Models\AnalyticsMetric;
use App\Models\AnalyticsReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Report jobs are scheduled shortly before midnight UTC but run on the next worker
 * pass, which can be after midnight. The report must describe the day it was scheduled for.
 */
class ScheduledReportPeriodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    protected function tearDown(): void
    {
        foreach (AnalyticsReport::query()->pluck('file_path') as $path) {
            @unlink(storage_path('app/'.$path));
        }

        parent::tearDown();
    }

    /** @return array<string, array{class-string, string, string, string}> */
    public static function reports(): array
    {
        return [
            'daily' => [GenerateDailyReportJob::class, 'daily', '2026-10-14 23:50:00', '2026-10-15 00:05:00'],
            'weekly (Sunday)' => [GenerateWeeklyReportJob::class, 'weekly', '2026-10-18 23:50:00', '2026-10-19 00:12:00'],
            'monthly (February)' => [GenerateMonthlyReportJob::class, 'monthly', '2027-02-28 23:50:00', '2027-03-01 00:05:00'],
            'monthly (December)' => [GenerateMonthlyReportJob::class, 'monthly', '2026-12-31 23:50:00', '2027-01-01 00:15:00'],
        ];
    }

    #[DataProvider('reports')]
    public function test_report_picked_up_after_midnight_describes_the_scheduled_day(string $class, string $period, string $scheduledAt, string $workerAt): void
    {
        $this->travelTo(Carbon::parse($scheduledAt));
        $payload = serialize(new $class());

        $this->travelTo(Carbon::parse($workerAt));
        unserialize($payload)->handle();

        $expectedDay = Carbon::parse($scheduledAt)->toDateString();
        $report = AnalyticsReport::query()->sole();
        $this->assertSame($period, $report->type);
        $this->assertSame($expectedDay, $report->filters['date']);
        $this->assertStringEndsWith($expectedDay, $report->title);
    }

    public function test_metric_window_ends_on_the_scheduled_day(): void
    {
        foreach (['2026-10-14' => 42, '2026-10-15' => 7] as $day => $value) {
            AnalyticsMetric::create([
                'metric_key' => 'website_page_views_daily', 'metric_value' => $value,
                'period' => 'daily', 'recorded_at' => "{$day} 00:00:00",
            ]);
        }

        $this->travelTo(Carbon::parse('2026-10-14 23:50:00'));
        $job = unserialize(serialize(new GenerateScheduledReportsJob('daily')));
        $this->travelTo(Carbon::parse('2026-10-15 00:05:00'));

        $metrics = (new ReflectionMethod($job, 'gatherMetrics'))->invoke($job);

        $this->assertEquals(42, $metrics['website_page_views_daily']['total']);
    }

    public function test_payload_queued_before_this_change_still_runs(): void
    {
        // Jobs serialised by the previous release carry no reference date.
        $this->travelTo(Carbon::parse('2026-10-15 09:00:00'));
        $legacy = (new \ReflectionClass(GenerateDailyReportJob::class))->newInstanceWithoutConstructor();

        $legacy->handle();

        $this->assertSame('2026-10-15', AnalyticsReport::query()->sole()->filters['date']);
    }
}
