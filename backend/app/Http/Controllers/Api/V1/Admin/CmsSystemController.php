<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CMS\Announcement;
use App\Models\CMS\Media;
use App\Models\CMS\Resource;
use App\Models\CMS\TeamMember;
use App\Services\Systems\SystemsControlPlaneService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Platform Systems registry entry for the CMS application.
 */
class CmsSystemController extends Controller
{
    public function __construct(
        private SystemsControlPlaneService $systems
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $app = $this->systems->application('cms');

        return response()->json([
            'success' => true,
            'system' => [
                'name' => config('cms.application.name', 'Content Management System'),
                'slug' => config('cms.application.slug', 'cms'),
                'version' => $app['version'] ?? config('systems.applications.cms.version', 'unknown'),
                'status' => $app['status'] ?? SystemsControlPlaneService::STATUS_UNKNOWN,
                'frontend_url' => $app['launch_url'] ?? rtrim((string) config('app.frontend_url', ''), '/').'/cms',
                'owns' => config('cms.application.owns', []),
                'updated_at' => Carbon::now()->toIso8601String(),
            ],
        ]);
    }

    public function health(Request $request): JsonResponse
    {
        if (! $this->canView($request)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $health = $this->systems->applicationHealth('cms', $request->boolean('refresh'));

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
                'announcements' => Announcement::count(),
                'announcements_published' => Announcement::where('status', 'published')->count(),
                'team_members' => TeamMember::count(),
                'resources' => Resource::count(),
                'resources_published' => Resource::where('status', 'published')->count(),
                'media' => Media::count(),
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
