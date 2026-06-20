<?php

namespace App\Http\Controllers\Admin\CMS;

use App\Http\Controllers\Controller;
use App\Models\CMS\Announcement;
use App\Models\CMS\Event;
use App\Models\CMS\TeamMember;
use App\Models\CMS\Resource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;

class CmsDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Announcement::class); // Basic CMS authorize check

        $stats = [
            'announcements' => [
                'total' => Announcement::count(),
                'drafts' => Announcement::where('status', 'draft')->count(),
                'published' => Announcement::where('status', 'published')->count(),
            ],
            'events' => [
                'total' => Event::count(),
                'upcoming' => Event::where('start_date', '>=', now())->count(),
                'past' => Event::where('start_date', '<', now())->count(),
            ],
            'team' => [
                'total' => TeamMember::count(),
            ],
            'resources' => [
                'total' => Resource::count(),
            ],
        ];

        // Fetch recent CMS-related logs
        $recentLogs = AuditLog::with('user:id,name')
            ->whereIn('action', [
                'create_announcement', 'update_announcement', 'delete_announcement',
                'create_event', 'update_event', 'delete_event',
                'create_team_member', 'update_team_member', 'delete_team_member', 'reorder_team',
                'create_resource', 'update_resource', 'delete_resource',
                'update_homepage', 'rollback', 'upload_media', 'delete_media'
            ])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'stats' => $stats,
            'recentLogs' => $recentLogs
        ]);
    }
}
