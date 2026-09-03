<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Donations\Enums\DonationStatus;
use App\Donations\Models\Donation;
use App\Http\Controllers\Controller;
use App\Services\Systems\SystemsControlPlaneService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DonationsSystemController extends Controller
{
    public function __construct(
        private SystemsControlPlaneService $systems
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $app = $this->systems->application('donations');

        return response()->json([
            'success' => true,
            'system' => [
                'name' => 'Donation Management System',
                'slug' => 'donations',
                'version' => $app['version'] ?? config('systems.applications.donations.version', '1.0.0'),
                'status' => $app['status'] ?? SystemsControlPlaneService::STATUS_UNKNOWN,
                'frontend_url' => $app['launch_url'] ?? rtrim((string) config('app.frontend_url', ''), '/').'/donations/admin',
                'owns' => config('systems.applications.donations.owns', ['donations', 'donation_refunds']),
                'updated_at' => Carbon::now()->toIso8601String(),
            ],
        ]);
    }

    public function health(Request $request): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $health = $this->systems->applicationHealth('donations', $request->boolean('refresh'));

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

        $paidCount = Donation::paid()->count();
        $paidCents = Donation::paid()->sum('amount_cents');

        return response()->json([
            'success' => true,
            'metrics' => [
                'total_donations' => Donation::count(),
                'paid_donations' => $paidCount,
                'pending_donations' => Donation::pending()->count(),
                'refunded_donations' => Donation::where('status', DonationStatus::Refunded->value)->count(),
                'total_revenue_cents' => (int) $paidCents,
                'total_revenue_formatted' => '$'.number_format($paidCents / 100, 2).' CAD',
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
