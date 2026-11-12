<?php

namespace App\Pm\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Pm\Models\PmProject;
use App\Pm\Services\PmAnalyticsService;
use App\Pm\Services\ProjectAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PmAnalyticsController extends Controller
{
    public function __construct(
        protected PmAnalyticsService $analyticsService,
        protected ProjectAccessService $accessService
    ) {}

    /**
     * Get detailed analytics for a specific project.
     */
    public function projectAnalytics(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::where('uuid', $uuid)->orWhere('id', $uuid)->firstOrFail();

        if (!$this->accessService->canViewProject($user, $project)) {
            abort(403, 'Unauthorized.');
        }

        $analytics = $this->analyticsService->getProjectAnalytics($project, $user);

        return response()->json([
            'status' => 'success',
            'data' => $analytics,
        ]);
    }

    /**
     * Get aggregated analytics for a department (scoped strictly to accessible projects).
     */
    public function departmentAnalytics(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $analytics = $this->analyticsService->getDepartmentAnalytics($id, $user);

        return response()->json([
            'status' => 'success',
            'data' => $analytics,
        ]);
    }

    /**
     * Get cross-project summary analytics for dashboard.
     */
    public function dashboardAnalytics(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $analytics = $this->analyticsService->getDashboardAnalytics($user);

        return response()->json([
            'status' => 'success',
            'data' => $analytics,
        ]);
    }
}

