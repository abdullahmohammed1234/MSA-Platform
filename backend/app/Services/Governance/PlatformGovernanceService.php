<?php

namespace App\Services\Governance;

use App\Models\OperationalActionApproval;
use App\Models\OperationalAlert;
use App\Models\User;
use App\Services\ApplicationAccessService;
use App\Services\Operations\Remediation\OperationalGovernanceService;

class PlatformGovernanceService
{
    public function __construct(
        private AuditGovernanceService $auditService,
        private IntegrityCheckService $integrityService,
        private ContinuityReadinessService $continuityService,
        private IncidentTimelineService $timelineService,
        private OperationalGovernanceService $remediationGovernanceService,
        private ApplicationAccessService $accessService
    ) {}

    /**
     * Get complete aggregated Governance & Operational Continuity overview.
     */
    public function getGovernanceOverview(string $period = '7d', ?User $user = null): array
    {
        $auditLogs = $this->auditService->searchAuditLogs(['per_page' => 10], 10, $user);
        $changeAccountability = $this->auditService->getChangeAccountability($period, 10, $user);
        $integrityScan = $this->integrityService->runIntegrityScan(null, $user);
        $continuityReadiness = $this->continuityService->getContinuityReadiness();

        // Pending Governance Approvals count
        $pendingApprovalsQuery = OperationalActionApproval::where('status', 'pending');
        $pendingApprovalsCount = $pendingApprovalsQuery->count();

        // Stale approvals older than 12 hours
        $staleApprovalsCount = OperationalActionApproval::where('status', 'pending')
            ->where('requested_at', '<=', now()->subHours(12))
            ->count();

        // Open Alerts Metrics
        $openCriticalAlerts = OperationalAlert::whereIn('status', ['open', 'acknowledged'])
            ->where('severity', 'critical')
            ->count();

        $openHighAlerts = OperationalAlert::whereIn('status', ['open', 'acknowledged'])
            ->where('severity', 'high')
            ->count();

        // Remediation Governance Health
        $automationHealth = $this->remediationGovernanceService->getAutomationHealthMetrics();

        // Calculate Deterministic Score
        $scoreResult = $this->calculateReadinessScore([
            'open_critical_alerts' => $openCriticalAlerts,
            'open_high_alerts' => $openHighAlerts,
            'integrity_failed_count' => $integrityScan['failed_count'],
            'integrity_warning_count' => $integrityScan['warning_count'],
            'stale_approvals_count' => $staleApprovalsCount,
            'continuity_probes' => $continuityReadiness['probes'],
            'automation_success_rate' => $automationHealth['success_rate_percentage'] ?? 100,
        ]);

        return [
            'period' => $period,
            'generated_at' => now()->toIso8601String(),
            'governance_score' => $scoreResult['score'],
            'score_level' => $scoreResult['level'],
            'score_drivers' => $scoreResult['drivers'],
            'audit_activity' => [
                'recent_logs_count' => $auditLogs->total(),
                'recent_logs' => $auditLogs->items(),
            ],
            'change_accountability' => $changeAccountability,
            'integrity_status' => $integrityScan,
            'continuity_readiness' => $continuityReadiness,
            'governance_backlog' => [
                'pending_approvals_count' => $pendingApprovalsCount,
                'stale_approvals_count' => $staleApprovalsCount,
            ],
            'automation_reliability' => $automationHealth,
        ];
    }

    /**
     * Compute 100% explainable, deterministic Governance Readiness Score (0 - 100).
     */
    public function calculateReadinessScore(array $context): array
    {
        $score = 100;
        $drivers = [];

        // 1. Critical & High Operational Alerts
        $openCritical = $context['open_critical_alerts'] ?? 0;
        $openHigh = $context['open_high_alerts'] ?? 0;

        if ($openCritical > 0 || $openHigh > 0) {
            $deduction = ($openCritical * 15) + ($openHigh * 5);
            $score -= $deduction;
            $drivers[] = [
                'category' => 'incidents',
                'deduction' => $deduction,
                'reason' => "Readiness reduced by {$deduction} points due to {$openCritical} critical and {$openHigh} high open operational alert(s).",
            ];
        }

        // 2. Data Integrity Diagnostics
        $failedIntegrity = $context['integrity_failed_count'] ?? 0;
        $warningIntegrity = $context['integrity_warning_count'] ?? 0;

        if ($failedIntegrity > 0 || $warningIntegrity > 0) {
            $deduction = ($failedIntegrity * 10) + ($warningIntegrity * 4);
            $score -= $deduction;
            $drivers[] = [
                'category' => 'data_integrity',
                'deduction' => $deduction,
                'reason' => "Readiness reduced by {$deduction} points due to {$failedIntegrity} failed and {$warningIntegrity} warning data integrity check(s).",
            ];
        }

        // 3. Stale Governance Approvals
        $staleApprovals = $context['stale_approvals_count'] ?? 0;
        if ($staleApprovals > 0) {
            $deduction = min(20, $staleApprovals * 5);
            $score -= $deduction;
            $drivers[] = [
                'category' => 'governance_backlog',
                'deduction' => $deduction,
                'reason' => "Readiness reduced by {$deduction} points due to {$staleApprovals} pending governance approval(s) older than 12 hours.",
            ];
        }

        // 4. Infrastructure Probes
        $probes = $context['continuity_probes'] ?? [];
        if (isset($probes['database']['status']) && $probes['database']['status'] === 'FAILED') {
            $score -= 25;
            $drivers[] = [
                'category' => 'infrastructure',
                'deduction' => 25,
                'reason' => 'Readiness reduced by 25 points due to primary database connection failure.',
            ];
        }
        if (isset($probes['cache']['status']) && $probes['cache']['status'] === 'FAILED') {
            $score -= 10;
            $drivers[] = [
                'category' => 'infrastructure',
                'deduction' => 10,
                'reason' => 'Readiness reduced by 10 points due to cache store unavailability.',
            ];
        }
        if (isset($probes['scheduler']['status']) && $probes['scheduler']['status'] === 'DEGRADED') {
            $score -= 10;
            $drivers[] = [
                'category' => 'infrastructure',
                'deduction' => 10,
                'reason' => 'Readiness reduced by 10 points due to stale scheduled task runner heartbeat.',
            ];
        }

        // 5. Backup Verification Status (NOT_VERIFIED)
        if (isset($probes['backup_verification']['status']) && $probes['backup_verification']['status'] === 'NOT_VERIFIED') {
            $score -= 10;
            $drivers[] = [
                'category' => 'backup_verification',
                'deduction' => 10,
                'reason' => 'Readiness reduced by 10 points because database & asset backup verification status is NOT_VERIFIED.',
            ];
        }

        $clampedScore = max(0, min(100, $score));

        $level = match (true) {
            $clampedScore >= 90 => 'EXCELLENT',
            $clampedScore >= 75 => 'GOOD',
            $clampedScore >= 50 => 'NEEDS_ATTENTION',
            default => 'CRITICAL',
        };

        return [
            'score' => $clampedScore,
            'level' => $level,
            'drivers' => $drivers,
        ];
    }
}
