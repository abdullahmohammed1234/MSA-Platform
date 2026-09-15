<?php

namespace App\Services\Governance;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ContinuityReadinessService
{
    private const CRON_HEARTBEAT_CACHE_KEY = 'platform.cron.last_heartbeat';

    /**
     * Evaluate continuity, infrastructure readiness, and backup verification signals.
     */
    public function getContinuityReadiness(): array
    {
        $database = $this->evaluateDatabaseHealth();
        $cache = $this->evaluateCacheHealth();
        $queue = $this->evaluateQueueHealth();
        $scheduler = $this->evaluateSchedulerHealth();
        $backup = $this->evaluateBackupVerificationStatus();

        // Calculate overall continuity status
        $statuses = [$database['status'], $cache['status'], $queue['status'], $scheduler['status']];

        $overallStatus = match (true) {
            in_array('FAILED', $statuses, true) => 'DEGRADED',
            in_array('DEGRADED', $statuses, true) => 'DEGRADED',
            in_array('UNKNOWN', $statuses, true) => 'HEALTHY_WITH_UNVERIFIED_SIGNALS',
            default => 'HEALTHY',
        };

        return [
            'overall_status' => $overallStatus,
            'evaluated_at' => now()->toIso8601String(),
            'probes' => [
                'database' => $database,
                'cache' => $cache,
                'queue' => $queue,
                'scheduler' => $scheduler,
                'backup_verification' => $backup,
            ],
            'continuity_summary' => $this->buildContinuitySummary($overallStatus, $backup),
        ];
    }

    private function evaluateDatabaseHealth(): array
    {
        try {
            DB::connection()->getPdo();
            return [
                'status' => 'HEALTHY',
                'name' => 'Database Primary Connection',
                'details' => 'PDO connection active and responsive.',
                'last_checked_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'FAILED',
                'name' => 'Database Primary Connection',
                'details' => 'Database connection failed: ' . $e->getMessage(),
                'last_checked_at' => now()->toIso8601String(),
            ];
        }
    }

    private function evaluateCacheHealth(): array
    {
        try {
            $key = 'platform.governance.cache_test_' . uniqid();
            Cache::put($key, 'test_val', 10);
            $val = Cache::get($key);
            Cache::forget($key);

            if ($val === 'test_val') {
                return [
                    'status' => 'HEALTHY',
                    'name' => 'Cache Store (Redis / File)',
                    'details' => 'Cache write/read verified successfully.',
                    'last_checked_at' => now()->toIso8601String(),
                ];
            }

            return [
                'status' => 'DEGRADED',
                'name' => 'Cache Store (Redis / File)',
                'details' => 'Cache read back unexpected value.',
                'last_checked_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'FAILED',
                'name' => 'Cache Store (Redis / File)',
                'details' => 'Cache access exception: ' . $e->getMessage(),
                'last_checked_at' => now()->toIso8601String(),
            ];
        }
    }

    private function evaluateQueueHealth(): array
    {
        try {
            $failedJobsCount = DB::table('failed_jobs')->count();

            if ($failedJobsCount > 10) {
                return [
                    'status' => 'DEGRADED',
                    'name' => 'Queue Backlog & Worker System',
                    'details' => "Elevated failed jobs count ({$failedJobsCount} failed jobs pending review).",
                    'failed_jobs_count' => $failedJobsCount,
                    'last_checked_at' => now()->toIso8601String(),
                ];
            }

            return [
                'status' => 'HEALTHY',
                'name' => 'Queue Backlog & Worker System',
                'details' => "Queue operating within healthy thresholds ({$failedJobsCount} failed jobs).",
                'failed_jobs_count' => $failedJobsCount,
                'last_checked_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'UNKNOWN',
                'name' => 'Queue Backlog & Worker System',
                'details' => 'Unable to query failed_jobs table: ' . $e->getMessage(),
                'failed_jobs_count' => 0,
                'last_checked_at' => now()->toIso8601String(),
            ];
        }
    }

    private function evaluateSchedulerHealth(): array
    {
        $lastHeartbeat = Cache::get(self::CRON_HEARTBEAT_CACHE_KEY);

        if (! $lastHeartbeat) {
            return [
                'status' => 'UNKNOWN',
                'name' => 'Scheduled Task Runner (Cron)',
                'details' => 'No cron heartbeat detected in cache (Heartbeat not yet recorded).',
                'last_heartbeat_at' => null,
                'last_checked_at' => now()->toIso8601String(),
            ];
        }

        $heartbeatTime = Carbon::parse($lastHeartbeat);
        $minutesAgo = (int) $heartbeatTime->diffInMinutes(now());

        if ($minutesAgo > 15) {
            return [
                'status' => 'DEGRADED',
                'name' => 'Scheduled Task Runner (Cron)',
                'details' => "Scheduler heartbeat stale (last run {$minutesAgo} minutes ago).",
                'last_heartbeat_at' => $heartbeatTime->toIso8601String(),
                'last_checked_at' => now()->toIso8601String(),
            ];
        }

        return [
            'status' => 'HEALTHY',
            'name' => 'Scheduled Task Runner (Cron)',
            'details' => "Scheduler active (last heartbeat {$minutesAgo}m ago).",
            'last_heartbeat_at' => $heartbeatTime->toIso8601String(),
            'last_checked_at' => now()->toIso8601String(),
        ];
    }

    private function evaluateBackupVerificationStatus(): array
    {
        return [
            'status' => 'NOT_VERIFIED',
            'verification_state' => 'UNKNOWN',
            'name' => 'Database & Media Asset Backup Verification',
            'details' => 'No automated backup verification agent detected in cPanel / shared-hosting runtime environment.',
            'recommendation' => 'Administrators must perform periodic manual verification of database dump files and uploaded media assets in the hosting control panel.',
            'is_automated' => false,
            'last_checked_at' => now()->toIso8601String(),
        ];
    }

    private function buildContinuitySummary(string $overallStatus, array $backup): string
    {
        if ($overallStatus === 'DEGRADED') {
            return 'Platform operational continuity is degraded due to infrastructure or queue warnings.';
        }

        return 'Core platform infrastructure is operational. Note: Automated backup verification is NOT_VERIFIED due to hosting environment constraints.';
    }
}
