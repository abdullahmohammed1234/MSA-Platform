<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Systems\SystemsControlPlaneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Platform Systems control plane API (Phase 7).
 * Visibility + navigation only — not CMS/DAMS/EMS ownership.
 */
class PlatformSystemsController extends Controller
{
    public function __construct(
        private SystemsControlPlaneService $systems
    ) {}

    /**
     * GET /api/v1/admin/systems
     */
    public function index(Request $request): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $refresh = $request->boolean('refresh');
        $data = $this->systems->overview($refresh);

        return response()->json([
            'success' => true,
            ...$data,
        ]);
    }

    /**
     * GET /api/v1/admin/systems/registry/{system}
     * Uses /registry/ prefix so it does not collide with systems/cms legacy groups.
     */
    public function show(Request $request, string $system): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $app = $this->systems->application($system, $request->boolean('refresh'));
        if (! $app) {
            return response()->json(['message' => 'System not found in registry.'], 404);
        }

        return response()->json([
            'success' => true,
            'system' => $app,
        ]);
    }

    /**
     * GET /api/v1/admin/systems/registry/{system}/health
     */
    public function health(Request $request, string $system): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $health = $this->systems->applicationHealth($system, $request->boolean('refresh'));
        if (! $health) {
            return response()->json(['message' => 'System not found in registry.'], 404);
        }

        return response()->json([
            'success' => true,
            'health' => $health,
        ]);
    }

    /**
     * GET /api/v1/admin/systems/services/{service}
     */
    public function showService(Request $request, string $service): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $item = $this->systems->platformService($service, $request->boolean('refresh'));
        if (! $item) {
            return response()->json(['message' => 'Platform service not found in registry.'], 404);
        }

        return response()->json([
            'success' => true,
            'service' => $item,
        ]);
    }

    private function canView(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        return $user->hasPermission('system.view')
            || $user->hasRole('super-admin')
            || $user->hasRole('admin');
    }
}
