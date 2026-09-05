<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformAlertController extends Controller
{
    public function __construct(
        private PlatformAlertService $alertService
    ) {}

    /**
     * GET /api/v1/admin/platform/alerts
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('platform.alerts') && ! $user->hasPermission('system.view') && ! $user->hasRole('super-admin') && ! $user->hasRole('admin'))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.alerts'], 403);
        }

        $filters = $request->only(['status', 'severity', 'application']);
        $perPage = (int) $request->query('per_page', 20);

        $paginator = $this->alertService->getAlerts($filters, $perPage);

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
     * POST /api/v1/admin/platform/alerts/{id}/acknowledge
     */
    public function acknowledge(Request $request, int|string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('platform.alerts') && ! $user->hasRole('super-admin') && ! $user->hasRole('admin'))) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $alert = $this->alertService->acknowledgeAlert($id, $user->id);

        return response()->json([
            'success' => $alert !== null,
            'message' => $alert ? 'Alert acknowledged.' : 'Failed to acknowledge alert.',
            'data' => $alert,
        ]);
    }

    /**
     * POST /api/v1/admin/platform/alerts/{id}/resolve
     */
    public function resolve(Request $request, int|string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('platform.alerts') && ! $user->hasRole('super-admin') && ! $user->hasRole('admin'))) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $alert = $this->alertService->resolveAlert($id, $user->id);

        return response()->json([
            'success' => $alert !== null,
            'message' => $alert ? 'Alert resolved.' : 'Failed to resolve alert.',
            'data' => $alert,
        ]);
    }
}
