<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Http\Resources\ActivityLogResource;
use App\Ems\Http\Resources\EventResource;
use App\Ems\Models\Event;
use App\Ems\Services\DashboardService;
use App\Ems\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends EmsController
{
    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    /**
     * GET /api/v1/ems/dashboard
     *
     * Everything is scoped to the caller, so an organizer's counters describe
     * their own events rather than the whole programme.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Event::class);

        $user = $request->user();

        return ApiResponse::success([
            'summary' => $this->dashboard->summary($user),
            'upcoming_events' => EventResource::collection($this->dashboard->upcomingEvents($user))->resolve(),
            'recent_activity' => ActivityLogResource::collection($this->dashboard->recentActivity($user))->resolve(),
            'quick_actions' => $this->dashboard->quickActions($user),
        ], 'Dashboard retrieved successfully.');
    }
}
