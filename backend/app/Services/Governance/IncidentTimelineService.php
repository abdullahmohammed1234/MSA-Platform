<?php

namespace App\Services\Governance;

use App\Models\AuditLog;
use App\Models\OperationalActionApproval;
use App\Models\OperationalActionExecution;
use App\Models\OperationalAlert;
use App\Models\User;
use App\Services\ApplicationAccessService;
use Carbon\Carbon;

class IncidentTimelineService
{
    public function __construct(
        private ApplicationAccessService $accessService
    ) {}

    /**
     * Reconstruct deterministic, chronological incident timeline for an operational alert.
     */
    public function reconstructTimeline(string $identifier, ?User $user = null): array
    {
        $alert = OperationalAlert::where('uuid', $identifier)
            ->orWhere('id', is_numeric($identifier) ? (int) $identifier : 0)
            ->first();

        if (! $alert) {
            return [
                'status' => 'not_found',
                'message' => 'Operational alert not found.',
                'nodes' => [],
            ];
        }

        // Domain access privacy check
        if ($user && ! $user->hasAnyRole(['super-admin', 'admin'])) {
            $domain = strtolower($alert->category ?? $alert->source_type ?? '');
            if ($domain && ! $this->accessService->canAccess($user, $domain)) {
                return [
                    'status' => 'restricted',
                    'message' => 'Access to this domain alert timeline is restricted.',
                    'nodes' => [],
                ];
            }
        }

        $nodes = [];

        // 1. Initial Detection
        $nodes[] = [
            'id' => 'alert-detected-' . $alert->id,
            'timestamp' => $alert->first_detected_at->toIso8601String(),
            'event_type' => 'alert_detected',
            'severity' => $alert->severity,
            'title' => 'Operational Alert Detected',
            'summary' => "Alert '{$alert->title}' detected by rule '{$alert->rule_key}'.",
            'actor' => 'System Detection Engine',
            'relationship_label' => 'same_alert',
            'metadata' => [
                'fingerprint' => $alert->fingerprint,
                'category' => $alert->category,
                'source_type' => $alert->source_type,
                'source_id' => $alert->source_id,
            ],
        ];

        // 2. Acknowledged Event
        if ($alert->acknowledged_at) {
            $acknowledgedBy = $alert->acknowledgedBy?->name ?? 'Administrator';
            $nodes[] = [
                'id' => 'alert-ack-' . $alert->id,
                'timestamp' => $alert->acknowledged_at->toIso8601String(),
                'event_type' => 'alert_acknowledged',
                'severity' => 'info',
                'title' => 'Alert Acknowledged',
                'summary' => "Alert acknowledged by {$acknowledgedBy}.",
                'actor' => $acknowledgedBy,
                'relationship_label' => 'same_alert',
                'metadata' => [],
            ];
        }

        // 3. Approval Requests & Decisions
        $approvals = OperationalActionApproval::with(['requestedBy:id,name', 'approvedBy:id,name', 'rejectedBy:id,name'])
            ->where('operational_alert_id', $alert->id)
            ->get();

        foreach ($approvals as $approval) {
            // Requested
            $nodes[] = [
                'id' => 'approval-req-' . $approval->id,
                'timestamp' => $approval->requested_at->toIso8601String(),
                'event_type' => 'approval_requested',
                'severity' => 'warning',
                'title' => "Approval Requested: {$approval->action_key}",
                'summary' => "Action '{$approval->action_key}' requested by {$approval->requestedBy?->name}. Reason: " . ($approval->request_reason ?? 'Reason not recorded'),
                'actor' => $approval->requestedBy?->name ?? 'Administrator',
                'relationship_label' => 'directly_related',
                'metadata' => [
                    'approval_uuid' => $approval->uuid,
                    'status' => $approval->status,
                ],
            ];

            // Decision
            if ($approval->decided_at && $approval->status !== 'pending') {
                $decider = match ($approval->status) {
                    'approved' => $approval->approvedBy?->name ?? 'Approver',
                    'rejected' => $approval->rejectedBy?->name ?? 'Rejector',
                    default => 'System',
                };
                $nodes[] = [
                    'id' => 'approval-dec-' . $approval->id,
                    'timestamp' => $approval->decided_at->toIso8601String(),
                    'event_type' => 'approval_' . $approval->status,
                    'severity' => $approval->status === 'approved' ? 'info' : 'warning',
                    'title' => "Approval " . ucfirst($approval->status) . ": {$approval->action_key}",
                    'summary' => "Approval request for '{$approval->action_key}' was {$approval->status} by {$decider}. Reason: " . ($approval->decision_reason ?? 'Reason not recorded'),
                    'actor' => $decider,
                    'relationship_label' => 'directly_related',
                    'metadata' => [
                        'approval_uuid' => $approval->uuid,
                        'decision_reason' => $approval->decision_reason,
                    ],
                ];
            }
        }

        // 4. Action Executions
        $executions = OperationalActionExecution::with(['requestedBy:id,name', 'executedBy:id,name'])
            ->where('operational_alert_id', $alert->id)
            ->get();

        foreach ($executions as $exec) {
            $executor = $exec->executedBy?->name ?? $exec->requestedBy?->name ?? 'System';
            $nodes[] = [
                'id' => 'execution-' . $exec->id,
                'timestamp' => ($exec->completed_at ?? $exec->failed_at ?? $exec->started_at ?? $exec->requested_at)->toIso8601String(),
                'event_type' => 'remediation_execution',
                'severity' => $exec->status === 'completed' ? 'info' : 'critical',
                'title' => "Remediation Executed: {$exec->action_key}",
                'summary' => "Action '{$exec->action_key}' status: {$exec->status}. Result: " . ($exec->result_summary ?? $exec->error_message ?? 'Execution finished'),
                'actor' => $executor,
                'relationship_label' => 'directly_related',
                'metadata' => [
                    'execution_uuid' => $exec->uuid,
                    'status' => $exec->status,
                    'error_code' => $exec->error_code,
                    'before_snapshot' => $exec->before_snapshot,
                    'after_snapshot' => $exec->after_snapshot,
                ],
            ];
        }

        // 5. Resolved or Dismissed Event
        if ($alert->resolved_at) {
            $resolvedBy = $alert->resolvedBy?->name ?? 'System / Automation';
            $nodes[] = [
                'id' => 'alert-resolved-' . $alert->id,
                'timestamp' => $alert->resolved_at->toIso8601String(),
                'event_type' => 'alert_resolved',
                'severity' => 'info',
                'title' => 'Alert Resolved',
                'summary' => "Alert resolved by {$resolvedBy}. Reason: " . ($alert->resolution_reason ?? 'Resolved by administrator'),
                'actor' => $resolvedBy,
                'relationship_label' => 'same_alert',
                'metadata' => [],
            ];
        } elseif ($alert->dismissed_at) {
            $dismissedBy = $alert->dismissedBy?->name ?? 'Administrator';
            $nodes[] = [
                'id' => 'alert-dismissed-' . $alert->id,
                'timestamp' => $alert->dismissed_at->toIso8601String(),
                'event_type' => 'alert_dismissed',
                'severity' => 'info',
                'title' => 'Alert Dismissed',
                'summary' => "Alert dismissed by {$dismissedBy}.",
                'actor' => $dismissedBy,
                'relationship_label' => 'same_alert',
                'metadata' => [],
            ];
        }

        // 6. Recurrence Check
        if ($alert->last_detected_at && $alert->last_detected_at->ne($alert->first_detected_at)) {
            $nodes[] = [
                'id' => 'alert-recurrence-' . $alert->id,
                'timestamp' => $alert->last_detected_at->toIso8601String(),
                'event_type' => 'alert_recurrence',
                'severity' => 'warning',
                'title' => 'Alert Recurrence Detected',
                'summary' => 'Alert rule re-triggered and updated last_detected_at timestamp.',
                'actor' => 'System Detection Engine',
                'relationship_label' => 'same_alert',
                'metadata' => [],
            ];
        }

        // 7. Directly Related Entity Audit Logs
        if ($alert->source_type && $alert->source_id) {
            $entityLogs = AuditLog::with('user:id,name')
                ->where('target_type', 'like', '%' . class_basename($alert->source_type) . '%')
                ->where('target_id', $alert->source_id)
                ->take(10)
                ->get();

            foreach ($entityLogs as $eLog) {
                $nodes[] = [
                    'id' => 'audit-entity-' . $eLog->id,
                    'timestamp' => $eLog->created_at->toIso8601String(),
                    'event_type' => 'audit_event',
                    'severity' => $eLog->severity,
                    'title' => "Audit Event: {$eLog->action}",
                    'summary' => $eLog->description ?? $eLog->action,
                    'actor' => $eLog->user?->name ?? 'System',
                    'relationship_label' => 'same_entity',
                    'metadata' => [],
                ];
            }
        }

        // Sort nodes chronologically by timestamp
        usort($nodes, fn ($a, $b) => strcmp($a['timestamp'], $b['timestamp']));

        return [
            'status' => 'success',
            'alert' => [
                'id' => $alert->id,
                'uuid' => $alert->uuid,
                'title' => $alert->title,
                'status' => $alert->status,
                'severity' => $alert->severity,
                'category' => $alert->category,
                'rule_key' => $alert->rule_key,
                'first_detected_at' => $alert->first_detected_at->toIso8601String(),
                'last_detected_at' => $alert->last_detected_at->toIso8601String(),
            ],
            'nodes_count' => count($nodes),
            'nodes' => $nodes,
        ];
    }
}
