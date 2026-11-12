<?php

namespace App\Pm\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Pm\Enums\DepartmentRole;
use App\Pm\Models\PmAccessLog;
use App\Pm\Models\PmDepartmentMembership;
use App\Pm\Services\DepartmentMembershipService;
use App\Pm\Support\PmPermissions;
use App\Pm\Support\PmRoles;
use App\Services\ApplicationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PmAccessManagementController extends Controller
{
    public function __construct(
        protected ApplicationAccessService $appAccessService,
        protected DepartmentMembershipService $deptService
    ) {}

    /**
     * List users with PMS access details and department memberships.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $search = $request->query('search');
        $departmentId = $request->query('department_id');
        $accessFilter = $request->query('access_filter'); // all, granted, none

        $query = User::query()->with([
            'roles',
            'pmDepartmentMemberships.department',
        ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($departmentId) {
            $query->whereHas('pmDepartmentMemberships', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId)->active();
            });
        }

        $users = $query->paginate(20);

        $items = collect($users->items())->map(function (User $user) {
            $hasAccess = $this->appAccessService->canAccess($user, 'projects');
            $rawApps = $this->appAccessService->accessibleApplications($user);
            $source = $rawApps['projects']['source'] ?? ($hasAccess ? 'role' : 'none');
            $status = $rawApps['projects']['status'] ?? ($hasAccess ? 'active' : 'none');

            return [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'has_pms_access' => $hasAccess,
                'access_source' => $source,
                'access_status' => $status,
                'roles' => $user->roles->pluck('slug')->toArray(),
                'pm_roles' => $user->roles->whereIn('slug', array_keys(PmRoles::definitions()))->pluck('slug')->values()->toArray(),
                'department_memberships' => $user->pmDepartmentMemberships->map(function (PmDepartmentMembership $m) {
                    return [
                        'id' => $m->id,
                        'department_id' => $m->department_id,
                        'department_name' => $m->department?->name,
                        'department_role' => $m->department_role instanceof DepartmentRole ? $m->department_role->value : $m->department_role,
                        'status' => $m->status,
                        'start_date' => $m->start_date?->toDateString(),
                        'end_date' => $m->end_date?->toDateString(),
                        'is_active' => $m->isActive(),
                    ];
                }),
            ];
        });

        if ($accessFilter === 'granted' || $accessFilter === 'active') {
            $items = $items->filter(fn ($u) => $u['has_pms_access'])->values();
        } elseif ($accessFilter === 'suspended') {
            $items = $items->filter(fn ($u) => $u['access_status'] === 'suspended')->values();
        } elseif ($accessFilter === 'none') {
            $items = $items->filter(fn ($u) => !$u['has_pms_access'] && $u['access_status'] !== 'suspended')->values();
        }

        return response()->json([
            'users' => $items,
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Grant explicit PMS application access to a user.
     */
    public function grantAccess(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $targetUser = User::findOrFail($validated['user_id']);
        $actor = Auth::user();

        $this->appAccessService->grant($targetUser, 'projects', $actor);

        PmAccessLog::create([
            'target_user_id' => $targetUser->id,
            'actor_user_id' => $actor->id,
            'action' => 'grant_pms_access',
            'previous_state' => ['has_access' => false, 'status' => 'none'],
            'new_state' => ['has_access' => true, 'status' => 'active'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'notes' => $validated['reason'] ?? 'Granted via PMS Access Management Console',
        ]);

        return response()->json([
            'message' => "PMS access successfully granted to {$targetUser->name}.",
            'has_pms_access' => true,
            'access_status' => 'active',
        ]);
    }

    /**
     * Suspend explicit PMS application access for a user.
     */
    public function suspendAccess(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $targetUser = User::findOrFail($validated['user_id']);
        $actor = Auth::user();

        $this->appAccessService->suspend($targetUser, 'projects', $actor, $validated['reason'] ?? null);

        PmAccessLog::create([
            'target_user_id' => $targetUser->id,
            'actor_user_id' => $actor->id,
            'action' => 'suspend_pms_access',
            'previous_state' => ['status' => 'active'],
            'new_state' => ['status' => 'suspended'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'notes' => $validated['reason'] ?? 'Suspended via PMS Access Management Console',
        ]);

        return response()->json([
            'message' => "PMS access successfully suspended for {$targetUser->name}.",
            'has_pms_access' => false,
            'access_status' => 'suspended',
        ]);
    }

    /**
     * Restore suspended PMS application access for a user.
     */
    public function restoreAccess(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $targetUser = User::findOrFail($validated['user_id']);
        $actor = Auth::user();

        $this->appAccessService->restore($targetUser, 'projects', $actor);

        PmAccessLog::create([
            'target_user_id' => $targetUser->id,
            'actor_user_id' => $actor->id,
            'action' => 'restore_pms_access',
            'previous_state' => ['status' => 'suspended'],
            'new_state' => ['status' => 'active'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'notes' => $validated['reason'] ?? 'Restored via PMS Access Management Console',
        ]);

        return response()->json([
            'message' => "PMS access successfully restored for {$targetUser->name}.",
            'has_pms_access' => true,
            'access_status' => 'active',
        ]);
    }

    /**
     * Revoke explicit PMS application access from a user.
     */
    public function revokeAccess(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $targetUser = User::findOrFail($validated['user_id']);
        $actor = Auth::user();

        $this->appAccessService->revoke($targetUser, 'projects', $actor);

        PmAccessLog::create([
            'target_user_id' => $targetUser->id,
            'actor_user_id' => $actor->id,
            'action' => 'revoke_pms_access',
            'previous_state' => ['has_access' => true],
            'new_state' => ['has_access' => false, 'status' => 'none'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'notes' => $validated['reason'] ?? 'Revoked via PMS Access Management Console',
        ]);

        return response()->json([
            'message' => "PMS access successfully revoked from {$targetUser->name}.",
            'has_pms_access' => false,
            'access_status' => 'none',
        ]);
    }

    /**
     * Assign or update a PMS role for a user.
     */
    public function assignRole(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|string',
            'action' => 'required|in:attach,detach',
        ]);

        $targetUser = User::findOrFail($validated['user_id']);
        $role = Role::where('slug', $validated['role'])->firstOrFail();
        $actor = Auth::user();

        $oldRoles = $targetUser->roles->pluck('slug')->toArray();

        if ($validated['action'] === 'attach') {
            $targetUser->roles()->syncWithoutDetaching([$role->id]);
        } else {
            $targetUser->roles()->detach([$role->id]);
        }

        $newRoles = $targetUser->fresh()->roles->pluck('slug')->toArray();

        PmAccessLog::create([
            'target_user_id' => $targetUser->id,
            'actor_user_id' => $actor->id,
            'action' => $validated['action'] === 'attach' ? 'attach_pms_role' : 'detach_pms_role',
            'previous_state' => ['roles' => $oldRoles],
            'new_state' => ['roles' => $newRoles],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'notes' => "Role {$role->name} {$validated['action']}ed.",
        ]);

        return response()->json([
            'message' => "Role {$role->name} successfully updated for {$targetUser->name}.",
            'roles' => $newRoles,
        ]);
    }

    /**
     * Assign a user to an MSA department.
     */
    public function assignDepartment(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'department_id' => 'required|exists:departments,id',
            'department_role' => 'required|in:lead,coordinator,member,guest',
        ]);

        $targetUser = User::findOrFail($validated['user_id']);
        $department = Department::findOrFail($validated['department_id']);
        $actor = Auth::user();

        $roleEnum = DepartmentRole::from($validated['department_role']);

        $membership = $this->deptService->addMember(
            $department,
            $targetUser,
            $roleEnum,
            $actor
        );

        PmAccessLog::create([
            'target_user_id' => $targetUser->id,
            'actor_user_id' => $actor->id,
            'action' => 'assign_department_membership',
            'previous_state' => [],
            'new_state' => [
                'department_id' => $department->id,
                'department_name' => $department->name,
                'role' => $roleEnum->value,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'notes' => "Assigned to {$department->name} as {$roleEnum->label()}.",
        ]);

        return response()->json([
            'message' => "{$targetUser->name} successfully assigned to {$department->name}.",
            'membership' => [
                'id' => $membership->id,
                'department_id' => $department->id,
                'department_name' => $department->name,
                'department_role' => $membership->department_role->value,
                'status' => $membership->status,
                'start_date' => $membership->start_date?->toDateString(),
            ],
        ]);
    }

    /**
     * Remove a user from an MSA department.
     */
    public function removeDepartment(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'department_id' => 'required|exists:departments,id',
        ]);

        $targetUser = User::findOrFail($validated['user_id']);
        $department = Department::findOrFail($validated['department_id']);
        $actor = Auth::user();

        $this->deptService->removeMember($department, $targetUser, $actor);

        PmAccessLog::create([
            'target_user_id' => $targetUser->id,
            'actor_user_id' => $actor->id,
            'action' => 'remove_department_membership',
            'previous_state' => [
                'department_id' => $department->id,
                'department_name' => $department->name,
            ],
            'new_state' => [],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'notes' => "Removed from {$department->name}.",
        ]);

        return response()->json([
            'message' => "{$targetUser->name} successfully removed from {$department->name}.",
        ]);
    }

    /**
     * List departments with summary metrics.
     */
    public function departments(): JsonResponse
    {
        $this->authorizeAdmin();

        $departments = Department::withCount([
            'memberships as active_members_count' => function ($q) {
                $q->active();
            },
        ])->get();

        return response()->json([
            'departments' => $departments->map(function (Department $d) {
                $lead = $d->memberships()->active()->where('department_role', DepartmentRole::Lead->value)->first()?->user;

                return [
                    'id' => $d->id,
                    'name' => $d->name,
                    'slug' => $d->slug,
                    'description' => $d->description,
                    'active_members_count' => $d->active_members_count,
                    'lead' => $lead ? [
                        'id' => $lead->id,
                        'name' => $lead->name,
                        'email' => $lead->email,
                    ] : null,
                ];
            }),
        ]);
    }

    /**
     * View administrative access audit logs.
     */
    public function accessLogs(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $logs = PmAccessLog::with(['targetUser', 'actor'])
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return response()->json([
            'logs' => collect($logs->items())->map(function (PmAccessLog $log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'target_user' => $log->targetUser ? [
                        'id' => $log->targetUser->id,
                        'name' => $log->targetUser->name,
                        'email' => $log->targetUser->email,
                    ] : null,
                    'actor' => $log->actor ? [
                        'id' => $log->actor->id,
                        'name' => $log->actor->name,
                        'email' => $log->actor->email,
                    ] : null,
                    'previous_state' => $log->previous_state,
                    'new_state' => $log->new_state,
                    'notes' => $log->notes,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at?->toIso8601String(),
                ];
            }),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Reference data for canonical PMS roles, permissions, and project-level roles.
     */
    public function rolesReference(): JsonResponse
    {
        $this->authorizeAdmin();

        return response()->json([
            'canonical_pms_roles' => PmRoles::definitions(),
            'canonical_permissions' => PmPermissions::definitions(),
            'project_level_roles' => [
                'owner' => [
                    'name' => 'Owner',
                    'description' => 'Accountable for the project and its overall management. Retains project ownership capabilities, full settings access, member management, and task control.',
                    'permissions' => [
                        'view_project',
                        'edit_details',
                        'manage_settings',
                        'delete_project',
                        'manage_members',
                        'assign_roles',
                        'create_tasks',
                        'edit_tasks',
                        'delete_tasks',
                        'add_comments',
                    ],
                ],
                'manager' => [
                    'name' => 'Manager',
                    'description' => 'Manages project execution, tasks, assignments, and permitted project settings. Cannot delete project or transfer ownership.',
                    'permissions' => [
                        'view_project',
                        'edit_details',
                        'manage_settings',
                        'manage_members',
                        'assign_roles',
                        'create_tasks',
                        'edit_tasks',
                        'delete_tasks',
                        'add_comments',
                    ],
                ],
                'contributor' => [
                    'name' => 'Contributor',
                    'description' => 'Participates in project work, creates and updates assigned tasks, and posts collaborative comments. Cannot modify project ownership or administrative settings.',
                    'permissions' => [
                        'view_project',
                        'create_tasks',
                        'edit_tasks',
                        'add_comments',
                    ],
                ],
                'viewer' => [
                    'name' => 'Viewer',
                    'description' => 'Read-only access. Can view project information, tasks, milestones, and activity without modifying them.',
                    'permissions' => [
                        'view_project',
                        'view_tasks',
                        'view_activity',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Role assignment history.
     */
    public function roleHistory(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $logs = PmAccessLog::with(['targetUser', 'actor'])
            ->whereIn('action', ['attach_pms_role', 'detach_pms_role', 'assign_pms_role'])
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return response()->json([
            'history' => collect($logs->items())->map(function (PmAccessLog $log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'target_user' => $log->targetUser ? [
                        'id' => $log->targetUser->id,
                        'name' => $log->targetUser->name,
                        'email' => $log->targetUser->email,
                    ] : null,
                    'actor' => $log->actor ? [
                        'id' => $log->actor->id,
                        'name' => $log->actor->name,
                        'email' => $log->actor->email,
                    ] : null,
                    'previous_state' => $log->previous_state,
                    'new_state' => $log->new_state,
                    'notes' => $log->notes,
                    'created_at' => $log->created_at?->toIso8601String(),
                ];
            }),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    private function authorizeAdmin(): void
    {
        $user = Auth::user();
        if (!$user || (!$user->hasAnyRole(['admin', 'super-admin', 'pms-administrator']) && !$user->can('pm.admin'))) {
            abort(403, 'Unauthorized: PMS administrative access required.');
        }
    }
}
