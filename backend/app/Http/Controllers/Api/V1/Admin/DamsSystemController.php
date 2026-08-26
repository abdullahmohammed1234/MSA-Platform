<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Services\Systems\SystemsControlPlaneService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Platform Systems registry entry for DAMS (distinct from learner Dawah Academy).
 */
class DamsSystemController extends Controller
{
    public function __construct(
        private SystemsControlPlaneService $systems
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $app = $this->systems->application('dams');

        return response()->json([
            'success' => true,
            'system' => [
                'name' => config('dams.application.name', 'Dawah Academy Management System'),
                'slug' => config('dams.application.slug', 'dams'),
                'version' => $app['version'] ?? config('systems.applications.dams.version', 'unknown'),
                'status' => $app['status'] ?? SystemsControlPlaneService::STATUS_UNKNOWN,
                'frontend_url' => $app['launch_url'] ?? rtrim((string) config('app.frontend_url', ''), '/').'/dams',
                'api_prefix' => config('dams.application.api_prefix'),
                'owns_operations' => config('dams.application.owns_operations', []),
                'updated_at' => Carbon::now()->toIso8601String(),
            ],
        ]);
    }

    public function health(Request $request): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $health = $this->systems->applicationHealth('dams', $request->boolean('refresh'));

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

        return response()->json([
            'success' => true,
            'metrics' => [
                'courses' => Course::count(),
                'courses_published' => Course::where('status', 'published')->count(),
                'quizzes' => Quiz::count(),
                'enrollments' => Enrollment::count(),
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
