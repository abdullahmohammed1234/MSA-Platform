<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\PerformanceMetric;
use App\Models\JobLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PerformanceController extends Controller
{
    /**
     * Get performance metrics for the dashboard.
     */
    public function getStats(Request $request): JsonResponse
    {
        if (!$request->user() || !$request->user()->hasPermission('view_queue_status')) {
            return response()->json(['message' => 'Unauthorized. Required permission: view_queue_status'], 403);
        }

        // 1. API Latency & Metrics (Last 50 requests)
        $recentMetrics = PerformanceMetric::orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn($m) => [
                'id' => $m->id,
                'url' => $m->url,
                'method' => $m->method,
                'duration_ms' => $m->duration_ms,
                'db_queries_count' => $m->db_queries_count,
                'db_queries_time_ms' => $m->db_queries_time_ms,
                'memory_mb' => $m->memory_mb,
                'created_at' => $m->created_at->format('H:i:s'),
            ])
            ->reverse()
            ->values();

        // 2. Averages
        $averages = PerformanceMetric::select(
            DB::raw('AVG(duration_ms) as avg_duration'),
            DB::raw('AVG(db_queries_count) as avg_queries'),
            DB::raw('AVG(db_queries_time_ms) as avg_queries_time'),
            DB::raw('AVG(memory_mb) as avg_memory'),
            DB::raw('COUNT(*) as total_requests')
        )->first();

        // 3. Cache Hit Ratio (calculated from cached URL hits vs total hits in last 24 hours)
        $cachedUrls = [
            '/api/v1/website/homepage',
            '/api/v1/website/announcements',
            '/api/v1/website/events',
            '/api/v1/website/team',
            '/api/v1/website/resources',
            '/api/v1/website/sponsors',
            '/api/v1/academy/courses'
        ];

        $totalCachedHits = PerformanceMetric::whereIn('url', $cachedUrls)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        $totalHits24h = PerformanceMetric::where('created_at', '>=', now()->subHours(24))
            ->count();

        $cacheHitRate = $totalHits24h > 0 ? round(($totalCachedHits / $totalHits24h) * 100, 1) : 0;

        // 4. Queue Metrics
        $pendingJobs = DB::table('jobs')->count();
        $failedJobsCount = DB::table('failed_jobs')->count();
        
        $avgJobDuration = JobLog::where('status', 'completed')
            ->avg('duration') ?? 0;

        $completedJobsCount = JobLog::where('status', 'completed')->count();
        $failedJobsLogCount = JobLog::where('status', 'failed')->count();

        // 5. Slow Queries / Requests (Duration > 300ms or Queries > 15)
        $slowRequests = PerformanceMetric::where(function($q) {
                $q->where('duration_ms', '>', 300)
                  ->orWhere('db_queries_count', '>', 15);
            })
            ->orderBy('duration_ms', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($m) => [
                'url' => $m->url,
                'method' => $m->method,
                'duration_ms' => $m->duration_ms,
                'db_queries_count' => $m->db_queries_count,
                'db_queries_time_ms' => $m->db_queries_time_ms,
                'created_at' => $m->created_at->toDateTimeString(),
            ]);

        // 6. System Health
        $dbDriver = DB::connection()->getDriverName();
        $dbSize = 'Unknown';
        try {
            if ($dbDriver === 'sqlite') {
                $dbPath = config('database.connections.sqlite.database');
                if (file_exists($dbPath)) {
                    $dbSize = round(filesize($dbPath) / (1024 * 1024), 2) . ' MB';
                }
            } else if ($dbDriver === 'mysql') {
                $dbName = config('database.connections.mysql.database');
                $sizeQuery = DB::select("SELECT SUM(data_length + index_length) / 1024 / 1024 AS size FROM information_schema.TABLES WHERE table_schema = ?", [$dbName]);
                if (!empty($sizeQuery)) {
                    $dbSize = round($sizeQuery[0]->size, 2) . ' MB';
                }
            }
        } catch (\Exception $e) {
            $dbSize = 'Error: ' . $e->getMessage();
        }

        return response()->json([
            'success' => true,
            'recent_metrics' => $recentMetrics,
            'averages' => [
                'duration_ms' => round($averages->avg_duration ?? 0),
                'db_queries' => round($averages->avg_queries ?? 0, 1),
                'db_queries_time_ms' => round($averages->avg_queries_time ?? 0),
                'memory_mb' => round($averages->avg_memory ?? 0, 1),
                'total_requests' => $averages->total_requests ?? 0,
            ],
            'cache' => [
                'hit_rate' => $cacheHitRate,
                'cached_requests_count' => $totalCachedHits,
                'total_requests_24h' => $totalHits24h,
            ],
            'queues' => [
                'pending' => $pendingJobs,
                'failed' => $failedJobsCount,
                'avg_duration_s' => round($avgJobDuration, 1),
                'processed_total' => $completedJobsCount,
                'failed_total' => $failedJobsLogCount,
            ],
            'slow_requests' => $slowRequests,
            'system' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'db_driver' => $dbDriver,
                'db_size' => $dbSize,
                'os' => PHP_OS,
            ],
        ]);
    }
}
