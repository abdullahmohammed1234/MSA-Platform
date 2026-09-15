<?php

namespace App\Services\Operations\Remediation\Actions;

use App\Enums\VolunteerRegistrationStatus;
use App\Models\OperationalActionExecution;
use App\Models\OperationalAlert;
use App\Models\User;
use App\Models\VolunteerRegistration;
use App\Services\Operations\Remediation\OperationalActionInterface;
use Illuminate\Support\Facades\Schema;

class RefreshPendingBacklogAction implements OperationalActionInterface
{
    public function getKey(): string
    {
        return 'volunteers.refresh_pending_backlog';
    }

    public function getName(): string
    {
        return 'Refresh Volunteer Pending Backlog';
    }

    public function getDescription(): string
    {
        return 'Re-evaluates unreviewed volunteer application backlog and updates alert metadata.';
    }

    public function getRiskLevel(): string
    {
        return 'low';
    }

    public function getCooldownSeconds(): int
    {
        return 60;
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
            'volunteer_pending_backlog',
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
        return true;
    }

    public function validatePreconditions(OperationalAlert $alert): array
    {
        if (! Schema::hasTable('volunteer_registrations')) {
            return [
                'valid' => false,
                'reason' => 'Volunteer registrations table does not exist.',
                'target' => null,
            ];
        }

        $currentPending = VolunteerRegistration::where('status', VolunteerRegistrationStatus::New->value)
            ->where('created_at', '<', now()->subHours(72))
            ->count();

        return [
            'valid' => true,
            'reason' => null,
            'target' => [
                'stale_pending_count' => $currentPending,
            ],
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

        $beforeCount = $alert->metadata['pending_count'] ?? null;
        $currentPending = VolunteerRegistration::where('status', VolunteerRegistrationStatus::New->value)
            ->where('created_at', '<', now()->subHours(72))
            ->count();

        $beforeSnapshot = [
            'alert_metadata_pending' => $beforeCount,
            'evaluated_at' => now()->toIso8601String(),
        ];

        // Update alert metadata with refreshed count
        $metadata = $alert->metadata ?? [];
        $metadata['pending_count'] = $currentPending;
        $metadata['last_refreshed_at'] = now()->toIso8601String();
        $alert->metadata = $metadata;
        $alert->save();

        $afterSnapshot = [
            'refreshed_pending_count' => $currentPending,
            'threshold' => 5,
            'backlog_cleared' => $currentPending <= 5,
        ];

        $summary = $currentPending <= 5
            ? "Volunteer backlog now has {$currentPending} pending items (threshold: 5). Condition resolved."
            : "Refreshed volunteer backlog count: {$currentPending} pending applications remaining.";

        return [
            'success' => true,
            'before_snapshot' => $beforeSnapshot,
            'after_snapshot' => $afterSnapshot,
            'summary' => $summary,
        ];
    }
}
