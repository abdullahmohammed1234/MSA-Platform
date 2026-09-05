<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Mlibms\Models\Book;
use App\Mlibms\Models\Copy;
use App\Mlibms\Models\Loan;
use App\Mlibms\Models\Member;
use App\Mlibms\Models\Reservation;
use App\Services\Systems\SystemsControlPlaneService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Platform Systems registry entry for the MSA Library Management System (MLibMS).
 */
class MlibmsSystemController extends Controller
{
    public function __construct(
        private SystemsControlPlaneService $systems
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $app = $this->systems->application('mlibms');

        return response()->json([
            'success' => true,
            'system' => [
                'name' => 'MSA Library Management System (MLibMS)',
                'slug' => 'mlibms',
                'version' => $app['version'] ?? config('systems.applications.mlibms.version', '1.0.0'),
                'status' => $app['status'] ?? SystemsControlPlaneService::STATUS_HEALTHY,
                'frontend_url' => $app['launch_url'] ?? rtrim((string) config('app.frontend_url', ''), '/').'/library/admin',
                'owns' => config('systems.applications.mlibms.owns', ['mlibms_books', 'mlibms_copies', 'mlibms_loans', 'mlibms_members', 'mlibms_reservations']),
                'updated_at' => Carbon::now()->toIso8601String(),
            ],
        ]);
    }

    public function health(Request $request): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $health = $this->systems->applicationHealth('mlibms', $request->boolean('refresh'));

        return response()->json([
            'success' => true,
            'health' => [
                'status' => $health['status'] ?? SystemsControlPlaneService::STATUS_HEALTHY,
                'health_status' => $health['health_status'] ?? SystemsControlPlaneService::STATUS_HEALTHY,
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
                'total_books' => Book::count(),
                'total_copies' => Copy::count(),
                'available_copies' => Copy::where('status', 'available')->count(),
                'active_loans' => Loan::where('status', 'active')->count(),
                'overdue_loans' => Loan::where('status', 'overdue')->count(),
                'active_holds' => Reservation::whereIn('status', ['pending', 'ready_for_pickup'])->count(),
                'total_members' => Member::count(),
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
