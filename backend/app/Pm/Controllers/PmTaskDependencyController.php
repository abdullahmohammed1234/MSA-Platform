<?php

namespace App\Pm\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Pm\Enums\DependencyType;
use App\Pm\Models\PmActivityLog;
use App\Pm\Models\PmTask;
use App\Pm\Models\PmTaskDependency;
use App\Pm\Services\PmSchedulingService;
use App\Pm\Services\ProjectAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PmTaskDependencyController extends Controller
{
    public function __construct(
        protected ProjectAccessService $accessService,
        protected PmSchedulingService $schedulingService
    ) {}

    /**
     * List all dependencies for a task (predecessors and successors) with scheduling metadata.
     */
    public function index(Request $request, string $taskUuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $task = PmTask::with([
            'project',
            'dependencies.assignee',
            'dependents.assignee',
        ])->where('uuid', $taskUuid)->firstOrFail();

        if (!$this->accessService->canViewProject($user, $task->project)) {
            abort(403, 'Unauthorized.');
        }

        $predecessors = $task->dependencies->map(fn (PmTask $dep) => [
            'id' => $dep->id,
            'uuid' => $dep->uuid,
            'title' => $dep->title,
            'status' => $dep->status->value,
            'status_label' => $dep->status->label(),
            'priority' => $dep->priority->value,
            'start_date' => $dep->start_date?->toDateString(),
            'due_date' => $dep->due_date?->toDateString(),
            'dependency_type' => $dep->pivot->dependency_type instanceof DependencyType ? $dep->pivot->dependency_type->value : ($dep->pivot->dependency_type ?? 'finish_to_start'),
            'lag_days' => (int) ($dep->pivot->lag_days ?? 0),
            'assignee' => $dep->assignee ? [
                'id' => $dep->assignee->id,
                'name' => $dep->assignee->name,
            ] : null,
        ]);

        $successors = $task->dependents->map(fn (PmTask $dep) => [
            'id' => $dep->id,
            'uuid' => $dep->uuid,
            'title' => $dep->title,
            'status' => $dep->status->value,
            'status_label' => $dep->status->label(),
            'priority' => $dep->priority->value,
            'start_date' => $dep->start_date?->toDateString(),
            'due_date' => $dep->due_date?->toDateString(),
            'dependency_type' => $dep->pivot->dependency_type instanceof DependencyType ? $dep->pivot->dependency_type->value : ($dep->pivot->dependency_type ?? 'finish_to_start'),
            'lag_days' => (int) ($dep->pivot->lag_days ?? 0),
            'assignee' => $dep->assignee ? [
                'id' => $dep->assignee->id,
                'name' => $dep->assignee->name,
            ] : null,
        ]);

        $scheduling = $this->schedulingService->detectConflicts($task);

        return response()->json([
            'task' => [
                'id' => $task->id,
                'uuid' => $task->uuid,
                'title' => $task->title,
                'start_date' => $task->start_date?->toDateString(),
                'due_date' => $task->due_date?->toDateString(),
            ],
            'dependencies' => $predecessors, // Tasks this task depends on (must be finished first)
            'dependents' => $successors,     // Tasks that depend on this task
            'is_blocked' => $task->dependencies->contains(fn ($dep) => $dep->status->value !== 'completed'),
            'scheduling' => $scheduling,
        ]);
    }

    /**
     * Add a dependency: the task ($taskUuid) will depend on $depends_on_task_uuid.
     */
    public function store(Request $request, string $taskUuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $task = PmTask::with('project')->where('uuid', $taskUuid)->firstOrFail();

        if (!$this->accessService->canUpdateTask($user, $task)) {
            abort(403, 'Unauthorized: Insufficient permissions to modify task dependencies.');
        }

        $validated = $request->validate([
            'depends_on_task_uuid' => 'required|string|exists:pm_tasks,uuid',
            'dependency_type' => ['nullable', 'string', Rule::in(DependencyType::values())],
            'lag_days' => 'nullable|integer',
        ]);

        $prereqTask = PmTask::where('uuid', $validated['depends_on_task_uuid'])->firstOrFail();

        // 1. Same-project verification
        if ((int) $task->project_id !== (int) $prereqTask->project_id) {
            return response()->json([
                'message' => 'Cross-project dependencies are forbidden. Both tasks must belong to the same project.',
            ], 422);
        }

        // 2. Self-dependency check
        if ((int) $task->id === (int) $prereqTask->id) {
            return response()->json([
                'message' => 'A task cannot depend on itself.',
            ], 422);
        }

        // 3. Duplicate check
        $exists = PmTaskDependency::where('task_id', $task->id)
            ->where('depends_on_task_id', $prereqTask->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'This dependency relationship already exists.',
            ], 422);
        }

        // 4. DAG Cycle Detection: would making $task depend on $prereqTask create a cycle?
        // A cycle occurs if $prereqTask already depends on $task directly or transitively.
        if ($this->hasTransitiveDependency($prereqTask->id, $task->id)) {
            return response()->json([
                'message' => 'Circular dependency detected: adding this dependency would create a cycle in the task graph.',
            ], 422);
        }

        $depType = isset($validated['dependency_type'])
            ? DependencyType::from($validated['dependency_type'])
            : DependencyType::FinishToStart;
        $lagDays = (int) ($validated['lag_days'] ?? 0);

        $dependency = PmTaskDependency::create([
            'project_id' => $task->project_id,
            'task_id' => $task->id,
            'depends_on_task_id' => $prereqTask->id,
            'dependency_type' => $depType,
            'lag_days' => $lagDays,
            'created_by' => $user->id,
        ]);

        PmActivityLog::record(
            actorId: $user->id,
            action: 'task_dependency_created',
            subjectType: 'task',
            subjectId: $task->id,
            projectId: $task->project_id,
            metadata: [
                'task_title' => $task->title,
                'depends_on_task_id' => $prereqTask->id,
                'depends_on_title' => $prereqTask->title,
                'dependency_type' => $depType->value,
                'lag_days' => $lagDays,
            ]
        );

        return response()->json([
            'message' => 'Task dependency added successfully.',
            'dependency' => [
                'id' => $dependency->id,
                'task_id' => $dependency->task_id,
                'depends_on_task_id' => $dependency->depends_on_task_id,
                'dependency_type' => $dependency->dependency_type->value,
                'lag_days' => $dependency->lag_days,
                'prerequisite' => [
                    'id' => $prereqTask->id,
                    'uuid' => $prereqTask->uuid,
                    'title' => $prereqTask->title,
                    'status' => $prereqTask->status->value,
                ],
            ],
        ], 201);
    }

    /**
     * Remove a dependency.
     */
    public function destroy(Request $request, string $taskUuid, string $dependsOnUuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $task = PmTask::with('project')->where('uuid', $taskUuid)->firstOrFail();

        if (!$this->accessService->canUpdateTask($user, $task)) {
            abort(403, 'Unauthorized: Insufficient permissions to modify task dependencies.');
        }

        $prereqTask = PmTask::where('uuid', $dependsOnUuid)->firstOrFail();

        $deleted = PmTaskDependency::where('task_id', $task->id)
            ->where('depends_on_task_id', $prereqTask->id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'Dependency not found.',
            ], 404);
        }

        PmActivityLog::record(
            actorId: $user->id,
            action: 'task_dependency_deleted',
            subjectType: 'task',
            subjectId: $task->id,
            projectId: $task->project_id,
            metadata: [
                'task_title' => $task->title,
                'removed_depends_on_title' => $prereqTask->title,
            ]
        );

        return response()->json([
            'message' => 'Task dependency removed successfully.',
        ]);
    }

    /**
     * Check if startTaskId transitively depends on targetTaskId.
     */
    protected function hasTransitiveDependency(int $startTaskId, int $targetTaskId, array &$visited = []): bool
    {
        if ($startTaskId === $targetTaskId) {
            return true;
        }

        if (isset($visited[$startTaskId])) {
            return false;
        }

        $visited[$startTaskId] = true;

        $prereqIds = PmTaskDependency::where('task_id', $startTaskId)
            ->pluck('depends_on_task_id')
            ->toArray();

        foreach ($prereqIds as $prereqId) {
            if ($this->hasTransitiveDependency($prereqId, $targetTaskId, $visited)) {
                return true;
            }
        }

        return false;
    }
}

