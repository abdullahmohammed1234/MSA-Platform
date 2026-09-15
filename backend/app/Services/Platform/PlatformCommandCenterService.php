<?php

namespace App\Services\Platform;

use App\Models\ApplicationAccess;
use App\Models\OperationalActionApproval;
use App\Models\OperationalActionExecution;
use App\Models\OperationalAlert;
use App\Models\User;
use App\Services\ApplicationAccessService;
use App\Services\Intelligence\CommunicationsIntelligenceService;
use App\Services\Intelligence\DonationIntelligenceService;
use App\Services\Intelligence\EmsIntelligenceService;
use App\Services\Intelligence\FeedbackIntelligenceService;
use App\Services\Intelligence\MlibmsIntelligenceService;
use App\Services\Intelligence\StoreIntelligenceService;
use App\Services\Intelligence\VolunteerIntelligenceService;
use App\Services\Operations\OperationalAlertService;
use App\Services\Operations\Remediation\OperationalGovernanceService;
use App\Services\Systems\SystemsControlPlaneService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PlatformCommandCenterService
{
    public function __construct(
        private SystemsControlPlaneService $systemsControlPlane,
        private OperationalAlertService $alertService,
        private OperationalGovernanceService $governanceService,
        private ApplicationAccessService $appAccessService,
        private EmsIntelligenceService $emsIntelligence,
        private DonationIntelligenceService $donationIntelligence,
        private StoreIntelligenceService $storeIntelligence,
        private MlibmsIntelligenceService $mlibmsIntelligence,
        private CommunicationsIntelligenceService $communicationsIntelligence,
        private VolunteerIntelligenceService $volunteerIntelligence,
        private FeedbackIntelligenceService $feedbackIntelligence
    ) {}

    /**
     * Get complete aggregated Command Center operational telemetry DTO payload.
     */
    public function getCommandCenterData(User $actor, string $period = '30d', ?string $startDate = null, ?string $endDate = null): array
    {
        $checkedAt = now()->toIso8601String();

        $platformHealth = $this->getPlatformInfrastructureHealth();
        $attentionItems = $this->getAttentionRequiredItems($actor);
        $applicationMatrix = $this->getApplicationHealthMatrix($actor);
        $businessSnapshot = $this->getBusinessSnapshot($actor, $period, $startDate, $endDate);
        $automationSnapshot = $this->getAutomationSnapshot();
        $platformStatus = $this->getPlatformStatusOverview($platformHealth, $attentionItems, $automationSnapshot);
        $operationalRisk = $this->calculateOperationalRisk($attentionItems, $platformHealth, $automationSnapshot, $applicationMatrix);

        return [
            'generated_at' => $checkedAt,
            'period' => $period,
            'platform' => $platformStatus,
            'risk' => $operationalRisk,
            'attention' => $attentionItems,
            'infrastructure' => $platformHealth,
            'applications' => $applicationMatrix,
            'business' => $businessSnapshot,
            'automation' => $automationSnapshot,
        ];
    }

    /**
     * Layer 1 — Overall Platform Status Overview
     */
    private function getPlatformStatusOverview(array $infraHealth, array $attentionItems, array $automationSnapshot): array
    {
        $criticalAlertsCount = 0;
        $highAlertsCount = 0;
        foreach ($attentionItems as $item) {
            if (($item['severity'] ?? '') === 'critical') {
                $criticalAlertsCount++;
            } elseif (($item['severity'] ?? '') === 'high') {
                $highAlertsCount++;
            }
        }

        $failedJobsCount = $infraHealth['queues']['failed_jobs'] ?? 0;
        $commsFailuresCount = $infraHealth['communications']['failed_count'] ?? 0;
        $pendingApprovalsCount = $automationSnapshot['pending_approvals_count'] ?? 0;
        $blockedAutomationsCount = $automationSnapshot['blocked_executions_count'] ?? 0;

        $overallState = 'healthy';
        if ($criticalAlertsCount > 0 || ($infraHealth['database']['status'] ?? '') === 'unavailable') {
            $overallState = 'critical';
        } elseif ($highAlertsCount > 0 || $blockedAutomationsCount > 0 || ($infraHealth['queues']['status'] ?? '') === 'degraded') {
            $overallState = 'degraded';
        } elseif ($pendingApprovalsCount > 0 || $failedJobsCount > 0) {
            $overallState = 'attention_required';
        }

        return [
            'overall_status' => $overallState,
            'critical_alerts_count' => $criticalAlertsCount,
            'high_alerts_count' => $highAlertsCount,
            'pending_approvals_count' => $pendingApprovalsCount,
            'blocked_automations_count' => $blockedAutomationsCount,
            'failed_jobs_count' => $failedJobsCount,
            'scheduler_status' => $infraHealth['scheduler']['status'] ?? 'operational',
            'communication_failures_count' => $commsFailuresCount,
        ];
    }

    /**
     * Layer 2 — Prioritized Immediate Attention Required Items
     */
    private function getAttentionRequiredItems(User $actor): array
    {
        $items = [];

        // 1. Critical & High Operational Alerts (Phase 24)
        if (Schema::hasTable('operational_alerts')) {
            $activeAlerts = OperationalAlert::whereIn('status', ['open', 'acknowledged'])
                ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get();

            foreach ($activeAlerts as $alert) {
                if ($alert->severity === 'low') {
                    continue;
                }

                $drilldownUrl = '/admin/operations';
                if ($alert->category === 'ems') {
                    $drilldownUrl = '/ems/admin';
                } elseif ($alert->category === 'donations') {
                    $drilldownUrl = '/donations/admin';
                } elseif ($alert->category === 'store') {
                    $drilldownUrl = '/store/admin';
                } elseif ($alert->category === 'mlibms') {
                    $drilldownUrl = '/library/admin';
                }

                $items[] = [
                    'id' => "alert-{$alert->id}",
                    'type' => 'alert',
                    'severity' => strtolower($alert->severity),
                    'source' => ucfirst($alert->category),
                    'title' => $alert->title,
                    'summary' => $alert->description,
                    'created_at' => $alert->created_at->toIso8601String(),
                    'age_human' => $alert->created_at->diffForHumans(),
                    'status' => $alert->status,
                    'action_name' => 'View in Operations',
                    'action_key' => null,
                    'drilldown_url' => $drilldownUrl,
                ];
            }
        }

        // 2. Pending Governance Approvals (Phase 26)
        if (Schema::hasTable('operational_action_approvals')) {
            $pendingApprovals = OperationalActionApproval::with('alert:id,title,category,severity')
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get();

            foreach ($pendingApprovals as $appr) {
                $items[] = [
                    'id' => "approval-{$appr->uuid}",
                    'type' => 'pending_approval',
                    'severity' => 'high',
                    'source' => 'Governance',
                    'title' => "Approval Required: {$appr->action_key}",
                    'summary' => $appr->request_reason ?? "Elevated action requires administrator authorization prior to execution.",
                    'created_at' => $appr->requested_at->toIso8601String(),
                    'age_human' => $appr->requested_at->diffForHumans(),
                    'status' => 'pending_approval',
                    'action_name' => 'Review Approval',
                    'action_key' => $appr->action_key,
                    'drilldown_url' => '/admin/operations/history',
                ];
            }
        }

        // 3. Governance Blocked Remediation Executions
        if (Schema::hasTable('operational_action_executions')) {
            $blockedExecutions = OperationalActionExecution::with('alert:id,title,category')
                ->where('status', 'failed')
                ->where('error_code', 'GOVERNANCE_BLOCKED')
                ->where('created_at', '>=', now()->subHours(24))
                ->latest()
                ->take(5)
                ->get();

            foreach ($blockedExecutions as $exec) {
                $items[] = [
                    'id' => "blocked-{$exec->uuid}",
                    'type' => 'governance_blocked',
                    'severity' => 'medium',
                    'source' => 'Remediation',
                    'title' => "Blocked Action: {$exec->action_key}",
                    'summary' => $exec->error_message ?? "Action execution was blocked by governance policy.",
                    'created_at' => $exec->created_at->toIso8601String(),
                    'age_human' => $exec->created_at->diffForHumans(),
                    'status' => 'governance_blocked',
                    'action_name' => 'Investigate Block',
                    'action_key' => $exec->action_key,
                    'drilldown_url' => '/admin/operations/history',
                ];
            }
        }

        // Sort items by severity priority (critical > high > medium > low) and age
        usort($items, function ($a, $b) {
            $weight = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
            $wA = $weight[$a['severity']] ?? 1;
            $wB = $weight[$b['severity']] ?? 1;
            if ($wA !== $wB) {
                return $wB <=> $wA;
            }
            return strcmp($b['created_at'], $a['created_at']);
        });

        return array_slice($items, 0, 10);
    }

    /**
     * Infrastructure Health Signals
     */
    private function getPlatformInfrastructureHealth(): array
    {
        $overview = $this->systemsControlPlane->overview();
        $platformServices = [];
        foreach ($overview['platform_services'] ?? [] as $svc) {
            $platformServices[$svc['id']] = $svc;
        }

        $failedJobsCount = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $commsFailedCount = 0;
        if (Schema::hasTable('notification_logs')) {
            $commsFailedCount = DB::table('notification_logs')->where('status', 'failed')->count();
        }

        return [
            'database' => [
                'status' => $platformServices['database']['status'] ?? 'operational',
                'message' => $platformServices['database']['message'] ?? 'Database connection active',
                'driver' => config('database.default', 'mysql'),
            ],
            'storage' => [
                'status' => $platformServices['storage']['status'] ?? 'operational',
                'message' => $platformServices['storage']['message'] ?? 'Public storage disk accessible',
            ],
            'email' => [
                'status' => $platformServices['email']['status'] ?? 'operational',
                'message' => $platformServices['email']['message'] ?? 'Mail gateway active',
            ],
            'queues' => [
                'status' => $platformServices['queues']['status'] ?? 'operational',
                'message' => $platformServices['queues']['message'] ?? 'Queue connection active',
                'failed_jobs' => $failedJobsCount,
                'connection' => config('queue.default', 'sync'),
            ],
            'scheduler' => [
                'status' => 'operational',
                'message' => 'Platform background scheduler active (runs every 5m)',
            ],
            'communications' => [
                'status' => $commsFailedCount > 10 ? 'degraded' : 'operational',
                'message' => $commsFailedCount > 0 ? "{$commsFailedCount} failed notifications in log" : 'All notifications delivered',
                'failed_count' => $commsFailedCount,
            ],
        ];
    }

    /**
     * Application Health Matrix
     */
    private function getApplicationHealthMatrix(User $actor): array
    {
        $overview = $this->systemsControlPlane->overview();
        $appsList = $overview['applications'] ?? [];

        $matrix = [];
        foreach ($appsList as $app) {
            $appId = $app['id'];
            $canAccess = $this->appAccessService->canAccess($actor, $appId) || $actor->hasRole('super-admin') || $actor->hasRole('admin');

            $categoryMap = [
                'ems' => 'ems',
                'donations' => 'donations',
                'store' => 'store',
                'mlibms' => 'mlibms',
                'volunteers' => 'volunteering',
            ];
            $category = $categoryMap[$appId] ?? $appId;

            $openAlertsCount = 0;
            if (Schema::hasTable('operational_alerts')) {
                $openAlertsCount = OperationalAlert::where('category', $category)
                    ->whereIn('status', ['open', 'acknowledged'])
                    ->count();
            }

            $matrix[] = [
                'id' => $appId,
                'name' => $app['name'],
                'status' => $app['status'] ?? 'operational',
                'health_status' => $app['health_status'] ?? 'operational',
                'status_reason' => $app['status_reason'] ?? 'Reachable and responding',
                'access_granted' => $canAccess,
                'open_issues_count' => $openAlertsCount,
                'launch_url' => $app['launch_url'] ?? '/',
                'admin_path' => $app['admin_path'] ?? null,
                'last_checked_at' => $app['last_checked_at'] ?? now()->toIso8601String(),
            ];
        }

        return $matrix;
    }

    /**
     * Business Operations Snapshot (Reuse Phase 23 Intelligence)
     */
    private function getBusinessSnapshot(User $actor, string $period, ?string $startDate, ?string $endDate): array
    {
        $isPrivileged = $actor->hasRole('super-admin') || $actor->hasRole('admin');

        $domains = [
            'ems' => fn () => $this->emsIntelligence->getAnalytics($period, $startDate, $endDate),
            'donations' => fn () => $this->donationIntelligence->getAnalytics($period, $startDate, $endDate),
            'store' => fn () => $this->storeIntelligence->getAnalytics($period, $startDate, $endDate),
            'mlibms' => fn () => $this->mlibmsIntelligence->getAnalytics($period, $startDate, $endDate),
            'communications' => fn () => $this->communicationsIntelligence->getAnalytics($period, $startDate, $endDate),
            'volunteers' => fn () => $this->volunteerIntelligence->getAnalytics($period, $startDate, $endDate),
            'feedback' => fn () => $this->feedbackIntelligence->getAnalytics($period, $startDate, $endDate),
        ];

        $snapshot = [];

        foreach ($domains as $domainKey => $fetcher) {
            $appAccessKey = $domainKey === 'communications' || $domainKey === 'feedback' ? 'main-website' : $domainKey;
            $hasAccess = $isPrivileged || $this->appAccessService->canAccess($actor, $appAccessKey);

            if (! $hasAccess) {
                $snapshot[$domainKey] = [
                    'access_granted' => false,
                    'status' => 'restricted',
                    'message' => "Access Restricted: You do not have permission to view {$domainKey} domain telemetry.",
                ];
                continue;
            }

            try {
                $data = $fetcher();
                $snapshot[$domainKey] = array_merge([
                    'access_granted' => true,
                    'status' => 'available',
                ], $data);
            } catch (Throwable $e) {
                $snapshot[$domainKey] = [
                    'access_granted' => true,
                    'status' => 'unavailable',
                    'message' => "Telemetry unavailable for {$domainKey}: {$e->getMessage()}",
                ];
            }
        }

        return $snapshot;
    }

    /**
     * Automation Operations Snapshot (Reuse Phase 26 Governance Metrics)
     */
    private function getAutomationSnapshot(): array
    {
        try {
            $metrics = $this->governanceService->getAutomationHealthMetrics();
            return array_merge(['status' => 'available'], $metrics);
        } catch (Throwable $e) {
            return [
                'status' => 'unavailable',
                'message' => "Automation governance metrics unavailable: {$e->getMessage()}",
                'total_executions' => 0,
                'completed_executions' => 0,
                'failed_executions' => 0,
                'execution_success_rate' => 100.0,
                'problem_resolution_effectiveness_rate' => 100.0,
                'blocked_executions_count' => 0,
                'pending_approvals_count' => 0,
                'action_breakdown' => [],
            ];
        }
    }

    /**
     * Deterministic Operational Risk Score
     */
    private function calculateOperationalRisk(array $attentionItems, array $infraHealth, array $automationSnapshot, array $applicationMatrix): array
    {
        $points = 0;
        $factors = [];

        // 1. Critical Alerts (+30 points each)
        $criticalCount = 0;
        $highCount = 0;
        foreach ($attentionItems as $item) {
            if (($item['severity'] ?? '') === 'critical') {
                $criticalCount++;
            } elseif (($item['severity'] ?? '') === 'high') {
                $highCount++;
            }
        }

        if ($criticalCount > 0) {
            $add = $criticalCount * 30;
            $points += $add;
            $factors[] = "{$criticalCount} active CRITICAL operational alert(s) (+{$add} pts)";
        }

        if ($highCount > 0) {
            $add = $highCount * 15;
            $points += $add;
            $factors[] = "{$highCount} active HIGH operational alert(s) (+{$add} pts)";
        }

        // 2. Governance Blocked Remediation Executions (+10 points each)
        $blockedCount = $automationSnapshot['blocked_executions_count'] ?? 0;
        if ($blockedCount > 0) {
            $add = min(30, $blockedCount * 10);
            $points += $add;
            $factors[] = "{$blockedCount} remediation action(s) blocked by governance (+{$add} pts)";
        }

        // 3. Pending Approvals (+5 points each)
        $pendingApprovals = $automationSnapshot['pending_approvals_count'] ?? 0;
        if ($pendingApprovals > 0) {
            $add = min(20, $pendingApprovals * 5);
            $points += $add;
            $factors[] = "{$pendingApprovals} pending elevated remediation approval(s) (+{$add} pts)";
        }

        // 4. Queue Failed Jobs (+10 points)
        $failedJobs = $infraHealth['queues']['failed_jobs'] ?? 0;
        if ($failedJobs > 5) {
            $points += 10;
            $factors[] = "{$failedJobs} failed queue jobs present (+10 pts)";
        }

        // 5. Application Degraded/Unavailable (+20 points each)
        $degradedApps = 0;
        foreach ($applicationMatrix as $app) {
            if (in_array(($app['status'] ?? 'operational'), ['degraded', 'unavailable'], true)) {
                $degradedApps++;
            }
        }
        if ($degradedApps > 0) {
            $add = $degradedApps * 20;
            $points += $add;
            $factors[] = "{$degradedApps} platform application(s) degraded/unavailable (+{$add} pts)";
        }

        // Determine Level
        if ($points >= 60) {
            $level = 'CRITICAL';
            $color = 'red';
        } elseif ($points >= 36) {
            $level = 'HIGH';
            $color = 'orange';
        } elseif ($points >= 16) {
            $level = 'MEDIUM';
            $color = 'yellow';
        } else {
            $level = 'LOW';
            $color = 'green';
        }

        if (empty($factors)) {
            $factors[] = 'All platform systems, queue workers, and automation flows operating within normal parameters.';
        }

        return [
            'score' => $points,
            'level' => $level,
            'color' => $color,
            'contributing_factors' => $factors,
        ];
    }
}
