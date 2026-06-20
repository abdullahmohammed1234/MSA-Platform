<?php

namespace App\Services\Queue;

use App\Models\JobLog;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class JobMonitoringService
{
    /**
     * Get paginated job execution logs with filters.
     */
    public function getJobLogs(array $filters = [], int $perPage = 15): array
    {
        $query = JobLog::query();

        if (!empty($filters['queue'])) {
            $query->where('queue', $filters['queue']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['job_name'])) {
            $query->where('job_name', 'like', '%' . $filters['job_name'] . '%');
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
        ];
    }

    /**
     * Get queue health metrics.
     */
    public function getQueueHealthMetrics(): array
    {
        $cutoff24h = Carbon::now()->subHours(24);

        // 1. Total jobs in the last 24h
        $total24h = JobLog::where('created_at', '>=', $cutoff24h)->count();
        $completed24h = JobLog::where('created_at', '>=', $cutoff24h)->where('status', 'completed')->count();
        $failed24h = JobLog::where('created_at', '>=', $cutoff24h)->where('status', 'failed')->count();

        // 2. Average duration in the last 24h
        $avgDuration = JobLog::where('created_at', '>=', $cutoff24h)
            ->where('status', 'completed')
            ->avg('duration') ?? 0;

        // 3. Queue-specific metrics
        $queues = ['high', 'default', 'low'];
        $queueMetrics = [];

        foreach ($queues as $q) {
            $qTotal = JobLog::where('queue', $q)->where('created_at', '>=', $cutoff24h)->count();
            $qFailed = JobLog::where('queue', $q)->where('status', 'failed')->where('created_at', '>=', $cutoff24h)->count();
            $qAvgDuration = JobLog::where('queue', $q)->where('status', 'completed')->where('created_at', '>=', $cutoff24h)->avg('duration') ?? 0;

            $queueMetrics[$q] = [
                'total_processed' => $qTotal,
                'failed_count' => $qFailed,
                'avg_duration' => round($qAvgDuration, 3),
                'failure_rate' => $qTotal > 0 ? round(($qFailed / $qTotal) * 100, 2) : 0,
            ];
        }

        // 4. Job processing count over time (hourly for last 24 hours)
        $hourlyThroughput = DB::table('job_logs')
            ->select(
                DB::raw("strftime('%Y-%m-%d %H:00:00', created_at) as hour"), // For SQLite fallback
                DB::raw("COUNT(*) as total_jobs"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_jobs")
            )
            ->where('created_at', '>=', $cutoff24h)
            ->groupBy('hour')
            ->orderBy('hour', 'asc')
            ->get();

        // Check if database driver is mysql to correct date expression
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            $hourlyThroughput = DB::table('job_logs')
                ->select(
                    DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour"),
                    DB::raw("COUNT(*) as total_jobs"),
                    DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_jobs")
                )
                ->where('created_at', '>=', $cutoff24h)
                ->groupBy('hour')
                ->orderBy('hour', 'asc')
                ->get();
        }

        return [
            'total_jobs_24h' => $total24h,
            'completed_jobs_24h' => $completed24h,
            'failed_jobs_24h' => $failed24h,
            'avg_duration_24h' => round($avgDuration, 3),
            'success_rate_24h' => $total24h > 0 ? round(($completed24h / $total24h) * 100, 2) : 100,
            'queue_breakdown' => $queueMetrics,
            'hourly_throughput' => $hourlyThroughput,
        ];
    }
}
