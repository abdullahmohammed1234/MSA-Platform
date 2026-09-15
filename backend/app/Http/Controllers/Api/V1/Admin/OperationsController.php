<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\OperationalAlert;
use App\Services\Operations\OperationalAlertService;
use App\Services\Operations\OperationalDetectionEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class OperationsController extends Controller
{
    public function __construct(
        private OperationalAlertService $alertService,
        private OperationalDetectionEngine $detectionEngine
    ) {}

    private function checkPermission(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (! $user || (! $user->hasPermission('platform.operations') && ! $user->hasPermission('platform.view') && ! $user->hasRole('super-admin') && ! $user->hasRole('admin'))) {
            return response()->json(['message' => 'Unauthorized access to Operations Action Center.'], 403);
        }

        return null;
    }

    /**
     * GET /api/v1/admin/operations
     * Paginated list of operational alerts with status, category, severity, and search filtering.
     */
    public function index(Request $request): JsonResponse
    {
        if ($forbidden = $this->checkPermission($request)) {
            return $forbidden;
        }

        $query = OperationalAlert::query()
            ->with(['acknowledgedBy:id,name,email', 'resolvedBy:id,name,email', 'dismissedBy:id,name,email']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhere('rule_key', 'like', $search);
            });
        }

        $perPage = max(1, min((int) $request->input('per_page', 25), 100));

        $alerts = $query->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->orderBy('last_detected_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'alerts' => $alerts,
        ]);
    }

    /**
     * GET /api/v1/admin/operations/summary
     * Summary KPI metrics for operational alerts.
     */
    public function summary(Request $request): JsonResponse
    {
        if ($forbidden = $this->checkPermission($request)) {
            return $forbidden;
        }

        $countsByStatus = [
            'open' => OperationalAlert::where('status', 'open')->count(),
            'acknowledged' => OperationalAlert::where('status', 'acknowledged')->count(),
            'resolved' => OperationalAlert::where('status', 'resolved')->count(),
            'dismissed' => OperationalAlert::where('status', 'dismissed')->count(),
        ];

        $countsBySeverity = [
            'critical' => OperationalAlert::where('status', 'open')->where('severity', 'critical')->count(),
            'high' => OperationalAlert::where('status', 'open')->where('severity', 'high')->count(),
            'medium' => OperationalAlert::where('status', 'open')->where('severity', 'medium')->count(),
            'low' => OperationalAlert::where('status', 'open')->where('severity', 'low')->count(),
        ];

        $countsByCategory = OperationalAlert::whereIn('status', ['open', 'acknowledged'])
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->toArray();

        return response()->json([
            'success' => true,
            'summary' => [
                'total_active' => $countsByStatus['open'] + $countsByStatus['acknowledged'],
                'by_status' => $countsByStatus,
                'by_severity' => $countsBySeverity,
                'by_category' => $countsByCategory,
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/operations/detect
     * Trigger execution of all operational rules across subsystems.
     */
    public function runDetection(Request $request): JsonResponse
    {
        if ($forbidden = $this->checkPermission($request)) {
            return $forbidden;
        }

        $report = $this->detectionEngine->runAll();

        return response()->json([
            'success' => true,
            'message' => "Operational detection completed. {$report['total_detected']} issue(s) evaluated.",
            'report' => $report,
        ]);
    }

    /**
     * GET /api/v1/admin/operations/alerts/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->checkPermission($request)) {
            return $forbidden;
        }

        $alert = OperationalAlert::with(['acknowledgedBy:id,name,email', 'resolvedBy:id,name,email', 'dismissedBy:id,name,email'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'alert' => $alert,
        ]);
    }

    /**
     * POST /api/v1/admin/operations/alerts/{id}/acknowledge
     */
    public function acknowledge(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->checkPermission($request)) {
            return $forbidden;
        }

        $alert = OperationalAlert::findOrFail($id);

        try {
            $updated = $this->alertService->transitionStatus($alert, 'acknowledged', $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Operational alert acknowledged.',
                'alert' => $updated,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/admin/operations/alerts/{id}/resolve
     */
    public function resolve(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->checkPermission($request)) {
            return $forbidden;
        }

        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $alert = OperationalAlert::findOrFail($id);

        try {
            $updated = $this->alertService->transitionStatus(
                $alert,
                'resolved',
                $request->user(),
                $request->input('reason')
            );

            return response()->json([
                'success' => true,
                'message' => 'Operational alert resolved.',
                'alert' => $updated,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/admin/operations/alerts/{id}/dismiss
     */
    public function dismiss(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->checkPermission($request)) {
            return $forbidden;
        }

        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $alert = OperationalAlert::findOrFail($id);

        try {
            $updated = $this->alertService->transitionStatus(
                $alert,
                'dismissed',
                $request->user(),
                $request->input('reason')
            );

            return response()->json([
                'success' => true,
                'message' => 'Operational alert dismissed.',
                'alert' => $updated,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /api/v1/admin/operations/alerts/{id}/reopen
     */
    public function reopen(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->checkPermission($request)) {
            return $forbidden;
        }

        $alert = OperationalAlert::findOrFail($id);

        try {
            $updated = $this->alertService->transitionStatus($alert, 'open', $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Operational alert reopened.',
                'alert' => $updated,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
