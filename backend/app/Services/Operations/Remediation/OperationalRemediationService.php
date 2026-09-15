<?php

namespace App\Services\Operations\Remediation;

use App\Models\AuditLog;
use App\Models\OperationalActionApproval;
use App\Models\OperationalActionExecution;
use App\Models\OperationalAlert;
use App\Models\User;
use App\Services\Operations\OperationalAlertService;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class OperationalRemediationService
{
    public function __construct(
        private OperationalActionRegistry $registry,
        private OperationalAlertService $alertService,
        private OperationalGovernanceService $governanceService
    ) {}

    /**
     * Execute a specific remediation action against an operational alert under governance evaluation.
     */
    public function executeAction(OperationalAlert $alert, string $actionKey, User $actor): OperationalActionExecution
    {
        $action = $this->registry->getAction($actionKey);
        if (! $action) {
            throw new InvalidArgumentException("Unknown operational action key '{$actionKey}'.");
        }

        if (! in_array($alert->rule_key, $action->getSupportedRuleKeys(), true)) {
            throw new InvalidArgumentException("Action '{$actionKey}' is not applicable to alert rule key '{$alert->rule_key}'.");
        }

        // Governance Policy Evaluation Pipeline
        $governance = $this->governanceService->evaluateGovernance($alert, $actionKey, $actor);
        if (! $governance['allowed']) {
            return OperationalActionExecution::create([
                'operational_alert_id' => $alert->id,
                'action_key' => $actionKey,
                'requested_by' => $actor->id,
                'status' => 'failed',
                'result_summary' => "Blocked by governance policy: {$governance['blocked_reason']}",
                'error_code' => 'GOVERNANCE_BLOCKED',
                'error_message' => $governance['blocked_reason'],
                'started_at' => now(),
                'completed_at' => now(),
            ]);
        }

        // Lock to prevent concurrent execution on the same alert
        $lockKey = "operations:remediation:{$alert->id}";
        $lock = Cache::lock($lockKey, 15);

        if (! $lock->get()) {
            return OperationalActionExecution::create([
                'operational_alert_id' => $alert->id,
                'action_key' => $actionKey,
                'requested_by' => $actor->id,
                'status' => 'failed',
                'result_summary' => 'Concurrent remediation execution blocked.',
                'error_code' => 'CONCURRENCY_LOCK_ACTIVE',
                'error_message' => 'Another remediation action is currently executing on this alert.',
                'started_at' => now(),
                'completed_at' => now(),
            ]);
        }

        try {
            // Create running execution record
            /** @var OperationalActionExecution $execution */
            $execution = OperationalActionExecution::create([
                'operational_alert_id' => $alert->id,
                'approval_id' => $governance['approval_id'] ?? null,
                'action_key' => $actionKey,
                'requested_by' => $actor->id,
                'status' => 'running',
                'started_at' => now(),
            ]);

            // Re-evaluate preconditions dynamically
            $precondition = $action->validatePreconditions($alert);
            if (! $precondition['valid']) {
                $execution->status = 'failed';
                $execution->summary = "Precondition failed: {$precondition['reason']}";
                $execution->error_code = 'PRECONDITION_FAILED';
                $execution->error_message = $precheck['reason'] ?? $precondition['reason'];
                $execution->completed_at = now();
                $execution->save();

                return $execution;
            }

            // Execute the action handler
            $result = $action->execute($alert, $execution, $actor);

            $beforeSnapshot = $this->sanitizeSnapshot($result['before_snapshot'] ?? []);
            $afterSnapshot = $this->sanitizeSnapshot($result['after_snapshot'] ?? []);

            if ($result['success']) {
                $execution->status = 'completed';
                $execution->before_snapshot = $beforeSnapshot;
                $execution->after_snapshot = $afterSnapshot;
                $execution->summary = $result['summary'] ?? 'Action executed successfully.';
                $execution->completed_at = now();
                $execution->save();

                // If execution consumed an approved request, mark request executed
                if (! empty($governance['approval_uuid'])) {
                    OperationalActionApproval::where('uuid', $governance['approval_uuid'])->update(['status' => 'executed']);
                }

                // Re-check alert lifecycle. If precondition is no longer met (issue fixed), auto-resolve alert
                $postCheck = $action->validatePreconditions($alert);
                if (! $postCheck['valid'] && in_array($alert->status, ['open', 'acknowledged'], true)) {
                    $this->alertService->transitionStatus(
                        $alert,
                        'resolved',
                        $actor,
                        "Automated remediation executed successfully ({$action->getName()}): {$execution->summary}"
                    );
                }

                // Audit log entry
                AuditLog::create([
                    'user_id' => $actor->id,
                    'application' => 'admin-portal',
                    'action' => 'operational_remediation_execute',
                    'severity' => 'info',
                    'target_type' => OperationalAlert::class,
                    'target_id' => $alert->id,
                    'description' => "Remediation action '{$action->getName()}' ({$actionKey}) executed for alert #{$alert->id}.",
                    'payload' => [
                        'execution_uuid' => $execution->uuid,
                        'action_key' => $actionKey,
                        'alert_id' => $alert->id,
                        'alert_rule_key' => $alert->rule_key,
                        'summary' => $execution->summary,
                    ],
                ]);
            } else {
                $execution->status = 'failed';
                $execution->before_snapshot = $beforeSnapshot;
                $execution->after_snapshot = $afterSnapshot;
                $execution->summary = $result['summary'] ?? 'Action execution failed.';
                $execution->error_code = $result['error_code'] ?? 'EXECUTION_FAILED';
                $execution->error_message = $result['error_message'] ?? 'Remediation action reported failure.';
                $execution->completed_at = now();
                $execution->save();

                AuditLog::create([
                    'user_id' => $actor->id,
                    'application' => 'admin-portal',
                    'action' => 'operational_remediation_failed',
                    'severity' => 'warning',
                    'target_type' => OperationalAlert::class,
                    'target_id' => $alert->id,
                    'description' => "Remediation action '{$action->getName()}' ({$actionKey}) failed for alert #{$alert->id}: {$execution->error_message}",
                    'payload' => [
                        'execution_uuid' => $execution->uuid,
                        'action_key' => $actionKey,
                        'alert_id' => $alert->id,
                        'error_code' => $execution->error_code,
                        'error_message' => $execution->error_message,
                    ],
                ]);
            }

            return $execution;
        } finally {
            $lock->release();
        }
    }

    /**
     * Recursively sanitize snapshot data to prevent recording sensitive tokens/secrets/PII.
     */
    private function sanitizeSnapshot(array $data): array
    {
        $sensitiveKeys = [
            'password', 'secret', 'token', 'api_key', 'authorization', 'bearer',
            'credit_card', 'card_number', 'cvv', 'ssn', 'private_key',
        ];

        $sanitized = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);
            $isSensitive = false;

            foreach ($sensitiveKeys as $pattern) {
                if (str_contains($lowerKey, $pattern)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeSnapshot($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
