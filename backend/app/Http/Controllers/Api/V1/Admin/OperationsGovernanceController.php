<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\OperationalActionApproval;
use App\Models\OperationalAlert;
use App\Services\Operations\Remediation\OperationalGovernanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class OperationsGovernanceController extends Controller
{
    public function __construct(
        private OperationalGovernanceService $governanceService
    ) {}

    private function checkViewPermission(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (! $user || (! $user->hasPermission('platform.operations') && ! $user->hasPermission('platform.view') && ! $user->hasRole('super-admin') && ! $user->hasRole('admin'))) {
            return response()->json(['message' => 'Unauthorized access to Operations Action Center.'], 403);
        }

        return null;
    }

    private function checkApprovePermission(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (! $user || (! $user->hasPermission('platform.operations.approve') && ! $user->hasPermission('platform.operations') && ! $user->hasRole('super-admin') && ! $user->hasRole('admin'))) {
            return response()->json(['message' => 'Unauthorized: Required permission platform.operations.approve missing.'], 403);
        }

        return null;
    }

    /**
     * GET /api/v1/admin/operations/governance/health
     * Automation health & remediation effectiveness metrics.
     */
    public function getHealthMetrics(Request $request): JsonResponse
    {
        if ($forbidden = $this->checkViewPermission($request)) {
            return $forbidden;
        }

        $metrics = $this->governanceService->getAutomationHealthMetrics();

        return response()->json([
            'success' => true,
            'metrics' => $metrics,
        ]);
    }

    /**
     * GET /api/v1/admin/operations/approvals
     * Paginated list of remediation action approval requests.
     */
    public function indexApprovals(Request $request): JsonResponse
    {
        if ($forbidden = $this->checkViewPermission($request)) {
            return $forbidden;
        }

        $query = OperationalActionApproval::query()
            ->with(['alert:id,title,category,severity,rule_key', 'requestedBy:id,name,email', 'approvedBy:id,name,email', 'rejectedBy:id,name,email']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('alert_id')) {
            $query->where('operational_alert_id', $request->input('alert_id'));
        }

        if ($request->filled('action_key')) {
            $query->where('action_key', $request->input('action_key'));
        }

        $perPage = max(1, min((int) $request->input('per_page', 20), 100));

        $approvals = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'approvals' => $approvals,
        ]);
    }

    /**
     * POST /api/v1/admin/operations/alerts/{id}/actions/{actionKey}/request-approval
     * Request elevated approval for a governed remediation action.
     */
    public function requestApproval(Request $request, int $id, string $actionKey): JsonResponse
    {
        if ($forbidden = $this->checkViewPermission($request)) {
            return $forbidden;
        }

        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $alert = OperationalAlert::findOrFail($id);

        try {
            $approval = $this->governanceService->requestApproval(
                $alert,
                $actionKey,
                $request->user(),
                $request->input('reason')
            );

            return response()->json([
                'success' => true,
                'message' => 'Remediation action approval requested.',
                'approval' => $approval->load(['alert', 'requestedBy:id,name,email']),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/v1/admin/operations/approvals/{uuid}/approve
     * Approve a pending remediation action request with Separation of Duties enforcement.
     */
    public function approve(Request $request, string $uuid): JsonResponse
    {
        if ($forbidden = $this->checkApprovePermission($request)) {
            return $forbidden;
        }

        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $approval = OperationalActionApproval::where('uuid', $uuid)->firstOrFail();

        try {
            $approved = $this->governanceService->approveRequest(
                $approval,
                $request->user(),
                $request->input('reason')
            );

            return response()->json([
                'success' => true,
                'message' => 'Remediation approval granted.',
                'approval' => $approved->load(['alert', 'requestedBy:id,name,email', 'approvedBy:id,name,email']),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/v1/admin/operations/approvals/{uuid}/reject
     * Reject a pending remediation action request.
     */
    public function reject(Request $request, string $uuid): JsonResponse
    {
        if ($forbidden = $this->checkApprovePermission($request)) {
            return $forbidden;
        }

        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $approval = OperationalActionApproval::where('uuid', $uuid)->firstOrFail();

        try {
            $rejected = $this->governanceService->rejectRequest(
                $approval,
                $request->user(),
                $request->input('reason')
            );

            return response()->json([
                'success' => true,
                'message' => 'Remediation approval rejected.',
                'approval' => $rejected->load(['alert', 'requestedBy:id,name,email', 'rejectedBy:id,name,email']),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
