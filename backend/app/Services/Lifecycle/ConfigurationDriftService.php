<?php

namespace App\Services\Lifecycle;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ConfigurationDriftService
{
    public function __construct(
        private MigrationReadinessService $migrationService
    ) {}

    /**
     * Run deterministic configuration drift detection rules.
     */
    public function detectConfigurationDrift(): array
    {
        $findings = [];

        // Rule 1: Application Encryption Key
        $appKey = config('app.key');
        if (empty($appKey)) {
            $findings[] = [
                'rule_key' => 'DRIFT_APP_KEY_MISSING',
                'category' => 'security',
                'severity' => 'critical',
                'detected' => true,
                'explanation' => 'Application encryption key (APP_KEY) is missing or empty in environment configuration.',
                'remediation' => 'Run "php artisan key:generate" or set APP_KEY in environment variables.',
                'verification_source' => 'config(app.key)',
            ];
        }

        // Rule 2: Debug Mode in Production
        $isProd = config('app.env') === 'production';
        $isDebug = (bool) config('app.debug', false);
        if ($isProd && $isDebug) {
            $findings[] = [
                'rule_key' => 'DRIFT_DEBUG_IN_PRODUCTION',
                'category' => 'security',
                'severity' => 'critical',
                'detected' => true,
                'explanation' => 'Application debug mode is enabled (APP_DEBUG=true) in a production environment.',
                'remediation' => 'Set APP_DEBUG=false in environment configuration to prevent sensitive stack trace disclosure.',
                'verification_source' => 'config(app.debug)',
            ];
        }

        // Rule 3: Sync Queue Driver in Production
        $queueDriver = config('queue.default', 'sync');
        if ($isProd && $queueDriver === 'sync') {
            $findings[] = [
                'rule_key' => 'DRIFT_SYNC_QUEUE_IN_PROD',
                'category' => 'queue',
                'severity' => 'warning',
                'detected' => true,
                'explanation' => 'Queue driver is set to "sync" in production, causing background tasks to block web requests.',
                'remediation' => 'Set QUEUE_CONNECTION=database or redis in environment configuration.',
                'verification_source' => 'config(queue.default)',
            ];
        }

        // Rule 4: Pending Database Migrations
        $migrationStatus = $this->migrationService->getMigrationStatus();
        if ($migrationStatus['pending_count'] > 0) {
            $findings[] = [
                'rule_key' => 'DRIFT_PENDING_MIGRATIONS',
                'category' => 'database',
                'severity' => 'warning',
                'detected' => true,
                'explanation' => "There are {$migrationStatus['pending_count']} pending database migration(s) that have not been executed.",
                'remediation' => 'Review and execute "php artisan migrate" during scheduled maintenance.',
                'verification_source' => 'MigrationRepository',
            ];
        }

        // Rule 5: Unverified Scheduler Cron Heartbeat
        $lastHeartbeat = Cache::get('platform.cron.last_heartbeat');
        if (!$lastHeartbeat) {
            $findings[] = [
                'rule_key' => 'DRIFT_UNVERIFIED_SCHEDULER',
                'category' => 'scheduler',
                'severity' => 'warning',
                'detected' => true,
                'explanation' => 'No active scheduler cron heartbeat recorded in application cache.',
                'remediation' => 'Verify cPanel cron job setup running "php artisan schedule:run" every minute.',
                'verification_source' => 'Cache::get(platform.cron.last_heartbeat)',
            ];
        }

        // Rule 6: Failed Queue Jobs Backlog
        try {
            if (Schema::hasTable('failed_jobs')) {
                $failedJobsCount = DB::table('failed_jobs')->count();
                if ($failedJobsCount > 0) {
                    $findings[] = [
                        'rule_key' => 'DRIFT_FAILED_JOBS_BACKLOG',
                        'category' => 'queue',
                        'severity' => $failedJobsCount > 10 ? 'critical' : 'warning',
                        'detected' => true,
                        'explanation' => "There are {$failedJobsCount} failed job(s) in the queue backlog.",
                        'remediation' => 'Inspect failed jobs via System Queue Controller or run "php artisan queue:retry all".',
                        'verification_source' => 'failed_jobs table count',
                    ];
                }
            }
        } catch (Throwable) {
            // Ignore database errors
        }

        // Rule 7: Mail Host Configuration
        $mailHost = config('mail.mailers.smtp.host');
        if (empty($mailHost) && config('mail.default') === 'smtp') {
            $findings[] = [
                'rule_key' => 'DRIFT_MAIL_HOST_UNCONFIGURED',
                'category' => 'mail',
                'severity' => 'info',
                'detected' => true,
                'explanation' => 'SMTP mail driver is selected but MAIL_HOST is not configured.',
                'remediation' => 'Set MAIL_HOST, MAIL_PORT, MAIL_USERNAME, and MAIL_PASSWORD in environment settings.',
                'verification_source' => 'config(mail.mailers.smtp.host)',
            ];
        }

        $criticalCount = count(array_filter($findings, fn($f) => $f['severity'] === 'critical'));
        $warningCount = count(array_filter($findings, fn($f) => $f['severity'] === 'warning'));
        $infoCount = count(array_filter($findings, fn($f) => $f['severity'] === 'info'));

        $driftStatus = match (true) {
            $criticalCount > 0 => 'CRITICAL_DRIFT',
            $warningCount > 0 => 'WARNING_DRIFT',
            $infoCount > 0 => 'MINOR_DRIFT',
            default => 'NO_DRIFT',
        };

        return [
            'status' => $driftStatus,
            'total_findings' => count($findings),
            'critical_count' => $criticalCount,
            'warning_count' => $warningCount,
            'info_count' => $infoCount,
            'findings' => $findings,
            'evaluated_at' => now()->toIso8601String(),
        ];
    }
}
