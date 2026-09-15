<?php

namespace App\Services\Lifecycle;

use Throwable;

class DeploymentReadinessService
{
    public function __construct(
        private EnvironmentInventoryService $envService,
        private MigrationReadinessService $migrationService,
        private DependencyInventoryService $dependencyService,
        private ConfigurationDriftService $driftService
    ) {}

    /**
     * Evaluate complete platform deployment readiness state.
     */
    public function evaluateReadiness(): array
    {
        $checks = [];

        // 1. Application & Security Check
        $env = $this->envService->getEnvironmentInventory();
        $appKey = config('app.key');
        $isProd = $env['environment'] === 'production';
        $isDebug = $env['debug_mode'];

        if (empty($appKey)) {
            $checks[] = [
                'domain' => 'Security',
                'key' => 'app_key_present',
                'title' => 'Application Encryption Key',
                'status' => 'FAILED',
                'details' => 'APP_KEY is missing or empty in environment.',
                'remediation' => 'Run php artisan key:generate or set APP_KEY.',
            ];
        } elseif ($isProd && $isDebug) {
            $checks[] = [
                'domain' => 'Security',
                'key' => 'production_debug_mode',
                'title' => 'Production Debug Mode',
                'status' => 'WARNING',
                'details' => 'APP_DEBUG is enabled in production environment.',
                'remediation' => 'Set APP_DEBUG=false in environment configuration.',
            ];
        } else {
            $checks[] = [
                'domain' => 'Security',
                'key' => 'security_config',
                'title' => 'Security & Encryption Configuration',
                'status' => 'PASSED',
                'details' => 'APP_KEY present and APP_DEBUG safely configured.',
                'remediation' => null,
            ];
        }

        // 2. Database & Migration Check
        $migrations = $this->migrationService->getMigrationStatus();
        $deps = $this->dependencyService->getDependencyHealth()['services'];

        $dbStatus = $deps['database']['status'] ?? 'UNKNOWN';
        if ($dbStatus !== 'HEALTHY') {
            $checks[] = [
                'domain' => 'Database',
                'key' => 'database_connectivity',
                'title' => 'Database Connectivity',
                'status' => 'FAILED',
                'details' => 'Database primary connection is unreachable or failed.',
                'remediation' => 'Verify database host, credentials, and service availability.',
            ];
        } elseif ($migrations['pending_count'] > 0) {
            $checks[] = [
                'domain' => 'Database',
                'key' => 'pending_migrations',
                'title' => 'Database Migrations',
                'status' => 'WARNING',
                'details' => "Database connected but has {$migrations['pending_count']} pending migration(s).",
                'remediation' => 'Run php artisan migrate before completing deployment.',
            ];
        } else {
            $checks[] = [
                'domain' => 'Database',
                'key' => 'database_readiness',
                'title' => 'Database & Migrations',
                'status' => 'PASSED',
                'details' => 'Database connection responsive and all migrations applied.',
                'remediation' => null,
            ];
        }

        // 3. Cache System Check
        $cacheStatus = $deps['cache']['status'] ?? 'UNKNOWN';
        if ($cacheStatus === 'HEALTHY') {
            $checks[] = [
                'domain' => 'Cache',
                'key' => 'cache_system',
                'title' => 'Cache Store Health',
                'status' => 'PASSED',
                'details' => 'Cache write/read operations verified.',
                'remediation' => null,
            ];
        } else {
            $checks[] = [
                'domain' => 'Cache',
                'key' => 'cache_system',
                'title' => 'Cache Store Health',
                'status' => 'WARNING',
                'details' => "Cache store status is {$cacheStatus}.",
                'remediation' => 'Check cache driver permissions and store accessibility.',
            ];
        }

        // 4. Queue System Check
        $queueStatus = $deps['queue']['status'] ?? 'UNKNOWN';
        $failedJobs = $deps['queue']['failed_jobs'] ?? 0;
        if ($failedJobs > 10) {
            $checks[] = [
                'domain' => 'Queue',
                'key' => 'queue_system',
                'title' => 'Queue Worker & Backlog',
                'status' => 'WARNING',
                'details' => "Queue has {$failedJobs} failed job(s) in backlog.",
                'remediation' => 'Clear or retry failed jobs via queue management controller.',
            ];
        } else {
            $checks[] = [
                'domain' => 'Queue',
                'key' => 'queue_system',
                'title' => 'Queue System Configuration',
                'status' => 'PASSED',
                'details' => "Queue driver ({$env['queue_driver']}) configured cleanly.",
                'remediation' => null,
            ];
        }

        // 5. Scheduler Check
        $schedulerStatus = $deps['scheduler']['status'] ?? 'UNKNOWN';
        if ($schedulerStatus === 'HEALTHY') {
            $checks[] = [
                'domain' => 'Scheduler',
                'key' => 'scheduler_heartbeat',
                'title' => 'Scheduler Cron Execution',
                'status' => 'PASSED',
                'details' => 'Scheduler cron heartbeat verified recently.',
                'remediation' => null,
            ];
        } else {
            $checks[] = [
                'domain' => 'Scheduler',
                'key' => 'scheduler_heartbeat',
                'title' => 'Scheduler Cron Execution',
                'status' => 'UNKNOWN',
                'details' => 'Scheduler cron heartbeat not verified in last 15 minutes.',
                'remediation' => 'Ensure cPanel cron job executing "php artisan schedule:run" is active.',
            ];
        }

        // 6. Storage Check
        $storageStatus = $deps['storage']['status'] ?? 'UNKNOWN';
        if ($storageStatus === 'HEALTHY') {
            $checks[] = [
                'domain' => 'Storage',
                'key' => 'filesystem_storage',
                'title' => 'Filesystem Storage Permissions',
                'status' => 'PASSED',
                'details' => 'Storage paths are writeable.',
                'remediation' => null,
            ];
        } else {
            $checks[] = [
                'domain' => 'Storage',
                'key' => 'filesystem_storage',
                'title' => 'Filesystem Storage Permissions',
                'status' => 'FAILED',
                'details' => 'Storage directory is not writeable by web worker.',
                'remediation' => 'Verify directory permissions on storage/ and bootstrap/cache/.',
            ];
        }

        // Aggregate overall readiness status
        $failedCount = count(array_filter($checks, fn($c) => $c['status'] === 'FAILED'));
        $warningCount = count(array_filter($checks, fn($c) => $c['status'] === 'WARNING'));
        $unknownCount = count(array_filter($checks, fn($c) => $c['status'] === 'UNKNOWN'));
        $passedCount = count(array_filter($checks, fn($c) => $c['status'] === 'PASSED'));

        $overallReadiness = match (true) {
            $failedCount > 0 => 'NOT_READY',
            $warningCount > 0 => 'READY_WITH_WARNINGS',
            $unknownCount > 0 && $passedCount === 0 => 'UNKNOWN',
            default => 'READY',
        };

        return [
            'readiness_status' => $overallReadiness,
            'summary' => [
                'total_checks' => count($checks),
                'passed' => $passedCount,
                'warning' => $warningCount,
                'failed' => $failedCount,
                'unknown' => $unknownCount,
            ],
            'checks' => $checks,
            'evaluated_at' => now()->toIso8601String(),
        ];
    }
}
