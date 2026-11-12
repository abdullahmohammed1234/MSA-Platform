<?php

namespace App\Pm\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Pm\Enums\ProjectPriority;
use App\Pm\Enums\ProjectVisibility;
use App\Pm\Enums\TaskStatus;
use App\Pm\Models\PmActivityLog;
use App\Pm\Models\PmMilestone;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmProjectMember;
use App\Pm\Models\PmTask;
use App\Pm\Models\PmTaskList;
use App\Pm\Services\DepartmentMembershipService;
use App\Pm\Services\PmNotificationService;
use App\Pm\Services\ProjectAccessService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PmTaskController extends Controller
{
    public function __construct(
        protected ProjectAccessService $accessService,
        protected DepartmentMembershipService $deptService,
        protected PmNotificationService $notificationService
    ) {}

    /**
     * List tasks for a project.
     */
    public function index(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::where('uuid', $uuid)->firstOrFail();

        if (!$this->accessService->canViewProject($user, $project)) {
            abort(403, 'Unauthorized.');
        }

        $milestoneId = $request->query('milestone_id');
        $taskListId = $request->query('task_list_id');
        $status = $request->query('status');
        $priority = $request->query('priority');
        $assignedTo = $request->query('assigned_to');
        $search = $request->query('search');

        $query = PmTask::query()
            ->where('project_id', $project->id)
            ->whereNull('parent_task_id') // Top-level tasks
            ->with([
                'assignee',
                'assignees:id,name,email,avatar',
                'taskList:id,uuid,name,color',
                'milestone',
                'subtasks.assignee',
            ])
            ->withCount(['comments', 'subtasks', 'dependencies', 'dependents', 'attachments']);

        if ($milestoneId) {
            $query->where('milestone_id', $milestoneId);
        }

        if ($taskListId !== null && $taskListId !== '') {
            if ($taskListId === 'uncategorized' || $taskListId === '0') {
                $query->whereNull('task_list_id');
            } else {
                $query->where('task_list_id', $taskListId);
            }
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($priority) {
            $query->where('priority', $priority);
        }

        if ($assignedTo) {
            $query->where(function ($q) use ($assignedTo) {
                $q->where('assigned_to', $assignedTo)
                    ->orWhereHas('assignees', fn ($sq) => $sq->where('users.id', $assignedTo));
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $tasks = $query->orderBy('due_date', 'asc')->paginate(50);

        $items = collect($tasks->items())->map(function (PmTask $task) {
            return $this->formatTaskPayload($task);
        });

        return response()->json([
            'tasks' => $items,
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'total' => $tasks->total(),
            ],
        ]);
    }

    /**
     * Get eligible assignees for tasks on this project.
     * Only active PMS users with an explicit project role of Owner, Manager, or Contributor.
     */
    public function eligibleAssignees(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::where('uuid', $uuid)->firstOrFail();

        if (!$this->accessService->canViewProject($user, $project)) {
            abort(403, 'Unauthorized.');
        }

        $eligibleUsers = $this->accessService->getEligibleTaskAssignees($project);

        return response()->json([
            'users' => $eligibleUsers->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'avatar' => $u->avatar,
            ]),
        ]);
    }

    /**
     * Create a task within a project.
     */
    public function store(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::where('uuid', $uuid)->firstOrFail();

        if (!$this->accessService->canCreateTask($user, $project)) {
            abort(403, 'Unauthorized: You do not have permission to add tasks to this project.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'milestone_id' => 'nullable|exists:pm_milestones,id',
            'task_list_id' => 'nullable|exists:pm_task_lists,id',
            'parent_task_id' => 'nullable|exists:pm_tasks,id',
            'status' => ['nullable', Rule::in(TaskStatus::values())],
            'priority' => ['nullable', Rule::in(ProjectPriority::values())],
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'assigned_to' => 'nullable|exists:users,id',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
        ]);

        // Validate task_list belongs to project
        if (!empty($validated['task_list_id'])) {
            $listExists = PmTaskList::where('id', $validated['task_list_id'])
                ->where('project_id', $project->id)
                ->exists();
            if (!$listExists) {
                return response()->json([
                    'message' => 'The selected task list does not belong to this project.',
                ], 422);
            }
        }

        // Validate milestone belongs to project
        if (!empty($validated['milestone_id'])) {
            $milestoneExists = PmMilestone::where('id', $validated['milestone_id'])
                ->where('project_id', $project->id)
                ->exists();
            if (!$milestoneExists) {
                return response()->json([
                    'message' => 'The selected milestone does not belong to this project.',
                ], 422);
            }
        }

        // Normalize assignee IDs
        $assigneeIds = collect($validated['assignee_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        if ($assigneeIds->isEmpty() && !empty($validated['assigned_to'])) {
            $assigneeIds->push((int) $validated['assigned_to']);
        }

        // Validate each assignee has effective Contributor/Manager/Owner role
        foreach ($assigneeIds as $assigneeId) {
            $targetUser = User::find($assigneeId);
            if (!$targetUser || !$this->accessService->canBeAssignedToTask($targetUser, $project)) {
                return response()->json([
                    'message' => "User [{$assigneeId}] is not eligible for assignment on this project. Only active PMS users with an explicit Contributor, Manager, or Owner role can be assigned.",
                ], 422);
            }
        }

        // Primary assignee: either explicitly requested and in assigneeIds, or the first assignee, or null
        $primaryAssigneeId = null;
        if (!empty($validated['assigned_to']) && $assigneeIds->contains((int) $validated['assigned_to'])) {
            $primaryAssigneeId = (int) $validated['assigned_to'];
        } elseif ($assigneeIds->isNotEmpty()) {
            $primaryAssigneeId = $assigneeIds->first();
        }

        $task = DB::transaction(function () use ($project, $validated, $primaryAssigneeId, $assigneeIds, $user) {
            $newTask = PmTask::create([
                'project_id' => $project->id,
                'milestone_id' => $validated['milestone_id'] ?? null,
                'task_list_id' => $validated['task_list_id'] ?? null,
                'parent_task_id' => $validated['parent_task_id'] ?? null,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'status' => isset($validated['status']) ? TaskStatus::from($validated['status']) : TaskStatus::Todo,
                'priority' => isset($validated['priority']) ? ProjectPriority::from($validated['priority']) : ProjectPriority::Medium,
                'start_date' => $validated['start_date'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'assigned_to' => $primaryAssigneeId,
                'created_by' => $user->id,
            ]);

            // Sync pivot table
            if ($assigneeIds->isNotEmpty()) {
                $syncData = [];
                foreach ($assigneeIds as $id) {
                    $syncData[$id] = [
                        'assigned_by' => $user->id,
                        'assigned_at' => now(),
                    ];
                }
                $newTask->assignees()->sync($syncData);
            }

            return $newTask;
        });

        PmActivityLog::record(
            actorId: $user->id,
            action: 'task_created',
            subjectType: 'task',
            subjectId: $task->id,
            projectId: $project->id,
            metadata: [
                'title' => $task->title,
                'priority' => $task->priority->value,
                'assigned_to' => $task->assigned_to,
                'assignee_ids' => $assigneeIds->all(),
                'task_list_id' => $task->task_list_id,
            ]
        );

        if ($assigneeIds->isNotEmpty()) {
            $this->notificationService->notifyTaskAssigned($task, $assigneeIds->all(), $user, false);
        }

        return response()->json([
            'message' => 'Task created successfully.',
            'task' => $this->formatTaskPayload($task->load(['assignee', 'assignees', 'milestone', 'taskList'])),
        ], 201);
    }

    /**
     * Show task details.
     */
    public function show(string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $task = PmTask::with([
            'project.department',
            'milestone',
            'taskList',
            'assignee',
            'assignees:id,name,email,avatar',
            'creator',
            'dependencies.assignee',
            'dependents.assignee',
            'subtasks.assignee',
            'comments.user',
            'attachments.uploader',
        ])
            ->withCount(['comments', 'subtasks', 'dependencies', 'dependents', 'attachments'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        if (!$this->accessService->canViewProject($user, $task->project)) {
            abort(403, 'Unauthorized.');
        }

        return response()->json([
            'task' => array_merge($this->formatTaskPayload($task), [
                'project' => [
                    'id' => $task->project->id,
                    'uuid' => $task->project->uuid,
                    'name' => $task->project->name,
                    'visibility' => $task->project->visibility->value,
                    'department' => $task->project->department ? [
                        'id' => $task->project->department->id,
                        'name' => $task->project->department->name,
                    ] : null,
                ],
                'creator' => $task->creator ? [
                    'id' => $task->creator->id,
                    'name' => $task->creator->name,
                ] : null,
                'dependencies' => $task->dependencies->map(fn ($dep) => [
                    'id' => $dep->id,
                    'uuid' => $dep->uuid,
                    'title' => $dep->title,
                    'status' => $dep->status->value,
                    'priority' => $dep->priority->value,
                    'due_date' => $dep->due_date?->toDateString(),
                    'assignee' => $dep->assignee ? ['id' => $dep->assignee->id, 'name' => $dep->assignee->name] : null,
                ]),
                'dependents' => $task->dependents->map(fn ($dep) => [
                    'id' => $dep->id,
                    'uuid' => $dep->uuid,
                    'title' => $dep->title,
                    'status' => $dep->status->value,
                    'priority' => $dep->priority->value,
                    'due_date' => $dep->due_date?->toDateString(),
                    'assignee' => $dep->assignee ? ['id' => $dep->assignee->id, 'name' => $dep->assignee->name] : null,
                ]),
                'attachments' => $task->attachments->map(fn ($att) => [
                    'id' => $att->id,
                    'uuid' => $att->uuid,
                    'original_name' => $att->original_name,
                    'file_size' => $att->file_size,
                    'mime_type' => $att->mime_type,
                    'uploader' => $att->uploader ? [
                        'id' => $att->uploader->id,
                        'name' => $att->uploader->name,
                    ] : null,
                    'created_at' => $att->created_at?->toIso8601String(),
                ]),
                'comments' => $task->comments->map(function ($c) {
                    return [
                        'id' => $c->id,
                        'body' => $c->body,
                        'user' => [
                            'id' => $c->user?->id,
                            'name' => $c->user?->name,
                            'avatar' => $c->user?->avatar,
                        ],
                        'created_at' => $c->created_at->toIso8601String(),
                    ];
                }),
            ]),
        ]);
    }

    /**
     * Update task status, assignees, priority, list, or content.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $task = PmTask::with('project')->where('uuid', $uuid)->firstOrFail();

        if (!$this->accessService->canUpdateTask($user, $task)) {
            abort(403, 'Unauthorized: Viewers cannot modify project tasks.');
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'milestone_id' => 'nullable|exists:pm_milestones,id',
            'task_list_id' => 'nullable|exists:pm_task_lists,id',
            'status' => ['sometimes', 'required', Rule::in(TaskStatus::values())],
            'priority' => ['sometimes', 'required', Rule::in(ProjectPriority::values())],
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
        ]);

        // Validate date order if both present
        $startDate = array_key_exists('start_date', $validated) ? $validated['start_date'] : $task->start_date?->toDateString();
        $dueDate = array_key_exists('due_date', $validated) ? $validated['due_date'] : $task->due_date?->toDateString();
        if ($startDate && $dueDate && $dueDate < $startDate) {
            return response()->json([
                'message' => 'The due date must be on or after the start date.',
            ], 422);
        }

        // Validate task_list_id belongs to the project
        if (array_key_exists('task_list_id', $validated) && $validated['task_list_id'] !== null) {
            $listExists = PmTaskList::where('id', $validated['task_list_id'])
                ->where('project_id', $task->project_id)
                ->exists();
            if (!$listExists) {
                return response()->json([
                    'message' => 'The selected task list does not belong to this project.',
                ], 422);
            }
        }

        // Validate milestone_id belongs to the project
        if (array_key_exists('milestone_id', $validated) && $validated['milestone_id'] !== null) {
            $milestoneExists = PmMilestone::where('id', $validated['milestone_id'])
                ->where('project_id', $task->project_id)
                ->exists();
            if (!$milestoneExists) {
                return response()->json([
                    'message' => 'The selected milestone does not belong to this project.',
                ], 422);
            }
        }

        $oldStatus = $task->status;
        $oldListId = $task->task_list_id;
        $previousAssignees = $task->assignees()->pluck('users.id')->map(fn ($id) => (int) $id)->all();

        DB::transaction(function () use ($task, $validated, $user) {
            if (isset($validated['title'])) {
                $task->title = $validated['title'];
            }
            if (array_key_exists('description', $validated)) {
                $task->description = $validated['description'];
            }
            if (array_key_exists('milestone_id', $validated)) {
                $task->milestone_id = $validated['milestone_id'];
            }
            if (array_key_exists('task_list_id', $validated)) {
                $task->task_list_id = $validated['task_list_id'];
            }
            if (isset($validated['status'])) {
                $task->status = TaskStatus::from($validated['status']);
                if ($task->status === TaskStatus::Completed && !$task->completed_at) {
                    $task->completed_at = now();
                } elseif ($task->status !== TaskStatus::Completed) {
                    $task->completed_at = null;
                }
            }
            if (isset($validated['priority'])) {
                $task->priority = ProjectPriority::from($validated['priority']);
            }
            if (array_key_exists('start_date', $validated)) {
                $task->start_date = $validated['start_date'];
            }
            if (array_key_exists('due_date', $validated)) {
                $task->due_date = $validated['due_date'];
            }

            // Assignee processing
            if (array_key_exists('assignee_ids', $validated) || array_key_exists('assigned_to', $validated)) {
                $currentAssigneeIds = $task->assignees()->pluck('users.id')->map(fn ($id) => (int) $id);
                $assigneeIds = array_key_exists('assignee_ids', $validated)
                    ? collect($validated['assignee_ids'])->map(fn ($id) => (int) $id)->unique()->values()
                    : $currentAssigneeIds;

                if (array_key_exists('assigned_to', $validated) && $validated['assigned_to'] !== null) {
                    if (!$assigneeIds->contains((int) $validated['assigned_to'])) {
                        $assigneeIds->push((int) $validated['assigned_to']);
                    }
                }

                // Validate each assignee
                foreach ($assigneeIds as $assigneeId) {
                    $targetUser = User::find($assigneeId);
                    if (!$targetUser || !$this->accessService->canBeAssignedToTask($targetUser, $task->project)) {
                        abort(response()->json([
                            'message' => "User [{$assigneeId}] is not eligible for assignment on this project. Only active PMS users with an explicit Contributor, Manager, or Owner role can be assigned.",
                        ], 422));
                    }
                }

                // Sync pivot
                $syncData = [];
                foreach ($assigneeIds as $id) {
                    $syncData[$id] = [
                        'assigned_by' => $user->id,
                        'assigned_at' => now(),
                    ];
                }
                $task->assignees()->sync($syncData);

                // Update primary assignee
                if (array_key_exists('assigned_to', $validated)) {
                    $task->assigned_to = $validated['assigned_to'];
                } elseif ($assigneeIds->isNotEmpty()) {
                    if (!$assigneeIds->contains((int) $task->assigned_to)) {
                        $task->assigned_to = $assigneeIds->first();
                    }
                } else {
                    $task->assigned_to = null;
                }
            }

            $task->save();
        });

        PmActivityLog::record(
            actorId: $user->id,
            action: 'task_updated',
            subjectType: 'task',
            subjectId: $task->id,
            projectId: $task->project_id,
            metadata: [
                'title' => $task->title,
                'old_status' => $oldStatus->value,
                'new_status' => $task->status->value,
                'task_list_id' => $task->task_list_id,
                'moved_list' => $oldListId !== $task->task_list_id,
                'assigned_to' => $task->assigned_to,
            ]
        );

        if ($task->status !== $oldStatus) {
            $this->notificationService->notifyTaskStatusChanged($task, $oldStatus, $task->status, $user);
        }

        if (array_key_exists('assignee_ids', $validated) || array_key_exists('assigned_to', $validated)) {
            $currentAssignees = $task->assignees()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
            $newAssignees = array_values(array_diff($currentAssignees, $previousAssignees));
            if (!empty($newAssignees)) {
                $this->notificationService->notifyTaskAssigned($task, $newAssignees, $user, true);
            }
        }

        return response()->json([
            'message' => 'Task updated successfully.',
            'task' => $this->formatTaskPayload($task->fresh(['assignee', 'assignees', 'milestone', 'taskList'])),
        ]);
    }

    /**
     * Quickly update task status (e.g. for kanban drag-and-drop).
     */
    public function updateStatus(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $task = PmTask::with('project')->where('uuid', $uuid)->firstOrFail();

        if (!$this->accessService->canUpdateTask($user, $task)) {
            abort(403, 'Unauthorized: Viewers cannot modify project task status.');
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(TaskStatus::values())],
        ]);

        $oldStatus = $task->status;
        $task->status = TaskStatus::from($validated['status']);

        if ($task->status === TaskStatus::Completed && !$task->completed_at) {
            $task->completed_at = now();
        } elseif ($task->status !== TaskStatus::Completed) {
            $task->completed_at = null;
        }

        $task->save();

        PmActivityLog::record(
            actorId: $user->id,
            action: 'task_status_updated',
            subjectType: 'task',
            subjectId: $task->id,
            projectId: $task->project_id,
            metadata: [
                'title' => $task->title,
                'old_status' => $oldStatus instanceof TaskStatus ? $oldStatus->value : $oldStatus,
                'new_status' => $task->status->value,
            ]
        );

        if ($oldStatus !== $task->status) {
            $this->notificationService->notifyTaskStatusChanged(
                $task,
                $oldStatus instanceof TaskStatus ? $oldStatus : TaskStatus::from($oldStatus),
                $task->status,
                $user
            );
        }

        return response()->json([
            'message' => 'Task status updated successfully.',
            'task' => $this->formatTaskPayload($task->fresh(['assignee', 'assignees', 'milestone', 'taskList'])),
        ]);
    }

    /**
     * Delete a task.
     */
    public function destroy(string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $task = PmTask::with('project')->where('uuid', $uuid)->firstOrFail();

        if (!$this->accessService->canDeleteTask($user, $task)) {
            abort(403, 'Unauthorized: Insufficient privileges to delete this task.');
        }

        $taskTitle = $task->title;
        $projectId = $task->project_id;
        $task->delete();

        PmActivityLog::record(
            actorId: $user->id,
            action: 'task_deleted',
            subjectType: 'task',
            subjectId: null,
            projectId: $projectId,
            metadata: [
                'title' => $taskTitle,
            ]
        );

        return response()->json([
            'message' => 'Task deleted successfully.',
        ]);
    }

    /**
     * List all tasks assigned to authenticated user across accessible projects.
     */
    public function myTasks(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $status = $request->query('status');

        $activeDeptIds = $this->deptService->getActiveDepartments($user)->pluck('id')->toArray();
        $acceptedProjectIds = PmProjectMember::query()
            ->where('user_id', $user->id)
            ->accepted()
            ->pluck('project_id')
            ->toArray();

        $query = PmTask::query()
            ->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                    ->orWhereHas('assignees', fn ($sq) => $sq->where('users.id', $user->id));
            })
            ->whereHas('project', function ($q) use ($activeDeptIds, $acceptedProjectIds) {
                $q->where('visibility', ProjectVisibility::SharedMsa->value)
                    ->orWhere(function ($sub) use ($activeDeptIds, $acceptedProjectIds) {
                        $sub->where('visibility', ProjectVisibility::DepartmentPrivate->value)
                            ->where(function ($inner) use ($activeDeptIds, $acceptedProjectIds) {
                                if (!empty($activeDeptIds)) {
                                    $inner->whereIn('department_id', $activeDeptIds);
                                }
                                if (!empty($acceptedProjectIds)) {
                                    $inner->orWhereIn('id', $acceptedProjectIds);
                                }
                                if (empty($activeDeptIds) && empty($acceptedProjectIds)) {
                                    $inner->whereRaw('0 = 1');
                                }
                            });
                    });
            })
            ->with(['project.department', 'milestone', 'taskList', 'assignees:id,name,email,avatar']);

        if ($status) {
            $query->where('status', $status);
        } else {
            $query->where('status', '!=', TaskStatus::Completed->value);
        }

        $tasks = $query->orderBy('due_date', 'asc')->paginate(20);

        return response()->json([
            'tasks' => collect($tasks->items())->map(function (PmTask $task) {
                return array_merge($this->formatTaskPayload($task), [
                    'project' => [
                        'uuid' => $task->project?->uuid,
                        'name' => $task->project?->name,
                        'department_name' => $task->project?->department?->name,
                    ],
                ]);
            }),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'total' => $tasks->total(),
            ],
        ]);
    }

    /**
     * Perform bulk operations on tasks within a project.
     */
    public function bulk(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $maxBatch = (int) config('pm.bulk.max_batch_size', 50);

        $validated = $request->validate([
            'project_uuid' => 'required|string|exists:pm_projects,uuid',
            'task_uuids' => "required|array|min:1|max:{$maxBatch}",
            'task_uuids.*' => 'required|string|exists:pm_tasks,uuid',
            'action' => 'required|in:status,assign,task_list,priority,milestone,reschedule_shift,set_dates,rebalance_assignee',
            'value' => 'present',
        ]);

        $project = PmProject::where('uuid', $validated['project_uuid'])->firstOrFail();

        if (!$this->accessService->canViewProject($user, $project)) {
            abort(403, 'Unauthorized.');
        }

        // Fetch tasks
        $tasks = PmTask::whereIn('uuid', $validated['task_uuids'])->get();

        // 1. Verify all tasks belong to the specified project
        foreach ($tasks as $task) {
            if ((int) $task->project_id !== (int) $project->id) {
                return response()->json([
                    'message' => 'Cross-project bulk operations are not permitted. All tasks must belong to the specified project.',
                ], 422);
            }
        }

        // 2. Authorization check on each task
        foreach ($tasks as $task) {
            if (!$this->accessService->canUpdateTask($user, $task)) {
                return response()->json([
                    'message' => "Unauthorized: You do not have permission to modify task '{$task->title}'.",
                ], 403);
            }
        }

        $action = $validated['action'];
        $val = $validated['value'];

        // Validate action values
        if ($action === 'status') {
            if (!in_array($val, TaskStatus::values(), true)) {
                return response()->json(['message' => 'Invalid status value.'], 422);
            }
        } elseif ($action === 'priority') {
            if (!in_array($val, ProjectPriority::values(), true)) {
                return response()->json(['message' => 'Invalid priority value.'], 422);
            }
        } elseif ($action === 'task_list') {
            if ($val !== null && $val !== '' && $val !== 'uncategorized' && $val !== 0) {
                $list = PmTaskList::where('id', $val)->orWhere('uuid', $val)->first();
                if (!$list || (int) $list->project_id !== (int) $project->id) {
                    return response()->json(['message' => 'The selected task list does not belong to this project.'], 422);
                }
                $val = $list->id;
            } else {
                $val = null;
            }
        } elseif ($action === 'milestone') {
            if ($val !== null && $val !== '') {
                $milestone = PmMilestone::where('id', $val)->orWhere('uuid', $val)->first();
                if (!$milestone || (int) $milestone->project_id !== (int) $project->id) {
                    return response()->json(['message' => 'The selected milestone does not belong to this project.'], 422);
                }
                $val = $milestone->id;
            } else {
                $val = null;
            }
        } elseif ($action === 'assign') {
            $assigneeIds = is_array($val) ? collect($val)->map(fn ($id) => (int) $id)->unique() : ($val ? collect([(int) $val]) : collect());
            foreach ($assigneeIds as $assigneeId) {
                $targetUser = User::find($assigneeId);
                if (!$targetUser || !$this->accessService->canBeAssignedToTask($targetUser, $project)) {
                    return response()->json([
                        'message' => "User [{$assigneeId}] is not eligible for assignment on this project. Only active PMS users with an explicit Contributor, Manager, or Owner role can be assigned.",
                    ], 422);
                }
            }
        } elseif ($action === 'reschedule_shift') {
            $shiftDays = is_array($val) ? ($val['shift_days'] ?? null) : $val;
            if (!is_numeric($shiftDays) || (int) $shiftDays < -365 || (int) $shiftDays > 365) {
                return response()->json(['message' => 'The shift_days value must be an integer between -365 and 365.'], 422);
            }
        } elseif ($action === 'set_dates') {
            if (!is_array($val)) {
                return response()->json(['message' => 'The set_dates value must be an object with start_date and/or due_date.'], 422);
            }
            $sDate = !empty($val['start_date']) ? Carbon::parse($val['start_date']) : null;
            $dDate = !empty($val['due_date']) ? Carbon::parse($val['due_date']) : null;
            if ($sDate && $dDate && $dDate->lt($sDate)) {
                return response()->json(['message' => 'The due_date must be greater than or equal to start_date.'], 422);
            }
        } elseif ($action === 'rebalance_assignee') {
            if (!is_array($val) || empty($val['source_user_id']) || empty($val['target_user_id'])) {
                return response()->json(['message' => 'The rebalance_assignee value requires source_user_id and target_user_id.'], 422);
            }
            $targetUser = User::find($val['target_user_id']);
            if (!$targetUser || !$this->accessService->canBeAssignedToTask($targetUser, $project)) {
                return response()->json([
                    'message' => "Target user [{$val['target_user_id']}] is not eligible for assignment on this project.",
                ], 422);
            }
        }

        $oldStatuses = [];
        if ($action === 'status') {
            foreach ($tasks as $t) {
                $oldStatuses[$t->id] = $t->status;
            }
        }

        // Execute atomically
        DB::transaction(function () use ($tasks, $action, $val, $user) {
            foreach ($tasks as $task) {
                if ($action === 'status') {
                    $task->status = TaskStatus::from($val);
                    if ($task->status === TaskStatus::Completed && !$task->completed_at) {
                        $task->completed_at = now();
                    } elseif ($task->status !== TaskStatus::Completed) {
                        $task->completed_at = null;
                    }
                    $task->save();
                } elseif ($action === 'priority') {
                    $task->priority = ProjectPriority::from($val);
                    $task->save();
                } elseif ($action === 'task_list') {
                    $task->task_list_id = $val;
                    $task->save();
                } elseif ($action === 'milestone') {
                    $task->milestone_id = $val;
                    $task->save();
                } elseif ($action === 'assign') {
                    $assigneeIds = is_array($val) ? collect($val)->map(fn ($id) => (int) $id)->unique() : ($val ? collect([(int) $val]) : collect());
                    $syncData = [];
                    foreach ($assigneeIds as $id) {
                        $syncData[$id] = [
                            'assigned_by' => $user->id,
                            'assigned_at' => now(),
                        ];
                    }
                    $task->assignees()->sync($syncData);
                    $task->assigned_to = $assigneeIds->isNotEmpty() ? $assigneeIds->first() : null;
                    $task->save();
                } elseif ($action === 'reschedule_shift') {
                    $shiftDays = is_array($val) ? (int) ($val['shift_days'] ?? 0) : (int) $val;
                    if ($shiftDays !== 0) {
                        if ($task->start_date) {
                            $task->start_date = $task->start_date->copy()->addDays($shiftDays);
                        }
                        if ($task->due_date) {
                            $task->due_date = $task->due_date->copy()->addDays($shiftDays);
                        }
                        if ($task->effort_source === 'inferred') {
                            $task->estimated_hours = $task->calculateInferredEffort();
                        }
                        $task->save();
                    }
                } elseif ($action === 'set_dates') {
                    $task->start_date = !empty($val['start_date']) ? Carbon::parse($val['start_date']) : null;
                    $task->due_date = !empty($val['due_date']) ? Carbon::parse($val['due_date']) : null;
                    if ($task->effort_source === 'inferred') {
                        $task->estimated_hours = $task->calculateInferredEffort();
                    }
                    $task->save();
                } elseif ($action === 'rebalance_assignee') {
                    $sourceId = (int) $val['source_user_id'];
                    $targetId = (int) $val['target_user_id'];
                    $wasAssigned = false;

                    if ((int) $task->assigned_to === $sourceId) {
                        $task->assigned_to = $targetId;
                        $wasAssigned = true;
                    }

                    $currentAssignees = $task->assignees()->pluck('users.id')->all();
                    if (in_array($sourceId, $currentAssignees, true)) {
                        $newAssignees = array_diff($currentAssignees, [$sourceId]);
                        if (!in_array($targetId, $newAssignees, true)) {
                            $newAssignees[] = $targetId;
                        }
                        $task->assignees()->sync(array_values($newAssignees));
                        if ((int) $task->assigned_to === $sourceId || empty($task->assigned_to)) {
                            $task->assigned_to = $targetId;
                        }
                        $wasAssigned = true;
                    }

                    if ($wasAssigned) {
                        $task->save();
                    }
                }
            }
        });

        PmActivityLog::record(
            actorId: $user->id,
            action: 'bulk_tasks_updated',
            subjectType: 'project',
            subjectId: $project->id,
            projectId: $project->id,
            metadata: [
                'action' => $action,
                'affected_count' => $tasks->count(),
                'task_uuids' => $validated['task_uuids'],
            ]
        );

        if ($action === 'status') {
            $newStatusEnum = TaskStatus::from($val);
            foreach ($tasks as $task) {
                $prev = $oldStatuses[$task->id] ?? null;
                if ($prev && $prev !== $newStatusEnum) {
                    $this->notificationService->notifyTaskStatusChanged(
                        $task,
                        $prev instanceof TaskStatus ? $prev : TaskStatus::from($prev),
                        $newStatusEnum,
                        $user
                    );
                }
            }
        } elseif ($action === 'assign') {
            $assigneeIds = is_array($val) ? collect($val)->map(fn ($id) => (int) $id)->unique() : ($val ? collect([(int) $val]) : collect());
            if ($assigneeIds->isNotEmpty()) {
                foreach ($tasks as $task) {
                    $this->notificationService->notifyTaskAssigned($task, $assigneeIds->all(), $user, false);
                }
            }
        } elseif ($action === 'rebalance_assignee') {
            $targetId = (int) $val['target_user_id'];
            foreach ($tasks as $task) {
                $this->notificationService->notifyTaskAssigned($task, [$targetId], $user, true);
            }
        }

        return response()->json([
            'message' => "Successfully updated {$tasks->count()} tasks.",
            'updated_count' => $tasks->count(),
            'task_uuids' => $validated['task_uuids'],
        ]);
    }

    private function formatTaskPayload(PmTask $task): array
    {
        return [
            'id' => $task->id,
            'uuid' => $task->uuid,
            'project_id' => $task->project_id,
            'milestone_id' => $task->milestone_id,
            'task_list_id' => $task->task_list_id,
            'parent_task_id' => $task->parent_task_id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status->value,
            'status_label' => $task->status->label(),
            'priority' => $task->priority->value,
            'priority_label' => $task->priority->label(),
            'start_date' => $task->start_date?->toDateString(),
            'due_date' => $task->due_date?->toDateString(),
            'completed_at' => $task->completed_at?->toIso8601String(),
            'assigned_to' => $task->assigned_to,
            'assignee' => $task->assignee ? [
                'id' => $task->assignee->id,
                'name' => $task->assignee->name,
                'email' => $task->assignee->email,
                'avatar' => $task->assignee->avatar,
            ] : null,
            'assignees' => $task->relationLoaded('assignees') ? $task->assignees->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'avatar' => $u->avatar,
                ];
            }) : ($task->assignee ? [[
                'id' => $task->assignee->id,
                'name' => $task->assignee->name,
                'email' => $task->assignee->email,
                'avatar' => $task->assignee->avatar,
            ]] : []),
            'task_list' => $task->taskList ? [
                'id' => $task->taskList->id,
                'uuid' => $task->taskList->uuid,
                'name' => $task->taskList->name,
                'color' => $task->taskList->color,
            ] : null,
            'milestone' => $task->milestone ? [
                'id' => $task->milestone->id,
                'uuid' => $task->milestone->uuid,
                'title' => $task->milestone->title,
            ] : null,
            'subtasks' => $task->relationLoaded('subtasks') ? $task->subtasks->map(function ($sub) {
                return [
                    'id' => $sub->id,
                    'uuid' => $sub->uuid,
                    'title' => $sub->title,
                    'status' => $sub->status->value,
                    'assignee' => $sub->assignee ? ['id' => $sub->assignee->id, 'name' => $sub->assignee->name] : null,
                ];
            }) : [],
            'comments_count' => $task->comments_count ?? 0,
            'subtasks_count' => $task->subtasks_count ?? 0,
            'dependencies_count' => $task->dependencies_count ?? 0,
            'dependents_count' => $task->dependents_count ?? 0,
            'attachments_count' => $task->attachments_count ?? 0,
        ];
    }
}
