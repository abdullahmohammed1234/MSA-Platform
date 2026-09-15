<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\OperationalActionExecution;
use App\Models\OperationalAlert;
use App\Services\Operations\Remediation\OperationalActionRegistry;
use App\Services\Operations\Remediation\OperationalGovernanceService;
use App\Services\Operations\Remediation\OperationalRemediationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class OperationsRemediationController extends Controller
{
    public function __construct(
        private OperationalActionRegistry $registry,
        private OperationalRemediationService $remediationService,
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

    private function checkExecutePermission(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (! $user || (! $user->hasPermission('platform.operations.execute') && ! $user->hasPermission('platform.operations') && ! $user->hasRole('super-admin') && ! $user->hasRole('admin'))) {
            return response()->json(['message' => 'Unauthorized: Required permission platform.operations.execute missing.'], 403);
        }

        return null;
    }

    /**
     * GET /api/v1/admin/operations/alerts/{id}/actions
     * Get available remediation actions and evaluated preconditions/governance for a specific alert.
     */
    public function getActionsForAlert(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->checkViewPermission($request)) {
            return $forbidden;
        }

        $alert = OperationalAlert::findOrFail($id);
        $actions = $this->registry->getActionsForAlert($alert);

        $actionsWithGovernance = array_map(function ($action) use ($alert, $request) {
            $eval = $this->governanceService->evaluateGovernance($alert, $action['key'], $request->user());
            $action['governance'] = $eval;
            return $action;
        }, $actions);

        return response()->json([
            'success' => true,
            'alert_id' => $alert->id,
            'rule_key' => $alert->rule_key,
            'actions' => $actionsWithGovernance,
        ]);
    }

    /**
     * POST /api/v1/admin/operations/alerts/{id}/actions/{actionKey}
     * Execute a remediation action for a specific alert under governance.
     */
    public function executeAction(Request $request, int $id, string $actionKey): JsonResponse
    {
        if ($forbidden = $this->checkExecutePermission($request)) {
            return $forbidden;
        }

        $alert = OperationalAlert::findOrFail($id);

        try {
            $execution = $this->remediationService->executeAction($alert, $actionKey, $request->user());

            $statusCode = $execution->status === 'completed' ? 200 : 422;

            return response()->json([
                'success' => $execution->status === 'completed',
                'message' => $execution->summary,
                'execution' => $execution->load(['alert', 'requester:id,name,email']),
            ], $statusCode);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /api/v1/admin/operations/executions
     * Paginated audit log of remediation action executions.
     */
    public function indexExecutions(Request $request): JsonResponse
    {
        if ($forbidden = $this->checkViewPermission($request)) {
            return $forbidden;
        }

        $query = OperationalActionExecution::query()
            ->with(['alert:id,title,category,severity,rule_key', 'requester:id,name,email']);

        if ($request->filled('alert_id')) {
            $query->where('operational_alert_id', $request->input('alert_id'));
        }

        if ($request->filled('action_key')) {
            $query->where('action_key', $request->input('action_key'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = max(1, min((int) $request->input('per_page', 25), 100));

        $executions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'executions' => $executions,
        ]);
    }

    /**
     * GET /api/v1/admin/operations/executions/{uuid}
     * Get details of a specific remediation execution by UUID.
     */
    public function showExecution(Request $request, string $uuid): JsonResponse
    {
        if ($forbidden = $this->checkViewPermission($request)) {
            return $forbidden;
        }

        $execution = OperationalActionExecution::with(['alert', 'requester:id,name,email'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'execution' => $execution,
        ]);
    }
}
