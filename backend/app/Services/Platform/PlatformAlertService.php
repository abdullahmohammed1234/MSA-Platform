<?php

namespace App\Services\Platform;

use App\Platform\Enums\AlertSeverity;
use App\Platform\Enums\AlertStatus;
use App\Platform\Models\PlatformAlert;
use App\Services\Systems\SystemsControlPlaneService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlatformAlertService
{
    private const CRON_HEARTBEAT_CACHE_KEY = 'platform.cron.last_heartbeat';
    private const ALERT_COOLDOWN_MINUTES = 30;

    public function __construct(
        private SystemsControlPlaneService $systems
    ) {}

    /**
     * Record cPanel cron heartbeat execution timestamp.
     */
    public function recordCronHeartbeat(): void
    {
        Cache::put(self::CRON_HEARTBEAT_CACHE_KEY, Carbon::now()->toIso8601String(), 86400);
    }

    /**
     * Evaluate system state, detect operational anomalies, and trigger deduplicated alerts.
     */
    public function evaluateAndTriggerAlerts(): array
    {
        $this->recordCronHeartbeat();
        $generatedAlerts = [];

        // 1. Check for failed jobs accumulation
        if (Schema::hasTable('failed_jobs')) {
            $failedJobsCount = DB::table('failed_jobs')->count();
            if ($failedJobsCount > 0) {
                $alert = $this->createDeduplicatedAlert(
                    key: 'queue.failed_jobs_accumulated',
                    application: 'queues',
                    severity: $failedJobsCount >= 10 ? AlertSeverity::CRITICAL : AlertSeverity::WARNING,
                    title: 'Background Failed Jobs Present',
                    message: "There are currently {$failedJobsCount} failed job(s) in the queue requiring administrator review.",
                    context: ['failed_jobs_count' => $failedJobsCount]
                );
                if ($alert) $generatedAlerts[] = $alert;
            }
        }

        // 2. Check cPanel cron heartbeat stale status (> 10 minutes)
        $lastHeartbeat = Cache::get(self::CRON_HEARTBEAT_CACHE_KEY);
        if ($lastHeartbeat) {
            $diffMinutes = Carbon::parse($lastHeartbeat)->diffInMinutes(Carbon::now());
            if ($diffMinutes > 10) {
                $alert = $this->createDeduplicatedAlert(
                    key: 'cron.heartbeat_stale',
                    application: 'system',
                    severity: AlertSeverity::CRITICAL,
                    title: 'cPanel Scheduled Cron Timeout',
                    message: "The cPanel cron scheduler heartbeat has not executed for {$diffMinutes} minutes.",
                    context: ['last_heartbeat' => $lastHeartbeat, 'diff_minutes' => $diffMinutes]
                );
                if ($alert) $generatedAlerts[] = $alert;
            }
        }

        // 3. Evaluate Application & Service Health Status
        $overview = $this->systems->overview(true);
        foreach ($overview['applications'] ?? [] as $app) {
            $status = $app['status'] ?? 'unknown';
            if (in_array($status, ['degraded', 'unavailable'], true)) {
                $alert = $this->createDeduplicatedAlert(
                    key: "app_health.{$app['id']}.{$status}",
                    application: $app['id'],
                    severity: $status === 'unavailable' ? AlertSeverity::CRITICAL : AlertSeverity::WARNING,
                    title: "Application Status: " . ucfirst($status),
                    message: "Application '{$app['name']}' status is {$status}. Reason: " . ($app['status_reason'] ?? 'N/A'),
                    context: ['app_id' => $app['id'], 'status' => $status, 'status_reason' => $app['status_reason'] ?? null]
                );
                if ($alert) $generatedAlerts[] = $alert;
            }
        }

        return $generatedAlerts;
    }

    /**
     * Helper for triggering alerts with alertKey and appKey.
     */
    public function triggerAlert(
        string $alertKey,
        string $appKey,
        AlertSeverity $severity,
        string $title,
        string $message,
        ?array $context = null
    ): PlatformAlert {
        $alertHash = md5($alertKey . ':' . $appKey);

        $recentAlert = PlatformAlert::where('alert_key', $alertHash)
            ->where('status', '!=', AlertStatus::RESOLVED)
            ->first();

        if ($recentAlert) {
            return $recentAlert;
        }

        return PlatformAlert::create([
            'alert_key' => $alertHash,
            'application' => $appKey,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'context' => $context,
            'status' => AlertStatus::NEW,
        ]);
    }

    /**
     * Create alert if no active alert with identical key exists within cooldown window.
     */
    public function createDeduplicatedAlert(
        string $key,
        string $application,
        AlertSeverity $severity,
        string $title,
        string $message,
        ?array $context = null
    ): ?PlatformAlert {
        $alertHash = md5($key . ':' . $application);

        // Check if an un-resolved alert with this key was created recently
        $recentAlert = PlatformAlert::where('alert_key', $alertHash)
            ->where('status', '!=', AlertStatus::RESOLVED)
            ->where('created_at', '>=', Carbon::now()->subMinutes(self::ALERT_COOLDOWN_MINUTES))
            ->first();

        if ($recentAlert) {
            return null; // Suppress duplicate alert within cooldown period
        }

        return PlatformAlert::create([
            'alert_key' => $alertHash,
            'application' => $application,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'context' => $context,
            'status' => AlertStatus::NEW,
        ]);
    }

    /**
     * Search and paginate alerts.
     */
    public function getAlerts(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = PlatformAlert::with(['acknowledgedBy:id,name,email', 'resolvedBy:id,name,email']);

        if (! empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $query->whereIn('status', [AlertStatus::NEW->value, AlertStatus::ACKNOWLEDGED->value]);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (! empty($filters['application'])) {
            $query->where('application', $filters['application']);
        }

        $perPageClamped = min(100, max(5, $perPage));

        return $query->orderBy('created_at', 'desc')->paginate($perPageClamped);
    }

    /**
     * Acknowledge alert.
     */
    public function acknowledgeAlert(int|string $id, int $userId): ?PlatformAlert
    {
        $alert = is_numeric($id)
            ? PlatformAlert::find((int) $id)
            : PlatformAlert::where('uuid', $id)->first();

        if (! $alert || $alert->status === AlertStatus::RESOLVED) {
            return null;
        }

        $alert->update([
            'status' => AlertStatus::ACKNOWLEDGED,
            'acknowledged_by' => $userId,
            'acknowledged_at' => Carbon::now(),
        ]);

        return $alert->fresh();
    }

    /**
     * Resolve alert.
     */
    public function resolveAlert(int|string $id, int $userId): ?PlatformAlert
    {
        $alert = is_numeric($id)
            ? PlatformAlert::find((int) $id)
            : PlatformAlert::where('uuid', $id)->first();

        if (! $alert) {
            return null;
        }

        $alert->update([
            'status' => AlertStatus::RESOLVED,
            'resolved_by' => $userId,
            'resolved_at' => Carbon::now(),
        ]);

        return $alert->fresh();
    }
}
