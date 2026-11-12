<?php

namespace App\Pm\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Pm\Enums\InvitationStatus;
use App\Pm\Enums\ProjectPriority;
use App\Pm\Enums\ProjectRole;
use App\Pm\Enums\ProjectStatus;
use App\Pm\Enums\ProjectVisibility;
use App\Pm\Models\PmActivityLog;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmProjectMember;
use App\Pm\Services\DepartmentMembershipService;
use App\Pm\Services\ProjectAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PmProjectController extends Controller
{
    public function __construct(
        protected ProjectAccessService $accessService,
        protected DepartmentMembershipService $deptService
    ) {}

    /**
     * List all projects accessible to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $search = $request->query('search');
        $departmentId = $request->query('department_id');
        $visibility = $request->query('visibility');
        $status = $request->query('status');
        $priority = $request->query('priority');

        $query = PmProject::query()
            ->accessibleTo($user)
            ->with([
                'department',
                'owner',
                'participatingDepartments',
            ])->withCount(['tasks', 'milestones', 'projectMembers']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($departmentId) {
            $query->where(function ($dq) use ($departmentId) {
                $dq->where('department_id', $departmentId)
                    ->orWhereHas('participatingDepartments', function ($pq) use ($departmentId) {
                        $pq->where('departments.id', $departmentId);
                    });
            });
        }

        if ($visibility) {
            $query->where('visibility', $visibility);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($priority) {
            $query->where('priority', $priority);
        }

        $projects = $query->orderBy('updated_at', 'desc')->paginate(15);

        $items = collect($projects->items())->map(function (PmProject $project) use ($user) {
            return [
                'id' => $project->id,
                'uuid' => $project->uuid,
                'name' => $project->name,
                'slug' => $project->slug,
                'description' => $project->description,
                'visibility' => $project->visibility->value,
                'visibility_label' => $project->visibility->label(),
                'status' => $project->status->value,
                'status_label' => $project->status->label(),
                'priority' => $project->priority->value,
                'priority_label' => $project->priority->label(),
                'department' => $project->department ? [
                    'id' => $project->department->id,
                    'name' => $project->department->name,
                    'slug' => $project->department->slug,
                    'code' => match ($project->department->slug) {
                        'presidential' => 'PRE',
                        'marketing' => 'MAR',
                        'finance' => 'FIN',
                        'education' => 'EDU',
                        'graphics' => 'GRA',
                        'events' => 'EVE',
                        'it' => 'IT',
                        'jumuah' => 'JUM',
                        default => strtoupper(substr($project->department->slug, 0, 3)),
                    },
                    'color' => $project->department->color,
                ] : null,
                'participating_departments' => $project->participatingDepartments->map(fn ($pd) => [
                    'id' => $pd->id,
                    'name' => $pd->name,
                    'slug' => $pd->slug,
                    'code' => match ($pd->slug) {
                        'presidential' => 'PRE',
                        'marketing' => 'MAR',
                        'finance' => 'FIN',
                        'education' => 'EDU',
                        'graphics' => 'GRA',
                        'events' => 'EVE',
                        'it' => 'IT',
                        'jumuah' => 'JUM',
                        default => strtoupper(substr($pd->slug, 0, 3)),
                    },
                    'color' => $pd->color,
                ])->values(),
                'participating_department_ids' => $project->participatingDepartments->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                'owner' => $project->owner ? [
                    'id' => $project->owner->id,
                    'name' => $project->owner->name,
                    'email' => $project->owner->email,
                ] : null,
                'tasks_count' => $project->tasks_count,
                'milestones_count' => $project->milestones_count,
                'members_count' => $project->project_members_count,
                'start_date' => $project->start_date?->toDateString(),
                'due_date' => $project->due_date?->toDateString(),
                'completed_at' => $project->completed_at?->toIso8601String(),
                'can_update' => $this->accessService->canUpdateProject($user, $project),
                'can_delete' => $this->accessService->canDeleteProject($user, $project),
            ];
        });

        return response()->json([
            'projects' => $items,
            'meta' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
            ],
        ]);
    }

    /**
     * Create a new project.
     * Visibility is strictly limited to 'department_private' and 'shared_msa'.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'owning_department_id' => 'nullable|exists:departments,id',
            'department_id' => 'required_without:owning_department_id|exists:departments,id',
            'participating_department_ids' => 'nullable|array',
            'participating_department_ids.*' => 'integer|exists:departments,id',
            'visibility' => [
                'required',
                Rule::in([ProjectVisibility::DepartmentPrivate->value, ProjectVisibility::SharedMsa->value]),
            ],
            'status' => ['nullable', Rule::in(ProjectStatus::values())],
            'priority' => ['nullable', Rule::in(ProjectPriority::values())],
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $owningDeptId = (int) ($validated['owning_department_id'] ?? $validated['department_id']);
        $rawParticipatingIds = $validated['participating_department_ids'] ?? [];
        $participatingDeptIds = array_values(array_unique(array_map('intval', $rawParticipatingIds)));

        // Validation: Participating departments cannot contain the owning department
        if (in_array($owningDeptId, $participatingDeptIds, true)) {
            return response()->json([
                'message' => 'The owning department cannot also be selected as a participating department.',
                'errors' => [
                    'participating_department_ids' => ['The owning department cannot also be selected as a participating department.'],
                ],
            ], 422);
        }

        $department = Department::where('id', $owningDeptId)->where('is_active', true)->firstOrFail();

        // Check active canonical status of all participating departments
        if (!empty($participatingDeptIds)) {
            $activeCount = Department::whereIn('id', $participatingDeptIds)->where('is_active', true)->count();
            if ($activeCount !== count($participatingDeptIds)) {
                return response()->json([
                    'message' => 'All participating departments must belong to the active canonical department registry.',
                    'errors' => [
                        'participating_department_ids' => ['All participating departments must belong to the active canonical department registry.'],
                    ],
                ], 422);
            }
        }

        // Check department membership or administrative authority
        $isMember = $this->deptService->isMember($user, $department->id);
        $isLead = $this->deptService->isLead($user, $department->id);
        $isAdmin = $user->hasRole('super-admin') || $user->hasRole('pms-administrator');

        if (!$isMember && !$isLead && !$isAdmin) {
            return response()->json([
                'message' => 'You must be a member or lead of this department to create projects within it.',
            ], 403);
        }

        $project = DB::transaction(function () use ($validated, $owningDeptId, $participatingDeptIds, $user) {
            $proj = PmProject::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'department_id' => $owningDeptId,
                'visibility' => ProjectVisibility::from($validated['visibility']),
                'status' => isset($validated['status']) ? ProjectStatus::from($validated['status']) : ProjectStatus::Planning,
                'priority' => isset($validated['priority']) ? ProjectPriority::from($validated['priority']) : ProjectPriority::Medium,
                'start_date' => $validated['start_date'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'owner_id' => $user->id,
                'created_by' => $user->id,
            ]);

            // Attach participating departments
            if (!empty($participatingDeptIds)) {
                $syncData = [];
                foreach ($participatingDeptIds as $pId) {
                    $syncData[$pId] = ['created_by' => $user->id];
                }
                $proj->participatingDepartments()->sync($syncData);
            }

            // Add creator as project owner member
            PmProjectMember::create([
                'project_id' => $proj->id,
                'user_id' => $user->id,
                'project_role' => ProjectRole::Owner,
                'invitation_status' => InvitationStatus::Accepted,
                'accepted_at' => now(),
            ]);

            PmActivityLog::record(
                actorId: $user->id,
                action: 'project_created',
                subjectType: 'project',
                subjectId: $proj->id,
                projectId: $proj->id,
                metadata: [
                    'name' => $proj->name,
                    'visibility' => $proj->visibility->value,
                    'department_id' => $proj->department_id,
                    'participating_department_ids' => $participatingDeptIds,
                ]
            );

            return $proj;
        });

        $project->load(['department', 'participatingDepartments']);

        return response()->json([
            'message' => 'Project created successfully.',
            'project' => [
                'id' => $project->id,
                'uuid' => $project->uuid,
                'name' => $project->name,
                'slug' => $project->slug,
                'visibility' => $project->visibility->value,
                'status' => $project->status->value,
                'department_id' => $project->department_id,
                'department' => $project->department ? [
                    'id' => $project->department->id,
                    'name' => $project->department->name,
                    'slug' => $project->department->slug,
                ] : null,
                'participating_departments' => $project->participatingDepartments->map(fn ($pd) => [
                    'id' => $pd->id,
                    'name' => $pd->name,
                    'slug' => $pd->slug,
                ])->values(),
                'participating_department_ids' => $project->participatingDepartments->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ],
        ], 201);
    }

    /**
     * Get single project details.
     */
    public function show(string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::where('uuid', $uuid)->firstOrFail();

        if (!$this->accessService->canViewProject($user, $project)) {
            abort(403, 'Unauthorized: You do not have permission to view this project.');
        }

        $project->load([
            'department',
            'participatingDepartments',
            'owner',
            'members' => function ($q) {
                $q->withPivot(['id', 'project_role', 'invitation_status', 'invited_at', 'accepted_at']);
            },
            'milestones' => function ($q) {
                $q->withCount('tasks');
            },
        ]);

        $recentActivities = PmActivityLog::query()
            ->where('project_id', $project->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'project' => [
                'id' => $project->id,
                'uuid' => $project->uuid,
                'name' => $project->name,
                'slug' => $project->slug,
                'description' => $project->description,
                'visibility' => $project->visibility->value,
                'visibility_label' => $project->visibility->label(),
                'status' => $project->status->value,
                'status_label' => $project->status->label(),
                'priority' => $project->priority->value,
                'priority_label' => $project->priority->label(),
                'start_date' => $project->start_date?->toDateString(),
                'due_date' => $project->due_date?->toDateString(),
                'completed_at' => $project->completed_at?->toIso8601String(),
                'created_at' => $project->created_at->toIso8601String(),
                'department' => [
                    'id' => $project->department->id,
                    'name' => $project->department->name,
                    'slug' => $project->department->slug,
                    'code' => match ($project->department->slug) {
                        'presidential' => 'PRE',
                        'marketing' => 'MAR',
                        'finance' => 'FIN',
                        'education' => 'EDU',
                        'graphics' => 'GRA',
                        'events' => 'EVE',
                        'it' => 'IT',
                        'jumuah' => 'JUM',
                        default => strtoupper(substr($project->department->slug, 0, 3)),
                    },
                    'color' => $project->department->color,
                ],
                'participating_departments' => $project->participatingDepartments->map(fn ($pd) => [
                    'id' => $pd->id,
                    'name' => $pd->name,
                    'slug' => $pd->slug,
                    'code' => match ($pd->slug) {
                        'presidential' => 'PRE',
                        'marketing' => 'MAR',
                        'finance' => 'FIN',
                        'education' => 'EDU',
                        'graphics' => 'GRA',
                        'events' => 'EVE',
                        'it' => 'IT',
                        'jumuah' => 'JUM',
                        default => strtoupper(substr($pd->slug, 0, 3)),
                    },
                    'color' => $pd->color,
                ])->values(),
                'participating_department_ids' => $project->participatingDepartments->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                'owner' => [
                    'id' => $project->owner->id,
                    'name' => $project->owner->name,
                    'email' => $project->owner->email,
                ],
                'members' => $project->members->map(function ($m) {
                    return [
                        'user_id' => $m->id,
                        'name' => $m->name,
                        'email' => $m->email,
                        'project_role' => $m->pivot->project_role,
                        'invitation_status' => $m->pivot->invitation_status,
                        'accepted_at' => $m->pivot->accepted_at,
                    ];
                }),
                'milestones' => $project->milestones->map(function ($ms) {
                    return [
                        'id' => $ms->id,
                        'uuid' => $ms->uuid,
                        'title' => $ms->title,
                        'status' => $ms->status->value,
                        'due_date' => $ms->due_date?->toDateString(),
                        'tasks_count' => $ms->tasks_count,
                    ];
                }),
                'recent_activities' => $recentActivities->map(function ($act) {
                    return [
                        'id' => $act->id,
                        'action' => $act->action,
                        'user_name' => $act->user?->name ?? 'System',
                        'metadata' => $act->metadata,
                        'created_at' => $act->created_at->toIso8601String(),
                    ];
                }),
                'can_update' => $this->accessService->canUpdateProject($user, $project),
                'can_delete' => $this->accessService->canDeleteProject($user, $project),
                'can_manage_members' => $this->accessService->canManageMembers($user, $project),
                'can_create_tasks' => $this->accessService->canCreateTask($user, $project),
                'can_manage_participating_departments' => $this->accessService->canManageParticipatingDepartments($user, $project),
            ],
        ]);
    }

    /**
     * Update project settings and details.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::where('uuid', $uuid)->firstOrFail();

        if (!$this->accessService->canUpdateProject($user, $project)) {
            abort(403, 'Unauthorized: You do not have permission to edit this project.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'owning_department_id' => 'nullable|exists:departments,id',
            'department_id' => 'nullable|exists:departments,id',
            'participating_department_ids' => 'nullable|array',
            'participating_department_ids.*' => 'integer|exists:departments,id',
            'visibility' => [
                'sometimes',
                'required',
                Rule::in([ProjectVisibility::DepartmentPrivate->value, ProjectVisibility::SharedMsa->value]),
            ],
            'status' => ['sometimes', 'required', Rule::in(ProjectStatus::values())],
            'priority' => ['sometimes', 'required', Rule::in(ProjectPriority::values())],
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'owner_id' => 'sometimes|required|exists:users,id',
        ]);

        $oldValues = $project->only(['name', 'visibility', 'status', 'priority', 'owner_id', 'department_id']);

        if (isset($validated['name'])) {
            $project->name = $validated['name'];
        }
        if (array_key_exists('description', $validated)) {
            $project->description = $validated['description'];
        }
        if (isset($validated['visibility'])) {
            $project->visibility = ProjectVisibility::from($validated['visibility']);
        }
        if (isset($validated['status'])) {
            $project->status = ProjectStatus::from($validated['status']);
            if ($project->status === ProjectStatus::Completed && !$project->completed_at) {
                $project->completed_at = now();
            }
        }
        if (isset($validated['priority'])) {
            $project->priority = ProjectPriority::from($validated['priority']);
        }
        if (array_key_exists('start_date', $validated)) {
            $project->start_date = $validated['start_date'];
        }
        if (array_key_exists('due_date', $validated)) {
            $project->due_date = $validated['due_date'];
        }
        if (isset($validated['owner_id'])) {
            $project->owner_id = $validated['owner_id'];
        }

        // Handle Owning Department update
        if (isset($validated['owning_department_id']) || isset($validated['department_id'])) {
            $newOwningId = (int) ($validated['owning_department_id'] ?? $validated['department_id']);
            if ($newOwningId !== (int) $project->department_id) {
                $oldDeptId = $project->department_id;
                $project->department_id = $newOwningId;

                // Automatically detach from participating if present
                $project->participatingDepartments()->detach($newOwningId);

                PmActivityLog::record(
                    actorId: $user->id,
                    action: 'owning_department_updated',
                    subjectType: 'project',
                    subjectId: $project->id,
                    projectId: $project->id,
                    metadata: [
                        'old_department_id' => $oldDeptId,
                        'new_department_id' => $newOwningId,
                    ]
                );
            }
        }

        $project->updated_by = $user->id;
        $project->save();

        // Handle Participating Departments update
        if (array_key_exists('participating_department_ids', $validated)) {
            $rawP = $validated['participating_department_ids'] ?? [];
            $newParticipatingIds = array_values(array_unique(array_map('intval', $rawP)));

            if (in_array((int) $project->department_id, $newParticipatingIds, true)) {
                return response()->json([
                    'message' => 'The owning department cannot also be selected as a participating department.',
                    'errors' => [
                        'participating_department_ids' => ['The owning department cannot also be selected as a participating department.'],
                    ],
                ], 422);
            }

            $existingIds = $project->participatingDepartments()->pluck('departments.id')->map(fn ($id) => (int) $id)->all();
            $addedIds = array_diff($newParticipatingIds, $existingIds);
            $removedIds = array_diff($existingIds, $newParticipatingIds);

            $syncData = [];
            foreach ($newParticipatingIds as $pId) {
                $syncData[$pId] = ['created_by' => $user->id];
            }
            $project->participatingDepartments()->sync($syncData);

            foreach ($addedIds as $aId) {
                PmActivityLog::record(
                    actorId: $user->id,
                    action: 'participating_department_added',
                    subjectType: 'project',
                    subjectId: $project->id,
                    projectId: $project->id,
                    metadata: ['department_id' => $aId]
                );
            }

            foreach ($removedIds as $rId) {
                PmActivityLog::record(
                    actorId: $user->id,
                    action: 'participating_department_removed',
                    subjectType: 'project',
                    subjectId: $project->id,
                    projectId: $project->id,
                    metadata: ['department_id' => $rId]
                );
            }
        }

        PmActivityLog::record(
            actorId: $user->id,
            action: 'project_updated',
            subjectType: 'project',
            subjectId: $project->id,
            projectId: $project->id,
            metadata: [
                'old' => $oldValues,
                'new' => $project->only(['name', 'visibility', 'status', 'priority', 'owner_id', 'department_id']),
            ]
        );

        $project->load(['department', 'participatingDepartments']);

        return response()->json([
            'message' => 'Project updated successfully.',
            'project' => [
                'uuid' => $project->uuid,
                'name' => $project->name,
                'visibility' => $project->visibility->value,
                'status' => $project->status->value,
                'priority' => $project->priority->value,
                'department_id' => $project->department_id,
                'department' => $project->department ? [
                    'id' => $project->department->id,
                    'name' => $project->department->name,
                    'slug' => $project->department->slug,
                ] : null,
                'participating_departments' => $project->participatingDepartments->map(fn ($pd) => [
                    'id' => $pd->id,
                    'name' => $pd->name,
                    'slug' => $pd->slug,
                ])->values(),
                'participating_department_ids' => $project->participatingDepartments->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ],
        ]);
    }

    /**
     * Soft delete/archive project.
     */
    public function destroy(string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::where('uuid', $uuid)->firstOrFail();

        if (!$this->accessService->canDeleteProject($user, $project)) {
            abort(403, 'Unauthorized: You do not have permission to delete this project.');
        }

        $project->delete();

        PmActivityLog::record(
            actorId: $user->id,
            action: 'project_deleted',
            subjectType: 'project',
            subjectId: $project->id,
            projectId: $project->id,
            metadata: [
                'name' => $project->name,
                'department_id' => $project->department_id,
            ]
        );

        return response()->json([
            'message' => 'Project deleted successfully.',
        ]);
    }
}
