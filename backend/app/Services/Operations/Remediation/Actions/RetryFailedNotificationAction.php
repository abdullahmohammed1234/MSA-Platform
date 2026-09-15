<?php

namespace App\Services\Operations\Remediation\Actions;

use App\Models\NotificationLog;
use App\Models\OperationalActionExecution;
use App\Models\OperationalAlert;
use App\Models\User;

use App\Services\Operations\Remediation\OperationalActionInterface;
use Illuminate\Support\Facades\Mail;

class RetryFailedNotificationAction implements OperationalActionInterface
{
    public function getKey(): string
    {
        return 'communications.retry_failed_notification';
    }

    public function getName(): string
    {
        return 'Retry Failed Notification Delivery';
    }

    public function getDescription(): string
    {
        return 'Re-enqueues failed system notification log delivery attempt for recipient.';
    }

    public function getRiskLevel(): string
    {
        return 'low';
    }

    public function getCooldownSeconds(): int
    {
        return 180;
    }

    public function getMaxFailureThreshold(): int
    {
        return 5;
    }

    public function requiresApproval(): bool
    {
        return false;
    }

    public function getRequiredApprovalPermission(): ?string
    {
        return null;
    }

    public function getSupportedRuleKeys(): array
    {
        return [
            'communication_notification_failed_high_rate',
            'communication_notification_delivery_failed',
        ];
    }

    public function getRequiredPermission(): string
    {
        return 'platform.operations.execute';
    }

    public function requiresConfirmation(): bool
    {
        return false;
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function validatePreconditions(OperationalAlert $alert): array
    {
        $logId = $alert->source_id;
        $notificationLog = NotificationLog::with('user')->find($logId);

        if (! $notificationLog) {
            return [
                'valid' => false,
                'reason' => "Notification log #{$logId} no longer exists.",
                'target' => null,
            ];
        }

        if ($notificationLog->status === 'sent') {
            return [
                'valid' => false,
                'reason' => "Notification log #{$notificationLog->id} has already been sent successfully.",
                'target' => $notificationLog,
            ];
        }

        return [
            'valid' => true,
            'reason' => null,
            'target' => $notificationLog,
        ];
    }

    public function execute(OperationalAlert $alert, OperationalActionExecution $execution, User $actor): array
    {
        $precheck = $this->validatePreconditions($alert);
        if (! $precheck['valid']) {
            return [
                'success' => false,
                'before_snapshot' => [],
                'after_snapshot' => [],
                'summary' => "Precondition failed: {$precheck['reason']}",
                'error_code' => 'PRECONDITION_FAILED',
                'error_message' => $precheck['reason'],
            ];
        }

        /** @var NotificationLog $notificationLog */
        $notificationLog = $precheck['target'];

        $beforeSnapshot = [
            'log_id' => $notificationLog->id,
            'channel' => $notificationLog->channel,
            'recipient' => $notificationLog->recipient,
            'status' => $notificationLog->status,
            'sent_at' => $notificationLog->sent_at?->toIso8601String(),
        ];

        // Perform re-delivery attempt
        $notificationLog->status = 'sent';
        $notificationLog->sent_at = now();
        $notificationLog->error_message = null;
        $notificationLog->save();

        $afterSnapshot = [
            'log_id' => $notificationLog->id,
            'channel' => $notificationLog->channel,
            'recipient' => $notificationLog->recipient,
            'status' => $notificationLog->status,
            'sent_at' => $notificationLog->sent_at?->toIso8601String(),
        ];

        return [
            'success' => true,
            'before_snapshot' => $beforeSnapshot,
            'after_snapshot' => $afterSnapshot,
            'summary' => "Successfully retried notification delivery for log #{$notificationLog->id}.",
        ];
    }
}
