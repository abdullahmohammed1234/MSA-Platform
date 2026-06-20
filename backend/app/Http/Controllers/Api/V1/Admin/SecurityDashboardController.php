<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SecurityEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SecurityDashboardController extends Controller
{
    /**
     * Fetch all aggregated security metrics, recent activity feeds, and health logs.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dashboard(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SecurityEvent::class);

        // 1. Aggregated Metrics (24h vs 7d)
        $metrics = [
            'failed_logins_24h' => SecurityEvent::where('event_type', 'failed_login')->where('created_at', '>=', now()->subDay())->count(),
            'failed_logins_7d' => SecurityEvent::where('event_type', 'failed_login')->where('created_at', '>=', now()->subDays(7))->count(),
            
            'rate_violations_24h' => SecurityEvent::where('event_type', 'rate_limit_violation')->where('created_at', '>=', now()->subDay())->count(),
            'rate_violations_7d' => SecurityEvent::where('event_type', 'rate_limit_violation')->where('created_at', '>=', now()->subDays(7))->count(),

            'total_security_events_24h' => SecurityEvent::where('created_at', '>=', now()->subDay())->count(),
            'total_security_events_7d' => SecurityEvent::where('created_at', '>=', now()->subDays(7))->count(),

            'total_audit_logs_24h' => AuditLog::where('created_at', '>=', now()->subDay())->count(),
            'total_audit_logs_7d' => AuditLog::where('created_at', '>=', now()->subDays(7))->count(),
        ];

        // 2. Recent security incident alerts
        $recentSecurityEvents = SecurityEvent::with('user:id,name,email')
            ->latest()
            ->limit(15)
            ->get();

        // 3. Recent audit trails
        $recentAuditLogs = AuditLog::with('user:id,name,email')
            ->latest()
            ->limit(15)
            ->get();

        // 4. System Health Checklist
        $dbStatus = 'Healthy';
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $dbStatus = 'Degraded';
        }

        $activeSessions = 0;
        try {
            $activeSessions = DB::table('sessions')->count();
        } catch (\Exception $e) {
            // Sessions table might not exist in testing / other session driver configs
            $activeSessions = 0;
        }

        $failedJobsCount = 0;
        try {
            $failedJobsCount = DB::table('failed_jobs')->count();
        } catch (\Exception $e) {
            $failedJobsCount = 0;
        }

        $systemHealth = [
            'db_status' => $dbStatus,
            'failed_jobs' => $failedJobsCount,
            'active_sessions' => $activeSessions,
            'app_debug' => env('APP_DEBUG', true) ? 'Warning: Enabled' : 'Secure: Disabled',
            'app_env' => env('APP_ENV', 'production'),
            'https_enforced' => env('FORCE_HTTPS', false) || $request->secure() ? 'Enabled' : 'Disabled',
        ];

        // 5. Chart dataset mapping (Mon-Sun coordinates)
        $chartData = $this->getChartDataset();

        return response()->json([
            'metrics' => $metrics,
            'recent_security_events' => $recentSecurityEvents,
            'recent_audit_logs' => $recentAuditLogs,
            'system_health' => $systemHealth,
            'chart_data' => $chartData,
        ]);
    }

    /**
     * Computes daily failed login and rate limit violation counts over past 7 days.
     *
     * @return array
     */
    protected function getChartDataset(): array
    {
        $labels = [];
        $failedLogins = [];
        $rateViolations = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $labels[] = $day->format('D'); // e.g., "Mon", "Tue"
            
            // Build bounds for the day to support database agnostic date queries
            $startOfDay = $day->copy()->startOfDay();
            $endOfDay = $day->copy()->endOfDay();

            $failedLogins[] = SecurityEvent::where('event_type', 'failed_login')
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->count();

            $rateViolations[] = SecurityEvent::where('event_type', 'rate_limit_violation')
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->count();
        }

        return [
            'labels' => $labels,
            'failed_logins' => $failedLogins,
            'rate_violations' => $rateViolations,
        ];
    }
}
