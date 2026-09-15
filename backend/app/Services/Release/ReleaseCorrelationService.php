<?php

namespace App\Services\Release;

use App\Models\AuditLog;
use App\Models\OperationalActionExecution;
use App\Models\OperationalAlert;
use App\Models\PlatformRelease;
use Carbon\Carbon;
use Throwable;

class ReleaseCorrelationService
{
    /**
     * Reconstruct complete chronological timeline and correlation analysis for a release.
     */
    public function reconstructReleaseTimeline(PlatformRelease $release): array
    {
        $timelineEvents = [];

        // Event 1: Release Created
        $timelineEvents[] = [
            'timestamp' => $release->created_at ? $release->created_at->toIso8601String() : now()->toIso8601String(),
            'event_type' => 'release_created',
            'title' => "Release {$release->release_identifier} Created",
            'description' => "Release registered with application version {$release->application_version} and status {$release->status}.",
            'actor' => $release->creator ? $release->creator->name : 'System Administrator',
            'relationship' => 'directly_related',
        ];

        // Event 2: Approval if present
        if ($release->approved_at && $release->approved_by) {
            $timelineEvents[] = [
                'timestamp' => $release->approved_at->toIso8601String(),
                'event_type' => 'release_approved',
                'title' => "Release Approved by {$release->approver->name}",
                'description' => "Release {$release->release_identifier} authorized for deployment.",
                'actor' => $release->approver->name,
                'relationship' => 'directly_related',
            ];
        }

        // Event 3: Linked Changes
        foreach ($release->changes as $change) {
            $timelineEvents[] = [
                'timestamp' => $change->created_at ? $change->created_at->toIso8601String() : now()->toIso8601String(),
                'event_type' => 'change_registered',
                'title' => "Change: {$change->title}",
                'description' => "Category: {$change->category}. Impact: {$change->impact_level}. {$change->description}",
                'actor' => $change->author ? $change->author->name : 'System',
                'relationship' => 'same_change',
            ];
        }

        // Event 4: Released / Deployed Timestamp
        if ($release->released_at) {
            $timelineEvents[] = [
                'timestamp' => $release->released_at->toIso8601String(),
                'event_type' => 'release_deployed',
                'title' => "Release Deployed to {$release->environment}",
                'description' => "Deployment completed. Status: {$release->status}.",
                'actor' => 'Deployment Agent / Administrator',
                'relationship' => 'directly_related',
            ];
        }

        // Event 5: Post-Release Operational Alerts Correlation
        if ($release->released_at) {
            try {
                $windowEnd = Carbon::parse($release->released_at)->addHours(24);
                $alerts = OperationalAlert::whereBetween('created_at', [$release->released_at, $windowEnd])
                    ->limit(20)
                    ->get();

                foreach ($alerts as $alert) {
                    $timelineEvents[] = [
                        'timestamp' => $alert->created_at->toIso8601String(),
                        'event_type' => 'correlated_operational_alert',
                        'title' => "Operational Alert: {$alert->title}",
                        'description' => "Severity: {$alert->severity}. Source: {$alert->source_domain}. Note: Temporal correlation does not prove causation.",
                        'actor' => 'Operational Detection Engine',
                        'relationship' => 'temporal_correlation',
                    ];
                }
            } catch (Throwable) {
                // Ignore query failures
            }
        }

        // Event 6: Audit Logs for Release Target
        try {
            $auditLogs = AuditLog::where(function ($q) use ($release) {
                $q->where('target_type', PlatformRelease::class)
                  ->where('target_id', (string) $release->id);
            })
            ->orWhere('description', 'like', "%{$release->release_identifier}%")
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

            foreach ($auditLogs as $log) {
                $timelineEvents[] = [
                    'timestamp' => $log->created_at ? $log->created_at->toIso8601String() : now()->toIso8601String(),
                    'event_type' => 'audit_record',
                    'title' => "Audit: {$log->action}",
                    'description' => $log->description ?: 'Administrative operation logged.',
                    'actor' => $log->user ? $log->user->name : 'System',
                    'relationship' => 'same_entity',
                ];
            }
        } catch (Throwable) {
            // Ignore audit log query failures
        }

        // Sort events chronologically
        usort($timelineEvents, fn($a, $b) => strcmp($a['timestamp'], $b['timestamp']));

        return [
            'release_identifier' => $release->release_identifier,
            'total_events' => count($timelineEvents),
            'timeline' => $timelineEvents,
            'correlation_semantics' => [
                'directly_related' => 'Event is an explicit state transition of the release.',
                'same_change' => 'Event represents a change item linked to this release.',
                'same_entity' => 'Audit log record targeting the release or its changes.',
                'temporal_correlation' => 'Event occurred within 24 hours of release deployment. Temporal correlation does not prove causation.',
            ],
        ];
    }
}
