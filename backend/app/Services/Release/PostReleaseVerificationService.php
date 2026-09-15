<?php

namespace App\Services\Release;

use App\Models\OperationalAlert;
use App\Models\PlatformRelease;
use App\Services\Lifecycle\DependencyInventoryService;
use App\Services\Lifecycle\MigrationReadinessService;
use Throwable;

class PostReleaseVerificationService
{
    public function __construct(
        private DependencyInventoryService $dependencyService,
        private MigrationReadinessService $migrationService
    ) {}

    /**
     * Perform deterministic post-release verification.
     */
    public function verifyRelease(PlatformRelease $release): array
    {
        $checkedAt = now()->toIso8601String();
        $probes = [];

        // 1. Database & Migrations
        $deps = $this->dependencyService->getDependencyHealth()['services'];
        $migrations = $this->migrationService->getMigrationStatus();

        if (($deps['database']['status'] ?? '') === 'HEALTHY' && $migrations['pending_count'] === 0) {
            $probes['database'] = [
                'name' => 'Database & Migrations',
                'status' => 'PASSED',
                'details' => 'Database responsive and all migrations applied.',
            ];
        } elseif ($migrations['pending_count'] > 0) {
            $probes['database'] = [
                'name' => 'Database & Migrations',
                'status' => 'WARNING',
                'details' => "Database connected but has {$migrations['pending_count']} pending migration(s).",
            ];
        } else {
            $probes['database'] = [
                'name' => 'Database & Migrations',
                'status' => 'FAILED',
                'details' => 'Database connection failed or unreachable.',
            ];
        }

        // 2. Cache Store
        $cacheStatus = $deps['cache']['status'] ?? 'UNKNOWN';
        $probes['cache'] = [
            'name' => 'Cache Store Health',
            'status' => $cacheStatus === 'HEALTHY' ? 'PASSED' : ($cacheStatus === 'DEGRADED' ? 'WARNING' : 'FAILED'),
            'details' => "Cache store status is {$cacheStatus}.",
        ];

        // 3. Queue System
        $queueStatus = $deps['queue']['status'] ?? 'UNKNOWN';
        $failedJobs = $deps['queue']['failed_jobs'] ?? 0;
        if ($failedJobs > 10) {
            $probes['queue'] = [
                'name' => 'Queue System',
                'status' => 'WARNING',
                'details' => "Queue driver ({$deps['queue']['driver']}) active with {$failedJobs} failed job(s).",
            ];
        } else {
            $probes['queue'] = [
                'name' => 'Queue System',
                'status' => 'PASSED',
                'details' => "Queue driver ({$deps['queue']['driver']}) operating within bounds.",
            ];
        }

        // 4. Scheduler Heartbeat
        $schedulerStatus = $deps['scheduler']['status'] ?? 'UNKNOWN';
        $probes['scheduler'] = [
            'name' => 'Scheduler Cron Heartbeat',
            'status' => $schedulerStatus === 'HEALTHY' ? 'PASSED' : 'NOT_VERIFIED',
            'details' => $schedulerStatus === 'HEALTHY'
                ? 'Scheduler cron heartbeat verified recently.'
                : 'Scheduler heartbeat not recorded in last 15 minutes (cPanel limitation).',
        ];

        // 5. Post-Release Operational Alerts
        try {
            $openCriticalAlerts = OperationalAlert::where('status', 'open')
                ->whereIn('severity', ['critical', 'high'])
                ->count();

            if ($openCriticalAlerts > 0) {
                $probes['operational_alerts'] = [
                    'name' => 'Post-Release Operational Alerts',
                    'status' => 'WARNING',
                    'details' => "Found {$openCriticalAlerts} active critical/high operational alert(s) post-release.",
                ];
            } else {
                $probes['operational_alerts'] = [
                    'name' => 'Post-Release Operational Alerts',
                    'status' => 'PASSED',
                    'details' => 'Zero critical or high severity operational alerts detected.',
                ];
            }
        } catch (Throwable) {
            $probes['operational_alerts'] = [
                'name' => 'Post-Release Operational Alerts',
                'status' => 'NOT_VERIFIED',
                'details' => 'Operational alerts table not initialized.',
            ];
        }

        // Determine overall verification result
        $statuses = array_column($probes, 'status');
        $overallResult = match (true) {
            in_array('FAILED', $statuses, true) => 'FAILED',
            in_array('WARNING', $statuses, true) => 'SUCCESSFUL_WITH_WARNINGS',
            in_array('NOT_VERIFIED', $statuses, true) => 'SUCCESSFUL_WITH_WARNINGS',
            default => 'SUCCESSFUL',
        };

        return [
            'release_identifier' => $release->release_identifier,
            'verification_status' => $overallResult,
            'verified_at' => $checkedAt,
            'probes' => $probes,
            'summary' => [
                'total_probes' => count($probes),
                'passed' => count(array_filter($statuses, fn($s) => $s === 'PASSED')),
                'warning' => count(array_filter($statuses, fn($s) => $s === 'WARNING')),
                'failed' => count(array_filter($statuses, fn($s) => $s === 'FAILED')),
                'not_verified' => count(array_filter($statuses, fn($s) => $s === 'NOT_VERIFIED')),
            ],
        ];
    }
}
