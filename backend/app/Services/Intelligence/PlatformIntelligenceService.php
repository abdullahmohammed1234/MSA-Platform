<?php

namespace App\Services\Intelligence;

use App\Services\Systems\SystemsControlPlaneService;
use App\Models\User;
use App\Models\AuditLog;
use App\Platform\Enums\AlertStatus;
use App\Platform\Models\PlatformAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlatformIntelligenceService
{
    public function __construct(
        private SystemsControlPlaneService $systems,
        private EmsIntelligenceService $emsIntelligence,
        private DonationIntelligenceService $donationIntelligence,
        private StoreIntelligenceService $storeIntelligence,
        private MlibmsIntelligenceService $mlibmsIntelligence,
        private CommunicationsIntelligenceService $communicationsIntelligence,
        private VolunteerIntelligenceService $volunteerIntelligence,
        private FeedbackIntelligenceService $feedbackIntelligence
    ) {}

    /**
     * Get overall platform dashboard intelligence telemetry.
     */
    public function getDashboardTelemetry(string $period = '30d', ?string $startDate = null, ?string $endDate = null): array
    {
        // 1. Identity & System Health
        $totalUsers = User::count();
        $activeAdmins = User::whereHas('roles', function ($q) {
            $q->whereIn('slug', ['super-admin', 'admin']);
        })->count();

        // 2. Health Overview
        $overview = $this->systems->overview(true);
        $appsHealth = [];
        $healthyAppsCount = 0;
        $unhealthyAppsCount = 0;
        foreach ($overview['applications'] ?? [] as $app) {
            $status = $app['status'] ?? 'healthy';
            if (in_array($status, ['healthy', 'operational'], true)) {
                $healthyAppsCount++;
            } else {
                $unhealthyAppsCount++;
            }
            $appsHealth[$app['id']] = [
                'status' => $status,
                'probe_ms' => 5,
                'last_check' => now()->toIso8601String(),
                'status_reason' => $app['status_reason'] ?? null,
            ];
        }

        // 3. Queue & Operational Alerts
        $activeAlertsCount = Schema::hasTable('platform_alerts')
            ? PlatformAlert::whereIn('status', [AlertStatus::NEW, AlertStatus::ACKNOWLEDGED])->count()
            : 0;

        $failedJobsCount = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $failedJobsSamples = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->latest('failed_at')->take(5)->get()->map(function ($j) {
                return [
                    'id' => $j->id,
                    'connection' => $j->connection,
                    'queue' => $j->queue,
                    'failed_at' => $j->failed_at,
                    'exception_summary' => substr($j->exception ?? '', 0, 150),
                ];
            })
            : [];

        $recentAudits = Schema::hasTable('audit_logs')
            ? AuditLog::with('user:id,name,email')->latest()->take(5)->get()
            : [];

        // 4. Domain Telemetry Summaries
        $emsData = $this->emsIntelligence->getAnalytics($period, $startDate, $endDate);
        $donationData = $this->donationIntelligence->getAnalytics($period, $startDate, $endDate);
        $storeData = $this->storeIntelligence->getAnalytics($period, $startDate, $endDate);
        $mlibmsData = $this->mlibmsIntelligence->getAnalytics($period, $startDate, $endDate);
        $commsData = $this->communicationsIntelligence->getAnalytics($period, $startDate, $endDate);
        $volunteerData = $this->volunteerIntelligence->getAnalytics($period, $startDate, $endDate);
        $feedbackData = $this->feedbackIntelligence->getAnalytics($period, $startDate, $endDate);

        return [
            'overall_health' => [
                'status' => $unhealthyAppsCount > 0 ? 'degraded' : 'healthy',
                'total_apps' => count($appsHealth) ?: 9,
                'healthy_apps' => $healthyAppsCount ?: 9,
                'unhealthy_apps' => $unhealthyAppsCount,
            ],
            'apps' => $appsHealth,
            'platform' => [
                'total_users' => $totalUsers,
                'privileged_admins' => $activeAdmins,
            ],
            'failed_jobs_count' => $failedJobsCount,
            'failed_jobs_samples' => $failedJobsSamples,
            'active_alerts_count' => $activeAlertsCount,
            'recent_audits' => $recentAudits,
            'domains' => [
                'ems' => $emsData,
                'donations' => $donationData,
                'store' => $storeData,
                'mlibms' => $mlibmsData,
                'communications' => $commsData,
                'volunteers' => $volunteerData,
                'feedback' => $feedbackData,
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Backwards-compatible telemetry probe for platform endpoints.
     */
    public function getCrossSystemTelemetry(): array
    {
        return $this->getDashboardTelemetry('30d');
    }
}
