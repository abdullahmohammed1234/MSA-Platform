<?php

namespace App\Services\Operations\Rules;

use App\Models\OperationalAlert;
use App\Services\Operations\OperationalAlertService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlatformOperationalRules
{
    public function __construct(
        private OperationalAlertService $alertService
    ) {}

    public function detect(): int
    {
        $detected = 0;

        // 1. Failed Queue Jobs in system
        if (Schema::hasTable('failed_jobs')) {
            $failedJobsCount = DB::table('failed_jobs')->count();

            if ($failedJobsCount > 0) {
                $latestFailedJob = DB::table('failed_jobs')->latest('failed_at')->first();

                $this->alertService->upsertAlert([
                    'category' => 'platform',
                    'severity' => 'high',
                    'title' => "System Queue Job Failures ({$failedJobsCount} Failed)",
                    'description' => "The queue worker encountered {$failedJobsCount} failed background job(s). Latest failure: " . ($latestFailedJob->queue ?? 'default'),
                    'source_type' => 'System',
                    'source_id' => 'failed_jobs_summary',
                    'rule_key' => 'platform_failed_jobs_exceeded',
                    'action_url' => '/admin/operations',
                    'metadata' => [
                        'failed_jobs_count' => $failedJobsCount,
                        'latest_failed_queue' => $latestFailedJob->queue ?? null,
                        'latest_failed_at' => $latestFailedJob->failed_at ?? null,
                    ],
                ]);
                $detected++;
            }
        }

        // 2. Active Alert Escalation (> 72 hours unresolved open alert)
        if (Schema::hasTable('operational_alerts')) {
            $staleAlerts = OperationalAlert::where('status', 'open')
                ->where('first_detected_at', '<', now()->subHours(72))
                ->where('rule_key', '!=', 'platform_active_alert_escalation')
                ->get();

            foreach ($staleAlerts as $staleAlert) {
                $hoursOpen = (int) now()->diffInHours($staleAlert->first_detected_at);
                $this->alertService->upsertAlert([
                    'category' => 'platform',
                    'severity' => 'high',
                    'title' => "Escalated Unresolved Alert: {$staleAlert->title}",
                    'description' => "Alert #{$staleAlert->id} ('{$staleAlert->title}') has remained unresolved for {$hoursOpen} hours without action.",
                    'source_type' => 'OperationalAlert',
                    'source_id' => (string) $staleAlert->id,
                    'rule_key' => 'platform_active_alert_escalation',
                    'action_url' => '/admin/operations',
                    'metadata' => [
                        'target_alert_id' => $staleAlert->id,
                        'target_rule_key' => $staleAlert->rule_key,
                        'hours_unresolved' => $hoursOpen,
                    ],
                ]);
                $detected++;
            }
        }

        return $detected;
    }
}
