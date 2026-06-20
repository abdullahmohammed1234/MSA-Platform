<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\PerformanceMetric;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitoringMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip monitoring for health check route or debug requests
        if ($request->is('up') || $request->is('_debugbar*')) {
            return $next($request);
        }

        // Avoid adding DB overhead to every local request
        if (app()->environment('local', 'testing')) {
            return $next($request);
        }

        $startTime = microtime(true);
        $queryCount = 0;
        $queryTimeMs = 0;

        // Listen for database queries
        DB::listen(function ($query) use (&$queryCount, &$queryTimeMs) {
            $queryCount++;
            $queryTimeMs += $query->time; // time in milliseconds
        });

        $response = $next($request);

        $durationMs = round((microtime(true) - $startTime) * 1000);
        $memoryMb = round(memory_get_peak_usage(true) / (1024 * 1024), 2);
        
        try {
            $userId = $request->user()?->id;

            PerformanceMetric::create([
                'url' => $request->getRequestUri(),
                'method' => $request->getMethod(),
                'duration_ms' => $durationMs,
                'db_queries_count' => $queryCount,
                'db_queries_time_ms' => round($queryTimeMs),
                'memory_mb' => $memoryMb,
                'user_id' => $userId,
                'created_at' => now(),
            ]);

            // Log warnings for slow requests (e.g. > 500ms or > 20 DB queries)
            if ($durationMs > 500 || $queryCount > 20) {
                Log::warning("Slow request detected: {$request->getMethod()} {$request->getRequestUri()} - Duration: {$durationMs}ms, DB Queries: {$queryCount} ({$queryTimeMs}ms), Memory: {$memoryMb}MB");
            }
        } catch (\Exception $e) {
            Log::error("Failed to save performance metrics: " . $e->getMessage());
        }

        return $response;
    }
}
