<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Governance\AuditGovernanceService;
use App\Services\Governance\ContinuityReadinessService;
use App\Services\Governance\IncidentTimelineService;
use App\Services\Governance\IntegrityCheckService;
use App\Services\Governance\PlatformGovernanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlatformGovernanceController extends Controller
{
    public function __construct(
        private PlatformGovernanceService $governanceService,
        private AuditGovernanceService $auditService,
        private IncidentTimelineService $timelineService,
        private IntegrityCheckService $integrityService,
        private ContinuityReadinessService $continuityService
    ) {}

    /**
     * GET /api/v1/admin/governance
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('platform.governance.view') && ! $user->hasPermission('platform.view') && ! $user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.governance.view'], 403);
        }

        $period = (string) $request->query('period', '7d');
        $overview = $this->governanceService->getGovernanceOverview($period, $user);

        return response()->json([
            'success' => true,
            'data' => $overview,
        ]);
    }

    /**
     * GET /api/v1/admin/governance/audit
     */
    public function auditIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('platform.governance.audit') && ! $user->hasPermission('platform.audit') && ! $user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.governance.audit'], 403);
        }

        $filters = $request->only(['application', 'severity', 'action', 'user_id', 'target_type', 'target_id', 'search', 'start_date', 'end_date']);
        $perPage = (int) $request->query('per_page', 20);

        $paginator = $this->auditService->searchAuditLogs($filters, $perPage, $user);

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
        ]);
    }

    /**
     * GET /api/v1/admin/governance/audit/{id}
     */
    public function auditShow(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('platform.governance.audit') && ! $user->hasPermission('platform.audit') && ! $user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.governance.audit'], 403);
        }

        $log = AuditLog::with('user:id,name,email')->find($id);

        if (! $log) {
            return response()->json(['message' => 'Audit log record not found.'], 404);
        }

        if ($log->payload && is_array($log->payload)) {
            $log->payload = $this->auditService->sanitizePayload($log->payload);
        }

        return response()->json([
            'success' => true,
            'data' => $log,
        ]);
    }

    /**
     * GET /api/v1/admin/governance/incidents/{identifier}/timeline
     */
    public function timeline(Request $request, string $identifier): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('platform.governance.view') && ! $user->hasPermission('platform.operations') && ! $user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.governance.view'], 403);
        }

        $timelineResult = $this->timelineService->reconstructTimeline($identifier, $user);

        if (($timelineResult['status'] ?? '') === 'restricted') {
            return response()->json(['message' => $timelineResult['message']], 403);
        }

        if (($timelineResult['status'] ?? '') === 'not_found') {
            return response()->json(['message' => $timelineResult['message']], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $timelineResult,
        ]);
    }

    /**
     * GET /api/v1/admin/governance/integrity
     */
    public function integrity(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('platform.governance.integrity') && ! $user->hasPermission('platform.health') && ! $user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.governance.integrity'], 403);
        }

        $domain = $request->query('domain');
        $scan = $this->integrityService->runIntegrityScan($domain ? (string) $domain : null, $user);

        return response()->json([
            'success' => true,
            'data' => $scan,
        ]);
    }

    /**
     * GET /api/v1/admin/governance/continuity
     */
    public function continuity(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('platform.governance.continuity') && ! $user->hasPermission('platform.health') && ! $user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.governance.continuity'], 403);
        }

        $continuity = $this->continuityService->getContinuityReadiness();

        return response()->json([
            'success' => true,
            'data' => $continuity,
        ]);
    }

    /**
     * GET /api/v1/admin/governance/report
     */
    public function report(Request $request)
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('platform.governance.export') && ! $user->hasPermission('platform.governance.view') && ! $user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.governance.export'], 403);
        }

        $period = (string) $request->query('period', '30d');
        $format = (string) $request->query('format', 'json');

        $overview = $this->governanceService->getGovernanceOverview($period, $user);

        if ($format === 'csv') {
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"platform_governance_report_{$period}.csv\"",
            ];

            $callback = function () use ($overview) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Metric / Dimension', 'Value / Details']);
                fputcsv($file, ['Generated At', $overview['generated_at']]);
                fputcsv($file, ['Reporting Period', $overview['period']]);
                fputcsv($file, ['Governance Readiness Score', $overview['governance_score']]);
                fputcsv($file, ['Score Level', $overview['score_level']]);
                fputcsv($file, ['Data Integrity Status', $overview['integrity_status']['status'] ?? 'N/A']);
                fputcsv($file, ['Continuity Status', $overview['continuity_readiness']['overall_status'] ?? 'N/A']);
                fputcsv($file, ['Backup Verification Status', $overview['continuity_readiness']['probes']['backup_verification']['status'] ?? 'NOT_VERIFIED']);
                fputcsv($file, ['Pending Approvals Count', $overview['governance_backlog']['pending_approvals_count'] ?? 0]);

                fputcsv($file, []);
                fputcsv($file, ['Score Drivers', 'Deduction', 'Reason']);
                foreach ($overview['score_drivers'] as $driver) {
                    fputcsv($file, [$driver['category'], $driver['deduction'], $driver['reason']]);
                }

                fclose($file);
            };

            return new StreamedResponse($callback, 200, $headers);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'report_metadata' => [
                    'generated_at' => $overview['generated_at'],
                    'period' => $period,
                    'generated_by' => $user->name,
                ],
                'overview' => $overview,
            ],
        ]);
    }
}
