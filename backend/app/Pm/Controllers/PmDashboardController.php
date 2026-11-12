<?php

namespace App\Pm\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Pm\Enums\ProjectStatus;
use App\Pm\Enums\ProjectVisibility;
use App\Pm\Enums\TaskStatus;
use App\Pm\Models\PmActivityLog;
use App\Pm\Models\PmDepartmentMembership;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmProjectMember;
use App\Pm\Models\PmTask;
use App\Pm\Services\DepartmentMembershipService;
use App\Pm\Services\ProjectAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PmDashboardController extends Controller
{
    public function __construct(
        protected ProjectAccessService $accessService,
        protected DepartmentMembershipService $deptService
    ) {}

    /**
     * PMS Home / Dashboard overview metrics and personalized data.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$this->accessService->canAccessProjects($user)) {
            abort(403, 'Unauthorized.');
        }

        // 1. Get accessible department IDs and projects
        $deptIds = $this->deptService->getUserDepartmentIds($user);
        $memberProjectIds = PmProjectMember::query()
            ->where('user_id', $user->id)
            ->accepted()
            ->pluck('project_id')
            ->all();

        $accessibleProjectQuery = PmProject::query()->accessibleTo($user);

        $accessibleProjectIds = (clone $accessibleProjectQuery)->pluck('id')->all();

        // 2. Metrics & KPI counts
        $totalAccessibleProjects = count($accessibleProjectIds);
        $activeProjectsCount = (clone $accessibleProjectQuery)
            ->whereIn('status', [ProjectStatus::InProgress, 'in_progress', ProjectStatus::Planning, 'planning'])
            ->count();

        $completedProjectsCount = (clone $accessibleProjectQuery)
            ->whereIn('status', [ProjectStatus::Completed, 'completed'])
            ->count();

        // My tasks
        $myOpenTasksQuery = PmTask::query()
            ->whereIn('project_id', $accessibleProjectIds)
            ->where('assigned_to', $user->id)
            ->whereNotIn('status', [TaskStatus::Completed, 'completed', TaskStatus::Cancelled, 'cancelled']);

        $myOpenTasksCount = (clone $myOpenTasksQuery)->count();

        $today = now()->toDateString();
        $myOverdueTasksCount = (clone $myOpenTasksQuery)
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->count();

        // 3. User's active department memberships
        $memberships = PmDepartmentMembership::query()
            ->where('user_id', $user->id)
            ->active()
            ->with(['department:id,name,slug,color'])
            ->get()
            ->map(fn ($m) => [
                'id' => $m->department_id,
                'department_id' => $m->department_id,
                'name' => $m->department?->name,
                'department_name' => $m->department?->name,
                'department_slug' => $m->department?->slug,
                'slug' => $m->department?->slug,
                'code' => strtoupper(substr($m->department?->slug ?? '', 0, 3)),
                'department_code' => strtoupper(substr($m->department?->slug ?? '', 0, 3)),
                'color' => $m->department?->color,
                'role' => $m->department_role instanceof \BackedEnum ? $m->department_role->value : $m->department_role,
            ]);

        // 4. My upcoming tasks (top 8)
        $myUpcomingTasks = (clone $myOpenTasksQuery)
            ->with([
                'project:id,uuid,name,slug',
                'milestone:id,title',
            ])
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date ASC')
            ->limit(8)
            ->get();

        // 5. Recent accessible projects (top 6)
        $recentProjects = (clone $accessibleProjectQuery)
            ->with([
                'department:id,name,slug,color',
                'owner:id,name,email,avatar',
            ])
            ->withCount(['tasks', 'milestones'])
            ->orderBy('updated_at', 'desc')
            ->limit(6)
            ->get();

        // 6. Recent activity log stream (top 15)
        $recentActivity = PmActivityLog::query()
            ->whereIn('project_id', $accessibleProjectIds)
            ->with(['user:id,name,email,avatar', 'project:id,uuid,name,slug'])
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'is_admin' => $user->hasRole('super-admin') || $user->hasRole('pms-administrator'),
                ],
                'departments' => $memberships,
                'kpi' => [
                    'total_projects' => $totalAccessibleProjects,
                    'active_projects' => $activeProjectsCount,
                    'completed_projects' => $completedProjectsCount,
                    'my_open_tasks' => $myOpenTasksCount,
                    'my_overdue_tasks' => $myOverdueTasksCount,
                ],
                'my_tasks' => $myUpcomingTasks,
                'recent_projects' => $recentProjects,
                'activity_stream' => $recentActivity,
            ],
        ]);
    }
}
