<?php

namespace App\Services\Operations;

use App\Models\AuditLog;
use App\Models\OperationalAlert;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OperationalAlertService
{
    /**
     * Create or update an operational alert cleanly with atomic deduplication fingerprinting.
     */
    public function upsertAlert(array $data): OperationalAlert
    {
        $category = (string) ($data['category'] ?? 'platform');
        $sourceType = (string) ($data['source_type'] ?? 'System');
        $sourceId = (string) ($data['source_id'] ?? '0');
        $ruleKey = (string) ($data['rule_key'] ?? 'generic_rule');

        $fingerprint = OperationalAlert::computeFingerprint($category, $sourceType, $sourceId, $ruleKey);

        try {
            return DB::transaction(function () use ($data, $fingerprint, $category, $sourceType, $sourceId, $ruleKey) {
                $existing = OperationalAlert::where('fingerprint', $fingerprint)->first();

                if ($existing) {
                    $existing->last_detected_at = now();
                    $existing->title = $data['title'] ?? $existing->title;
                    $existing->description = $data['description'] ?? $existing->description;
                    $existing->severity = $data['severity'] ?? $existing->severity;
                    $existing->action_url = $data['action_url'] ?? $existing->action_url;
                    $existing->metadata = array_merge($existing->metadata ?? [], $data['metadata'] ?? []);

                    // Reopen if condition persists and was previously marked resolved/dismissed
                    if (in_array($existing->status, ['resolved', 'dismissed'], true)) {
                        $existing->status = 'open';
                    }

                    $existing->save();

                    return $existing;
                }

                return OperationalAlert::create([
                    'fingerprint' => $fingerprint,
                    'category' => $category,
                    'severity' => $data['severity'] ?? 'medium',
                    'status' => 'open',
                    'title' => $data['title'] ?? 'Operational Issue Detected',
                    'description' => $data['description'] ?? 'An operational issue requiring administrator attention has been detected.',
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'rule_key' => $ruleKey,
                    'action_url' => $data['action_url'] ?? null,
                    'metadata' => $data['metadata'] ?? [],
                    'first_detected_at' => now(),
                    'last_detected_at' => now(),
                ]);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            $existing = OperationalAlert::where('fingerprint', $fingerprint)->first();
            if ($existing) {
                $existing->last_detected_at = now();
                $existing->save();
                return $existing;
            }
            throw $e;
        }
    }

    /**
     * Transition an alert status safely with lifecycle validation and audit logging.
     */
    public function transitionStatus(OperationalAlert $alert, string $targetStatus, ?User $actor = null, ?string $reason = null): OperationalAlert
    {
        $targetStatus = strtolower(trim($targetStatus));

        if (! $alert->canTransitionTo($targetStatus)) {
            throw new InvalidArgumentException("Invalid operational alert state transition from {$alert->status} to {$targetStatus}.");
        }

        $prevStatus = $alert->status;

        DB::transaction(function () use ($alert, $targetStatus, $actor, $reason, $prevStatus) {
            $alert->status = $targetStatus;

            if ($targetStatus === 'acknowledged') {
                $alert->acknowledged_at = now();
                $alert->acknowledged_by = $actor?->id;
            } elseif ($targetStatus === 'resolved') {
                $alert->resolved_at = now();
                $alert->resolved_by = $actor?->id;
                if ($reason) {
                    $alert->resolution_reason = $reason;
                }
            } elseif ($targetStatus === 'dismissed') {
                $alert->dismissed_at = now();
                $alert->dismissed_by = $actor?->id;
                if ($reason) {
                    $alert->resolution_reason = $reason;
                }
            } elseif ($targetStatus === 'open') {
                $alert->acknowledged_at = null;
                $alert->acknowledged_by = null;
                $alert->resolved_at = null;
                $alert->resolved_by = null;
                $alert->dismissed_at = null;
                $alert->dismissed_by = null;
            }

            $alert->save();

            $auditSeverity = match ($alert->severity) {
                'critical' => 'critical',
                'high', 'medium' => 'warning',
                default => 'info',
            };

            AuditLog::create([
                'user_id' => $actor?->id,
                'application' => 'admin-portal',
                'action' => 'operational_alert_transition',
                'severity' => $auditSeverity,
                'target_type' => OperationalAlert::class,
                'target_id' => $alert->id,
                'description' => "Operational alert #{$alert->id} ({$alert->rule_key}) transitioned from {$prevStatus} to {$targetStatus}.",
                'payload' => [
                    'alert_uuid' => $alert->uuid,
                    'rule_key' => $alert->rule_key,
                    'previous_status' => $prevStatus,
                    'new_status' => $targetStatus,
                    'reason' => $reason,
                ],
            ]);
        });

        return $alert;
    }
}
