<?php

namespace App\Services\Operations\Remediation;

use App\Models\AuditLog;
use App\Models\OperationalActionApproval;
use App\Models\OperationalActionExecution;
use App\Models\OperationalAlert;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OperationalGovernanceService
{
    public function __construct(
        private OperationalActionRegistry $registry
    ) {}

    /**
     * Deterministically evaluate governance eligibility for a remediation action on an alert.
     */
    public function evaluateGovernance(OperationalAlert $alert, string $actionKey, User $actor): array
    {
        $action = $this->registry->getAction($actionKey);
        if (! $action) {
            return [
                'allowed' => false,
                'risk' => 'medium',
                'requires_confirmation' => false,
                'requires_approval' => false,
                'approval_status' => 'none',
                'approval_uuid' => null,
                'cooldown_remaining_seconds' => 0,
                'repeated_failures_count' => 0,
                'blocked_reason' => "UNSUPPORTED_ACTION: Unknown action key '{$actionKey}'.",
            ];
        }

        if (! in_array($alert->rule_key, $action->getSupportedRuleKeys(), true)) {
            return [
                'allowed' => false,
                'risk' => $action->getRiskLevel(),
                'requires_confirmation' => $action->requiresConfirmation(),
                'requires_approval' => $action->requiresApproval(),
                'approval_status' => 'none',
                'approval_uuid' => null,
                'cooldown_remaining_seconds' => 0,
                'repeated_failures_count' => 0,
                'blocked_reason' => "UNSUPPORTED_ACTION: Action '{$actionKey}' does not support rule '{$alert->rule_key}'.",
            ];
        }

        // 1. Alert Status Check
        if (in_array($alert->status, ['resolved', 'dismissed'], true)) {
            return [
                'allowed' => false,
                'risk' => $action->getRiskLevel(),
                'requires_confirmation' => $action->requiresConfirmation(),
                'requires_approval' => $action->requiresApproval(),
                'approval_status' => 'none',
                'approval_uuid' => null,
                'cooldown_remaining_seconds' => 0,
                'repeated_failures_count' => 0,
                'blocked_reason' => "ALERT_ALREADY_RESOLVED: Remediation cannot be executed on an alert with status '{$alert->status}'.",
            ];
        }

        // 2. Permission Check
        if (! $actor->hasPermission($action->getRequiredPermission()) && ! $actor->hasRole('super-admin') && ! $actor->hasRole('admin')) {
            return [
                'allowed' => false,
                'risk' => $action->getRiskLevel(),
                'requires_confirmation' => $action->requiresConfirmation(),
                'requires_approval' => $action->requiresApproval(),
                'approval_status' => 'none',
                'approval_uuid' => null,
                'cooldown_remaining_seconds' => 0,
                'repeated_failures_count' => 0,
                'blocked_reason' => "PERMISSION_DENIED: Required permission '{$action->getRequiredPermission()}' missing.",
            ];
        }

        // 3. Server-side Cooldown Check
        $cooldownSeconds = $action->getCooldownSeconds();
        $latestExecution = OperationalActionExecution::where('operational_alert_id', $alert->id)
            ->where('action_key', $actionKey)
            ->where('created_at', '>=', now()->subSeconds($cooldownSeconds))
            ->orderBy('created_at', 'desc')
            ->first();

        if ($latestExecution) {
            $elapsed = now()->diffInSeconds($latestExecution->created_at);
            $remaining = max(1, $cooldownSeconds - $elapsed);

            return [
                'allowed' => false,
                'risk' => $action->getRiskLevel(),
                'requires_confirmation' => $action->requiresConfirmation(),
                'requires_approval' => $action->requiresApproval(),
                'approval_status' => 'none',
                'approval_uuid' => null,
                'cooldown_remaining_seconds' => (int) $remaining,
                'repeated_failures_count' => 0,
                'blocked_reason' => "COOLDOWN_ACTIVE: Action is on cooldown for another {$remaining} second(s).",
            ];
        }

        // 4. Repeated Failures Threshold Check
        $maxFailures = $action->getMaxFailureThreshold();
        $recentExecutions = OperationalActionExecution::where('operational_alert_id', $alert->id)
            ->where('action_key', $actionKey)
            ->orderBy('created_at', 'desc')
            ->take($maxFailures)
            ->get();

        $consecutiveFailures = 0;
        foreach ($recentExecutions as $exec) {
            if ($exec->status === 'failed') {
                $consecutiveFailures++;
            } else {
                break;
            }
        }

        if ($consecutiveFailures >= $maxFailures) {
            return [
                'allowed' => false,
                'risk' => $action->getRiskLevel(),
                'requires_confirmation' => $action->requiresConfirmation(),
                'requires_approval' => $action->requiresApproval(),
                'approval_status' => 'none',
                'approval_uuid' => null,
                'cooldown_remaining_seconds' => 0,
                'repeated_failures_count' => $consecutiveFailures,
                'blocked_reason' => "AUTOMATION_TEMPORARILY_BLOCKED: Action has failed {$consecutiveFailures} consecutive times and requires manual investigation.",
            ];
        }

        // 5. Elevated Approval Check
        $needsApproval = $action->requiresApproval() || in_array($action->getRiskLevel(), ['high', 'critical'], true);

        if ($needsApproval) {
            $approval = OperationalActionApproval::where('operational_alert_id', $alert->id)
                ->where('action_key', $actionKey)
                ->orderBy('created_at', 'desc')
                ->first();

            if (! $approval || $approval->isExpired()) {
                return [
                    'allowed' => false,
                    'risk' => $action->getRiskLevel(),
                    'requires_confirmation' => $action->requiresConfirmation(),
                    'requires_approval' => true,
                    'approval_status' => 'none',
                    'approval_uuid' => null,
                    'cooldown_remaining_seconds' => 0,
                    'repeated_failures_count' => $consecutiveFailures,
                    'blocked_reason' => 'APPROVAL_REQUIRED: Elevated administrator approval is required prior to execution.',
                ];
            }

            if ($approval->status === 'pending') {
                return [
                    'allowed' => false,
                    'risk' => $action->getRiskLevel(),
                    'requires_confirmation' => $action->requiresConfirmation(),
                    'requires_approval' => true,
                    'approval_status' => 'pending',
                    'approval_uuid' => $approval->uuid,
                    'cooldown_remaining_seconds' => 0,
                    'repeated_failures_count' => $consecutiveFailures,
                    'blocked_reason' => "PENDING_APPROVAL: Approval requested on {$approval->requested_at->toIso8601String()}.",
                ];
            }

            if ($approval->status === 'rejected') {
                return [
                    'allowed' => false,
                    'risk' => $action->getRiskLevel(),
                    'requires_confirmation' => $action->requiresConfirmation(),
                    'requires_approval' => true,
                    'approval_status' => 'rejected',
                    'approval_uuid' => $approval->uuid,
                    'cooldown_remaining_seconds' => 0,
                    'repeated_failures_count' => $consecutiveFailures,
                    'blocked_reason' => "APPROVAL_REJECTED: Approval request was rejected on {$approval->decided_at?->toIso8601String()}.",
                ];
            }

            if ($approval->status === 'approved') {
                return [
                    'allowed' => true,
                    'risk' => $action->getRiskLevel(),
                    'requires_confirmation' => $action->requiresConfirmation(),
                    'requires_approval' => true,
                    'approval_status' => 'approved',
                    'approval_id' => $approval->id,
                    'approval_uuid' => $approval->uuid,
                    'cooldown_remaining_seconds' => 0,
                    'repeated_failures_count' => $consecutiveFailures,
                    'blocked_reason' => null,
                ];
            }
        }

        return [
            'allowed' => true,
            'risk' => $action->getRiskLevel(),
            'requires_confirmation' => $action->requiresConfirmation(),
            'requires_approval' => false,
            'approval_status' => 'none',
            'approval_uuid' => null,
            'cooldown_remaining_seconds' => 0,
            'repeated_failures_count' => $consecutiveFailures,
            'blocked_reason' => null,
        ];
    }

    /**
     * Submit an approval request for a governed action.
     */
    public function requestApproval(OperationalAlert $alert, string $actionKey, User $actor, ?string $reason = null): OperationalActionApproval
    {
        $action = $this->registry->getAction($actionKey);
        if (! $action) {
            throw new InvalidArgumentException("Unknown action key '{$actionKey}'.");
        }

        // Lock to prevent concurrent duplicate requests
        $lockKey = "operations:approval_request:{$alert->id}:{$actionKey}";
        $lock = Cache::lock($lockKey, 10);

        if (! $lock->get()) {
            throw new InvalidArgumentException('Concurrent approval request blocked. Please try again.');
        }

        try {
            // Check for existing pending request
            $existing = OperationalActionApproval::where('operational_alert_id', $alert->id)
                ->where('action_key', $actionKey)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->first();

            if ($existing) {
                return $existing;
            }

            $approval = OperationalActionApproval::create([
                'operational_alert_id' => $alert->id,
                'action_key' => $actionKey,
                'status' => 'pending',
                'requested_by' => $actor->id,
                'request_reason' => $reason,
                'requested_at' => now(),
                'expires_at' => now()->addHours(24),
            ]);

            AuditLog::create([
                'user_id' => $actor->id,
                'application' => 'admin-portal',
                'action' => 'operational_remediation_approval_requested',
                'severity' => 'info',
                'target_type' => OperationalAlert::class,
                'target_id' => $alert->id,
                'description' => "Elevated approval requested for remediation action '{$action->getName()}' ({$actionKey}).",
                'payload' => [
                    'approval_uuid' => $approval->uuid,
                    'action_key' => $actionKey,
                    'alert_id' => $alert->id,
                    'reason' => $reason,
                ],
            ]);

            return $approval;
        } finally {
            $lock->release();
        }
    }

    /**
     * Approve a pending remediation action request with Separation of Duties enforcement.
     */
    public function approveRequest(OperationalActionApproval $approval, User $actor, ?string $reason = null): OperationalActionApproval
    {
        if ($approval->status !== 'pending') {
            throw new InvalidArgumentException("Approval request #{$approval->id} is not in pending status.");
        }

        if ($approval->isExpired()) {
            $approval->status = 'expired';
            $approval->save();
            throw new InvalidArgumentException("Approval request #{$approval->id} has expired.");
        }

        // Separation of Duties: Requester cannot approve their own request (unless super-admin bypass)
        if ($approval->requested_by === $actor->id && ! $actor->hasRole('super-admin')) {
            throw new InvalidArgumentException('Separation of duties violation: You cannot approve your own remediation request.');
        }

        $approval->status = 'approved';
        $approval->approved_by = $actor->id;
        $approval->decision_reason = $reason;
        $approval->decided_at = now();
        $approval->save();

        AuditLog::create([
            'user_id' => $actor->id,
            'application' => 'admin-portal',
            'action' => 'operational_remediation_approval_granted',
            'severity' => 'info',
            'target_type' => OperationalActionApproval::class,
            'target_id' => $approval->id,
            'description' => "Approved remediation request #{$approval->id} for action '{$approval->action_key}'.",
            'payload' => [
                'approval_uuid' => $approval->uuid,
                'action_key' => $approval->action_key,
                'requested_by' => $approval->requested_by,
                'approved_by' => $actor->id,
                'reason' => $reason,
            ],
        ]);

        return $approval;
    }

    /**
     * Reject a pending remediation action request.
     */
    public function rejectRequest(OperationalActionApproval $approval, User $actor, ?string $reason = null): OperationalActionApproval
    {
        if ($approval->status !== 'pending') {
            throw new InvalidArgumentException("Approval request #{$approval->id} is not in pending status.");
        }

        $approval->status = 'rejected';
        $approval->rejected_by = $actor->id;
        $approval->decision_reason = $reason;
        $approval->decided_at = now();
        $approval->save();

        AuditLog::create([
            'user_id' => $actor->id,
            'application' => 'admin-portal',
            'action' => 'operational_remediation_approval_rejected',
            'severity' => 'warning',
            'target_type' => OperationalActionApproval::class,
            'target_id' => $approval->id,
            'description' => "Rejected remediation request #{$approval->id} for action '{$approval->action_key}'.",
            'payload' => [
                'approval_uuid' => $approval->uuid,
                'action_key' => $approval->action_key,
                'requested_by' => $approval->requested_by,
                'rejected_by' => $actor->id,
                'reason' => $reason,
            ],
        ]);

        return $approval;
    }

    /**
     * Calculate automation health and effectiveness metrics.
     */
    public function getAutomationHealthMetrics(): array
    {
        $totalExecutions = OperationalActionExecution::count();
        $completedExecutions = OperationalActionExecution::where('status', 'completed')->count();
        $failedExecutions = OperationalActionExecution::where('status', 'failed')->count();

        $executionSuccessRate = $totalExecutions > 0
            ? round(($completedExecutions / $totalExecutions) * 100, 1)
            : 100.0;

        // Resolved count: Completed executions where the underlying alert is currently resolved
        $resolvedCount = OperationalActionExecution::where('status', 'completed')
            ->whereHas('alert', function ($query) {
                $query->where('status', 'resolved');
            })
            ->count();

        $resolutionEffectivenessRate = $completedExecutions > 0
            ? round(($resolvedCount / $completedExecutions) * 100, 1)
            : 100.0;

        $blockedExecutionsCount = OperationalActionExecution::where('status', 'failed')
            ->whereIn('error_code', ['COOLDOWN_ACTIVE', 'PRECONDITION_FAILED', 'FAILURE_THRESHOLD_EXCEEDED', 'APPROVAL_REQUIRED', 'GOVERNANCE_BLOCKED'])
            ->count();

        $pendingApprovalsCount = OperationalActionApproval::where('status', 'pending')
            ->where('expires_at', '>', now())
            ->count();

        // Per-Action Breakdown
        $allActions = $this->registry->getAllActions();
        $actionBreakdown = [];

        foreach ($allActions as $action) {
            $key = $action->getKey();
            $actionTotal = OperationalActionExecution::where('action_key', $key)->count();
            $actionSuccess = OperationalActionExecution::where('action_key', $key)->where('status', 'completed')->count();
            $actionFailed = OperationalActionExecution::where('action_key', $key)->where('status', 'failed')->count();
            $actionResolved = OperationalActionExecution::where('action_key', $key)
                ->where('status', 'completed')
                ->whereHas('alert', function ($query) {
                    $query->where('status', 'resolved');
                })
                ->count();

            $actionSuccessRate = $actionTotal > 0 ? round(($actionSuccess / $actionTotal) * 100, 1) : 100.0;
            $actionEffectivenessRate = $actionSuccess > 0 ? round(($actionResolved / $actionSuccess) * 100, 1) : 100.0;

            $actionBreakdown[] = [
                'action_key' => $key,
                'name' => $action->getName(),
                'risk_level' => $action->getRiskLevel(),
                'total_executions' => $actionTotal,
                'success_count' => $actionSuccess,
                'resolved_count' => $actionResolved,
                'failed_count' => $actionFailed,
                'success_rate' => $actionSuccessRate,
                'resolution_effectiveness_rate' => $actionEffectivenessRate,
            ];
        }

        return [
            'total_executions' => $totalExecutions,
            'completed_executions' => $completedExecutions,
            'failed_executions' => $failedExecutions,
            'execution_success_rate' => $executionSuccessRate,
            'problem_resolved_count' => $resolvedCount,
            'problem_resolution_effectiveness_rate' => $resolutionEffectivenessRate,
            'blocked_executions_count' => $blockedExecutionsCount,
            'pending_approvals_count' => $pendingApprovalsCount,
            'action_breakdown' => $actionBreakdown,
        ];
    }
}
