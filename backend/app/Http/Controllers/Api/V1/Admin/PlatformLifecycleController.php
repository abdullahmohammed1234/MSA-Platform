<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Lifecycle\PlatformLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformLifecycleController extends Controller
{
    public function __construct(
        private PlatformLifecycleService $lifecycleService
    ) {}

    /**
     * GET /api/v1/admin/lifecycle
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('platform.lifecycle.view') && !$user->hasPermission('platform.view') && !$user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.lifecycle.view'], 403);
        }

        $overview = $this->lifecycleService->getLifecycleOverview($user);

        return response()->json([
            'success' => true,
            'data' => $overview,
        ]);
    }

    /**
     * GET /api/v1/admin/lifecycle/environment
     */
    public function environment(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('platform.lifecycle.environment') && !$user->hasPermission('platform.lifecycle.view') && !$user->hasPermission('platform.view') && !$user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.lifecycle.environment'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'environment' => $this->lifecycleService->getEnvironmentInventory(),
                'release' => $this->lifecycleService->getReleaseMetadata(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/lifecycle/release
     */
    public function release(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('platform.lifecycle.view') && !$user->hasPermission('platform.view') && !$user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.lifecycle.view'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->lifecycleService->getReleaseMetadata(),
        ]);
    }

    /**
     * GET /api/v1/admin/lifecycle/dependencies
     */
    public function dependencies(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('platform.lifecycle.view') && !$user->hasPermission('platform.view') && !$user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.lifecycle.view'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->lifecycleService->getDependencyHealth(),
        ]);
    }

    /**
     * GET /api/v1/admin/lifecycle/migrations
     */
    public function migrations(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('platform.lifecycle.view') && !$user->hasPermission('platform.view') && !$user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.lifecycle.view'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->lifecycleService->getMigrationStatus(),
        ]);
    }

    /**
     * GET /api/v1/admin/lifecycle/scheduler
     */
    public function scheduler(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('platform.lifecycle.view') && !$user->hasPermission('platform.view') && !$user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.lifecycle.view'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->lifecycleService->getSchedulerQueueInventory(),
        ]);
    }

    /**
     * GET /api/v1/admin/lifecycle/drift
     */
    public function drift(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('platform.lifecycle.configuration') && !$user->hasPermission('platform.lifecycle.view') && !$user->hasPermission('platform.view') && !$user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.lifecycle.configuration'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->lifecycleService->detectConfigurationDrift(),
        ]);
    }

    /**
     * GET /api/v1/admin/lifecycle/readiness
     */
    public function readiness(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermission('platform.lifecycle.readiness') && !$user->hasPermission('platform.lifecycle.view') && !$user->hasPermission('platform.view') && !$user->hasAnyRole(['super-admin', 'admin']))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.lifecycle.readiness'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->lifecycleService->evaluateReadiness(),
        ]);
    }
}
