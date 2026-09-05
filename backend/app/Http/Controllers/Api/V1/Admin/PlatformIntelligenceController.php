<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformIntelligenceController extends Controller
{
    public function __construct(
        private PlatformIntelligenceService $intelligenceService
    ) {}

    /**
     * GET /api/v1/admin/platform/intelligence/metrics
     */
    public function metrics(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasPermission('platform.view') && ! $user->hasRole('super-admin') && ! $user->hasRole('admin'))) {
            return response()->json(['message' => 'Unauthorized. Required permission: platform.view'], 403);
        }

        $metrics = $this->intelligenceService->getCrossSystemTelemetry();

        return response()->json([
            'success' => true,
            'metrics' => $metrics,
        ]);
    }
}
