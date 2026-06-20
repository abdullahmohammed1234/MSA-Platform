<?php

namespace App\Jobs\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsSession;
use App\Models\AnalyticsMetric;
use App\Models\Enrollment;
use App\Models\CertificateAward;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Throwable;

class AggregateAnalyticsMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 0;

    protected ?Carbon $date;

    /**
     * Create a new job instance.
     */
    public function __construct(?Carbon $date = null)
    {
        $this->date = $date ?? Carbon::today();
        $this->queue = 'low';
    }

    /**
     * Timeout safeguard: retry up to 24 hours.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(24);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startOfDay = $this->date->copy()->startOfDay();
        $endOfDay = $this->date->copy()->endOfDay();

        Log::info("Running analytics metrics aggregation for date: " . $startOfDay->toDateString());

        // 1. Unique Visitors (Daily)
        $uniqueVisitors = AnalyticsSession::whereBetween('started_at', [$startOfDay, $endOfDay])
            ->count();
        $this->saveMetric('website_visitors_unique_daily', $uniqueVisitors, 'daily', $startOfDay);

        // 2. Page Views (Daily)
        $pageViews = AnalyticsEvent::where('event_name', 'page_view')
            ->whereBetween('occurred_at', [$startOfDay, $endOfDay])
            ->count();
        $this->saveMetric('website_page_views_daily', $pageViews, 'daily', $startOfDay);

        // 3. Active Learners (Daily, Weekly, Monthly)
        $dailyActiveLearners = AnalyticsEvent::whereIn('event_name', ['lesson_completed', 'quiz_submitted', 'course_completed', 'course_enrolled'])
            ->whereBetween('occurred_at', [$startOfDay, $endOfDay])
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count();
        $this->saveMetric('academy_active_learners_daily', $dailyActiveLearners, 'daily', $startOfDay);

        $weeklyStart = $this->date->copy()->subDays(6)->startOfDay();
        $weeklyActiveLearners = AnalyticsEvent::whereIn('event_name', ['lesson_completed', 'quiz_submitted', 'course_completed', 'course_enrolled'])
            ->whereBetween('occurred_at', [$weeklyStart, $endOfDay])
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count();
        $this->saveMetric('academy_active_learners_weekly', $weeklyActiveLearners, 'weekly', $startOfDay);

        $monthlyStart = $this->date->copy()->subDays(29)->startOfDay();
        $monthlyActiveLearners = AnalyticsEvent::whereIn('event_name', ['lesson_completed', 'quiz_submitted', 'course_completed', 'course_enrolled'])
            ->whereBetween('occurred_at', [$monthlyStart, $endOfDay])
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count();
        $this->saveMetric('academy_active_learners_monthly', $monthlyActiveLearners, 'monthly', $startOfDay);

        // 4. Course Completion Rate
        $totalEnrollments = Enrollment::count();
        $completedEnrollments = Enrollment::where('status', 'completed')->count();
        $completionRate = $totalEnrollments > 0 ? ($completedEnrollments / $totalEnrollments) * 100 : 0;
        $this->saveMetric('academy_course_completion_rate', $completionRate, 'overall', $startOfDay);

        // 5. Total Certificates Issued
        $certificatesIssued = CertificateAward::count();
        $this->saveMetric('academy_certificates_issued', $certificatesIssued, 'overall', $startOfDay);

        // 6. Event Registration Conversion Rate
        $eventViews = AnalyticsEvent::where('event_name', 'event_view')
            ->whereBetween('occurred_at', [$startOfDay, $endOfDay])
            ->count();
        $eventRegistrations = AnalyticsEvent::where('event_name', 'event_registered')
            ->whereBetween('occurred_at', [$startOfDay, $endOfDay])
            ->count();
        $conversionRate = $eventViews > 0 ? ($eventRegistrations / $eventViews) * 100 : 0;
        $this->saveMetric('events_registrations_rate', $conversionRate, 'daily', $startOfDay);

        // 7. Clear Caches to force refresh next time
        $this->clearAnalyticsCaches();

        Log::info("Analytics metrics aggregation completed and caches flushed.");
    }

    /**
     * Save/update aggregated metrics in DB.
     */
    private function saveMetric(string $key, float $value, string $period, Carbon $recordedAt): void
    {
        AnalyticsMetric::updateOrCreate(
            [
                'metric_key' => $key,
                'period' => $period,
                'recorded_at' => $recordedAt->toDateTimeString(),
            ],
            [
                'metric_value' => $value,
            ]
        );
    }

    /**
     * Flush cached analytics responses.
     */
    private function clearAnalyticsCaches(): void
    {
        // Forget common period ranges (e.g. 7 days, 30 days)
        $ranges = ['7', '30', '90', '365'];
        foreach ($ranges as $r) {
            Cache::forget("analytics_overview_{$r}");
            Cache::forget("analytics_website_{$r}");
            Cache::forget("analytics_academy_{$r}");
            Cache::forget("analytics_events_{$r}");
        }
        
        // General caches
        Cache::forget("analytics_kpis");
        Cache::forget("analytics_recent_activity");
    }

    /**
     * Handle job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("Failed to aggregate analytics metrics: " . $exception->getMessage());
    }
}
