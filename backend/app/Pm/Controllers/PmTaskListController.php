<?php

namespace App\Pm\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Pm\Models\PmActivityLog;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
use App\Pm\Models\PmTaskList;
use App\Pm\Services\ProjectAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PmTaskListController extends Controller
{
    public function __construct(
        protected ProjectAccessService $accessService
    ) {}

    /**
     * List task lists for a project, including task count and uncategorized count.
     */
    public function index(Request $request, string $projectUuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::where('uuid', $projectUuid)->firstOrFail();

        if (!$this->accessService->canViewProject($user, $project)) {
            abort(403, 'Unauthorized.');
        }

        $lists = PmTaskList::query()
            ->where('project_id', $project->id)
            ->withCount('tasks')
            ->orderBy('order_index', 'asc')
            ->get();

        $uncategorizedCount = PmTask::query()
            ->where('project_id', $project->id)
            ->whereNull('task_list_id')
            ->whereNull('parent_task_id')
            ->count();

        return response()->json([
            'lists' => $lists->map(fn (PmTaskList $list) => [
                'id' => $list->id,
                'uuid' => $list->uuid,
                'project_id' => $list->project_id,
                'name' => $list->name,
                'order_index' => $list->order_index,
                'color' => $list->color,
                'tasks_count' => $list->tasks_count,
                'created_at' => $list->created_at?->toIso8601String(),
            ]),
            'uncategorized_count' => $uncategorizedCount,
        ]);
    }

    /**
     * Create a new task list for a project.
     */
    public function store(Request $request, string $projectUuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::where('uuid', $projectUuid)->firstOrFail();

        if (!$this->accessService->canManageTaskLists($user, $project)) {
            abort(403, 'Unauthorized: Only Managers and Owners can manage task lists.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|max:30',
        ]);

        $maxOrder = PmTaskList::where('project_id', $project->id)->max('order_index') ?? 0;

        $list = PmTaskList::create([
            'project_id' => $project->id,
            'name' => $validated['name'],
            'color' => $validated['color'] ?? null,
            'order_index' => $maxOrder + 1,
            'created_by' => $user->id,
        ]);

        PmActivityLog::record(
            actorId: $user->id,
            action: 'task_list_created',
            subjectType: 'task_list',
            subjectId: $list->id,
            projectId: $project->id,
            metadata: [
                'name' => $list->name,
                'order_index' => $list->order_index,
            ]
        );

        return response()->json([
            'message' => 'Task list created successfully.',
            'list' => [
                'id' => $list->id,
                'uuid' => $list->uuid,
                'project_id' => $list->project_id,
                'name' => $list->name,
                'order_index' => $list->order_index,
                'color' => $list->color,
                'tasks_count' => 0,
                'created_at' => $list->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Update/rename a task list.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $list = PmTaskList::with('project')->where('uuid', $uuid)->firstOrFail();

        if (!$this->accessService->canManageTaskLists($user, $list->project)) {
            abort(403, 'Unauthorized: Only Managers and Owners can update task lists.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'color' => 'nullable|string|max:30',
        ]);

        $oldName = $list->name;

        if (isset($validated['name'])) {
            $list->name = $validated['name'];
        }
        if (array_key_exists('color', $validated)) {
            $list->color = $validated['color'];
        }

        $list->save();

        PmActivityLog::record(
            actorId: $user->id,
            action: 'task_list_updated',
            subjectType: 'task_list',
            subjectId: $list->id,
            projectId: $list->project_id,
            metadata: [
                'old_name' => $oldName,
                'new_name' => $list->name,
            ]
        );

        return response()->json([
            'message' => 'Task list updated successfully.',
            'list' => [
                'id' => $list->id,
                'uuid' => $list->uuid,
                'project_id' => $list->project_id,
                'name' => $list->name,
                'order_index' => $list->order_index,
                'color' => $list->color,
            ],
        ]);
    }

    /**
     * Reorder task lists in a project.
     */
    public function reorder(Request $request, string $projectUuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::where('uuid', $projectUuid)->firstOrFail();

        if (!$this->accessService->canManageTaskLists($user, $project)) {
            abort(403, 'Unauthorized: Only Managers and Owners can reorder task lists.');
        }

        $validated = $request->validate([
            'list_uuids' => 'required|array',
            'list_uuids.*' => 'required|string|exists:pm_task_lists,uuid',
        ]);

        DB::transaction(function () use ($validated, $project) {
            foreach ($validated['list_uuids'] as $index => $listUuid) {
                PmTaskList::where('uuid', $listUuid)
                    ->where('project_id', $project->id)
                    ->update(['order_index' => $index + 1]);
            }
        });

        PmActivityLog::record(
            actorId: $user->id,
            action: 'task_lists_reordered',
            subjectType: 'task_list',
            subjectId: null,
            projectId: $project->id,
            metadata: [
                'count' => count($validated['list_uuids']),
            ]
        );

        return response()->json([
            'message' => 'Task lists reordered successfully.',
        ]);
    }

    /**
     * Delete a task list.
     * CRITICAL DATA PRESERVATION: All tasks in this list must have their task_list_id set to null,
     * preserving them in Uncategorized.
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $list = PmTaskList::with('project')->where('uuid', $uuid)->firstOrFail();

        if (!$this->accessService->canManageTaskLists($user, $list->project)) {
            abort(403, 'Unauthorized: Only Managers and Owners can delete task lists.');
        }

        $projectId = $list->project_id;
        $listName = $list->name;

        DB::transaction(function () use ($list) {
            // Preserve all tasks: disassociate from list to make them Uncategorized
            PmTask::where('task_list_id', $list->id)->update(['task_list_id' => null]);
            $list->delete();
        });

        PmActivityLog::record(
            actorId: $user->id,
            action: 'task_list_deleted',
            subjectType: 'task_list',
            subjectId: null,
            projectId: $projectId,
            metadata: [
                'name' => $listName,
            ]
        );

        return response()->json([
            'message' => 'Task list deleted successfully. Tasks have been moved to Uncategorized.',
        ]);
    }
}

