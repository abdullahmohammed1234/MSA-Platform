<?php

namespace App\Pm\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Pm\Models\PmCalendarFeed;
use App\Pm\Models\PmMilestone;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
use App\Pm\Services\PmICalendarService;
use App\Pm\Services\ProjectAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PmCalendarFeedController extends Controller
{
    public function __construct(
        protected ProjectAccessService $accessService
    ) {}

    /**
     * List all active calendar feeds for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $feeds = PmCalendarFeed::query()
            ->where('user_id', $user->id)
            ->active()
            ->with(['project:id,uuid,name,code'])
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $feeds->map(function (PmCalendarFeed $feed) {
            return [
                'id' => $feed->id,
                'uuid' => $feed->uuid,
                'name' => $feed->name,
                'project' => $feed->project ? [
                    'id' => $feed->project->id,
                    'uuid' => $feed->project->uuid,
                    'name' => $feed->project->name,
                    'code' => $feed->project->code,
                ] : null,
                'include_tasks' => $feed->include_tasks,
                'include_milestones' => $feed->include_milestones,
                'include_project_deadlines' => $feed->include_project_deadlines,
                'only_assigned_to_me' => $feed->only_assigned_to_me,
                'last_accessed_at' => $feed->last_accessed_at?->toIso8601String(),
                'created_at' => $feed->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Create a new secure calendar subscription feed with one-time plain token disclosure.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'project_uuid' => 'nullable|string|exists:pm_projects,uuid',
            'include_tasks' => 'nullable|boolean',
            'include_milestones' => 'nullable|boolean',
            'include_project_deadlines' => 'nullable|boolean',
            'only_assigned_to_me' => 'nullable|boolean',
        ]);

        $project = null;
        if (!empty($validated['project_uuid'])) {
            $project = PmProject::where('uuid', $validated['project_uuid'])->firstOrFail();
            if (!$this->accessService->canViewProject($user, $project)) {
                abort(403, 'You do not have permission to subscribe to this project.');
            }
        }

        // Generate high-entropy 256-bit token (64 hex characters)
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        $feed = PmCalendarFeed::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'project_id' => $project?->id,
            'token_hash' => $tokenHash,
            'name' => $validated['name'],
            'include_tasks' => $validated['include_tasks'] ?? true,
            'include_milestones' => $validated['include_milestones'] ?? true,
            'include_project_deadlines' => $validated['include_project_deadlines'] ?? true,
            'only_assigned_to_me' => $validated['only_assigned_to_me'] ?? false,
        ]);

        $httpsUrl = url("/api/v1/pm/calendar/feed/{$plainToken}.ics");
        $webcalUrl = preg_replace('/^https?:\/\//i', 'webcal://', $httpsUrl);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $feed->id,
                'uuid' => $feed->uuid,
                'name' => $feed->name,
                'project' => $project ? [
                    'id' => $project->id,
                    'uuid' => $project->uuid,
                    'name' => $project->name,
                    'code' => $project->code,
                ] : null,
                'include_tasks' => $feed->include_tasks,
                'include_milestones' => $feed->include_milestones,
                'include_project_deadlines' => $feed->include_project_deadlines,
                'only_assigned_to_me' => $feed->only_assigned_to_me,
                'plain_token' => $plainToken,
                'webcal_url' => $webcalUrl,
                'https_url' => $httpsUrl,
                'created_at' => $feed->created_at?->toIso8601String(),
            ],
            'message' => 'Calendar subscription feed created. Store this URL securely; the plain token cannot be retrieved again.',
        ], 201);
    }

    /**
     * Revoke an existing calendar subscription feed.
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $feed = PmCalendarFeed::where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $feed->revoke();

        return response()->json([
            'success' => true,
            'message' => 'Feed subscription revoked successfully.',
        ]);
    }

    /**
     * Public RFC 5545 calendar feed subscription endpoint authenticated by token hash.
     */
    public function feed(Request $request, string $token, PmICalendarService $iCalService): Response
    {
        // Strip optional trailing .ics extension if received in token param
        $cleanToken = preg_replace('/\.ics$/i', '', $token);
        $tokenHash = hash('sha256', $cleanToken);

        /** @var PmCalendarFeed|null $feed */
        $feed = PmCalendarFeed::where('token_hash', $tokenHash)
            ->with(['user', 'project'])
            ->first();

        if (!$feed || $feed->isRevoked()) {
            abort(404, 'Calendar feed not found or revoked.');
        }

        $user = $feed->user;
        if (!$user || !$user->is_active) {
            abort(404, 'Calendar feed owner is inactive.');
        }

        if (!$this->accessService->canAccessProjects($user)) {
            abort(403, 'Calendar feed owner no longer has PMS access.');
        }

        // Determine accessible projects
        if ($feed->project_id) {
            if (!$feed->project || !$this->accessService->canViewProject($user, $feed->project)) {
                abort(403, 'Project is no longer accessible.');
            }
            $accessibleProjects = collect([$feed->project]);
            $accessibleProjectIds = [$feed->project->id];
        } else {
            $accessibleProjects = PmProject::query()->accessibleTo($user)->get();
            $accessibleProjectIds = $accessibleProjects->pluck('id')->all();
        }

        // 1. Project deadlines
        $projects = collect();
        if ($feed->include_project_deadlines) {
            $projects = $accessibleProjects->filter(fn ($p) => !empty($p->due_date));
        }

        // 2. Milestones
        $milestones = collect();
        if ($feed->include_milestones && !empty($accessibleProjectIds)) {
            $milestones = PmMilestone::whereIn('project_id', $accessibleProjectIds)
                ->whereNotNull('due_date')
                ->get();
        }

        // 3. Tasks
        $tasks = collect();
        if ($feed->include_tasks && !empty($accessibleProjectIds)) {
            $tasksQuery = PmTask::whereIn('project_id', $accessibleProjectIds)
                ->whereNotNull('due_date')
                ->with(['assignee:id,name,email,avatar', 'assignees:id,name,email,avatar']);

            if ($feed->only_assigned_to_me) {
                $tasksQuery->where(function ($q) use ($user) {
                    $q->where('assigned_to', $user->id)
                        ->orWhereHas('assignees', fn ($sq) => $sq->where('users.id', $user->id));
                });
            }

            $tasks = $tasksQuery->get();
        }

        // Record feed access timestamp and IP
        $feed->recordAccess($request->ip());

        $icsContent = $iCalService->generate($projects, $milestones, $tasks, $feed->name);

        return response($icsContent, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="feed.ics"',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }
}

