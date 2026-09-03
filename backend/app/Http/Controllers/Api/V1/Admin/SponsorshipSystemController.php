<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Systems\SystemsControlPlaneService;
use App\Spms\Models\InKindContribution;
use App\Spms\Models\Organization;
use App\Spms\Models\Sponsorship;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SponsorshipSystemController extends Controller
{
    public function __construct(
        private SystemsControlPlaneService $systems
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $app = $this->systems->application('sponsorship');

        return response()->json([
            'success' => true,
            'system' => [
                'name' => 'Sponsorship & Partnerships Management System',
                'slug' => 'sponsorship',
                'version' => $app['version'] ?? config('systems.applications.sponsorship.version', '1.0.0'),
                'status' => $app['status'] ?? SystemsControlPlaneService::STATUS_UNKNOWN,
                'frontend_url' => $app['launch_url'] ?? rtrim((string) config('app.frontend_url', ''), '/').'/sponsorship/admin',
                'owns' => config('systems.applications.sponsorship.owns', []),
                'updated_at' => Carbon::now()->toIso8601String(),
            ],
        ]);
    }

    public function health(Request $request): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $health = $this->systems->applicationHealth('sponsorship', $request->boolean('refresh'));

        return response()->json([
            'success' => true,
            'health' => [
                'status' => $health['status'] ?? SystemsControlPlaneService::STATUS_UNKNOWN,
                'health_status' => $health['health_status'] ?? SystemsControlPlaneService::STATUS_UNKNOWN,
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

        $totalCommitted = (int) Sponsorship::sum('total_committed_cents');
        $totalCollected = (int) Sponsorship::sum('total_paid_cents');
        $totalInKind = (int) InKindContribution::sum('estimated_value_cents');

        return response()->json([
            'success' => true,
            'metrics' => [
                'active_sponsorships' => Sponsorship::whereIn('status', ['active', 'approved'])->count(),
                'partner_organizations' => Organization::where('status', 'active')->count(),
                'total_committed_cents' => $totalCommitted,
                'total_collected_cents' => $totalCollected,
                'total_in_kind_cents' => $totalInKind,
                'outstanding_cents' => max(0, $totalCommitted - $totalCollected),
            ],
        ]);
    }

    private function canView(Request $request): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('super-admin') || $user->hasRole('admin') || $user->hasRole('spms-administrator')) {
            return true;
        }

        return $user->hasPermissionTo('view_systems_health') || $user->hasPermissionTo('sponsorship.view');
    }
}
