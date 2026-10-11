<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MemberDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberDashboardController extends Controller
{
    protected MemberDashboardService $dashboardService;

    public function __construct(MemberDashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Get member dashboard aggregated data for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $this->dashboardService->getDashboardData($user);

        return response()->json($data);
    }
}
