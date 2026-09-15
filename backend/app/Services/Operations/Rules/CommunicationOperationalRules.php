<?php

namespace App\Services\Operations\Rules;

use App\Models\NotificationLog;
use App\Services\Operations\OperationalAlertService;
use Illuminate\Support\Facades\Schema;

class CommunicationOperationalRules
{
    public function __construct(
        private OperationalAlertService $alertService
    ) {}

    public function detect(): int
    {
        if (! Schema::hasTable('notification_logs')) {
            return 0;
        }

        $detected = 0;

        // 1. Individual notification failure alerts in past 24 hours
        $failedLogs = NotificationLog::where('status', 'failed')
            ->where('created_at', '>=', now()->subHours(24))
            ->latest()
            ->take(20)
            ->get();

        foreach ($failedLogs as $log) {
            $this->alertService->upsertAlert([
                'category' => 'communications',
                'severity' => 'medium',
                'title' => "Notification Delivery Failed: {$log->notification_type}",
                'description' => "Failed to deliver {$log->channel} notification ({$log->notification_type}): " . ($log->error_message ?? 'Unknown error'),
                'source_type' => 'NotificationLog',
                'source_id' => (string) $log->id,
                'rule_key' => 'comms_notification_failed',
                'action_url' => '/admin/communications',
                'metadata' => [
                    'notification_log_id' => $log->id,
                    'user_id' => $log->user_id,
                    'notification_type' => $log->notification_type,
                    'channel' => $log->channel,
                    'error_message' => $log->error_message,
                ],
            ]);
            $detected++;
        }

        // 2. Aggregate check: repeated notification failures in past 24 hours (> 3 failures)
        $failedCount24h = NotificationLog::where('status', 'failed')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        if ($failedCount24h > 3) {
            $this->alertService->upsertAlert([
                'category' => 'communications',
                'severity' => 'high',
                'title' => "High Communication Failure Rate Detected",
                'description' => "System detected {$failedCount24h} notification delivery failures in the last 24 hours.",
                'source_type' => 'System',
                'source_id' => 'comms_aggregate_24h',
                'rule_key' => 'comms_repeated_failures',
                'action_url' => '/admin/communications',
                'metadata' => [
                    'failure_count_24h' => $failedCount24h,
                    'threshold' => 3,
                ],
            ]);
            $detected++;
        }

        return $detected;
    }
}
