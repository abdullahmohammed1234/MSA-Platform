<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Platform\Models\PlatformHealthHistory;
use App\Services\Systems\SystemsControlPlaneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformHealthController extends Controller
{
    public function __construct(
        private SystemsControlPlaneService $systems
    ) {}

    /**
     * GET /api/v1/admin/platform/health/history
     */
    public function index(Request $request): JsonResponse
    {
        return $this->history($request);
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('platform.health') && ! $user->hasPermission('system.view') && ! $user->hasRole('super-admin') && ! $user->hasRole('admin'))) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $perPage = min(100, max(10, (int) $request->query('per_page', 20)));

        $histories = PlatformHealthHistory::orderBy('recorded_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $histories->items(),
            'total' => $histories->total(),
            'current_page' => $histories->currentPage(),
            'last_page' => $histories->lastPage(),
            'per_page' => $histories->perPage(),
        ]);
    }

    /**
     * POST /api/v1/admin/platform/health/snapshot
     */
    public function snapshot(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('platform.operations') && ! $user->hasPermission('platform.health') && ! $user->hasRole('super-admin') && ! $user->hasRole('admin'))) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $snapshot = $this->systems->recordSnapshot();

        $appsHealth = [];
        foreach ($snapshot->details['applications'] ?? [] as $app) {
            $appsHealth[$app['id']] = [
                'status' => $app['status'],
                'status_reason' => $app['status_reason'] ?? null,
                'probe_ms' => 5,
                'last_check' => $snapshot->recorded_at,
            ];
        }

        $servicesHealth = [];
        foreach ($snapshot->details['platform_services'] ?? [] as $svc) {
            $servicesHealth[$svc['id']] = [
                'status' => $svc['status'],
                'status_reason' => $svc['status_reason'] ?? null,
                'probe_ms' => 3,
                'last_check' => $snapshot->recorded_at,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Platform health snapshot recorded.',
            'data' => [
                'id' => $snapshot->id,
                'overall_status' => $snapshot->system_status->value ?? 'healthy',
                'apps_health' => $appsHealth,
                'services_health' => $servicesHealth,
                'recorded_at' => $snapshot->recorded_at,
            ],
            'snapshot' => $snapshot,
        ]);
    }
}
