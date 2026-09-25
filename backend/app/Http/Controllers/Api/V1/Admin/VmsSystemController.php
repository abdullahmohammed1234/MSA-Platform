<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Shift;
use App\Volunteering\Models\Signup;
use App\Volunteering\Models\Team;
use App\Services\Systems\SystemsControlPlaneService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Platform Systems registry entry for the Volunteer Management System (VMS).
 */
class VmsSystemController extends Controller
{
    public function __construct(
        private SystemsControlPlaneService $systems
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $app = $this->systems->application('vms');

        return response()->json([
            'success' => true,
            'system' => [
                'name' => 'Volunteer Management System (VMS)',
                'slug' => 'vms',
                'version' => $app['version'] ?? config('systems.applications.vms.version', '1.0.0'),
                'status' => $app['status'] ?? SystemsControlPlaneService::STATUS_OPERATIONAL,
                'frontend_url' => $app['launch_url'] ?? rtrim((string) config('app.frontend_url', ''), '/').'/admin/volunteering',
                'owns' => config('systems.applications.vms.owns', ['volunteering_opportunities', 'volunteering_teams', 'volunteering_shifts', 'volunteering_signups']),
                'updated_at' => Carbon::now()->toIso8601String(),
            ],
        ]);
    }

    public function health(Request $request): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $health = $this->systems->applicationHealth('vms', $request->boolean('refresh'));

        return response()->json([
            'success' => true,
            'health' => [
                'status' => $health['status'] ?? SystemsControlPlaneService::STATUS_OPERATIONAL,
                'health_status' => $health['health_status'] ?? SystemsControlPlaneService::STATUS_OPERATIONAL,
                'connection_status' => $health['connection_status'] ?? [],
                'checks' => $health['checks'] ?? [],
                'errors' => $health['errors'] ?? [],
                'checked_at' => $health['last_checked_at'] ?? Carbon::now()->toIso8601String(),
            ],
        ]);
    }

    public function metrics(Request $request): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json([
            'success' => true,
            'metrics' => [
                'total_opportunities' => Opportunity::count(),
                'open_opportunities' => Opportunity::where('status', 'open')->count(),
                'total_teams' => Team::count(),
                'total_shifts' => Shift::count(),
                'total_signups' => Signup::count(),
                'active_signups' => Signup::whereIn('status', ['signed_up', 'confirmed', 'completed'])->count(),
                'completed_signups' => Signup::where('status', 'completed')->count(),
                'waitlisted_signups' => Signup::where('status', 'waitlisted')->count(),
            ],
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
