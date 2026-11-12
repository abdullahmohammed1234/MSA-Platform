<?php

namespace App\Pm\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Pm\Enums\DependencyType;
use App\Pm\Models\PmMilestone;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
use App\Pm\Models\PmTaskDependency;
use App\Pm\Services\PmSchedulingService;
use App\Pm\Services\ProjectAccessService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PmSchedulingController extends Controller
{
    public function __construct(
        protected ProjectAccessService $accessService,
        protected PmSchedulingService $schedulingService
    ) {}

    /**
     * Get detailed scheduling and conflict analysis for a task.
     */
    public function scheduling(Request $request, string $taskUuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $task = PmTask::with(['project', 'milestone'])->where('uuid', $taskUuid)->firstOrFail();

        if (!$this->accessService->canViewProject($user, $task->project)) {
            abort(403, 'Unauthorized.');
        }

        $conflicts = $this->schedulingService->detectConflicts($task);

        $milestoneInfo = null;
        if ($task->milestone) {
            $milestoneInfo = $this->schedulingService->evaluateMilestoneCondition($task->milestone);
        }

        return response()->json([
            'task' => [
                'id' => $task->id,
                'uuid' => $task->uuid,
                'title' => $task->title,
                'start_date' => $task->start_date?->toDateString(),
                'due_date' => $task->due_date?->toDateString(),
                'duration_days' => ($task->start_date && $task->due_date)
                    ? max(1, \Carbon\Carbon::parse($task->start_date)->diffInDays(\Carbon\Carbon::parse($task->due_date)) + 1)
                    : 1,
            ],
            'scheduling' => $conflicts,
            'milestone_condition' => $milestoneInfo,
        ]);
    }

    /**
     * Preview the cascade impact of rescheduling a task without mutating data.
     */
    public function previewReschedule(Request $request, string $taskUuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $task = PmTask::with('project')->where('uuid', $taskUuid)->firstOrFail();

        if (!$this->accessService->canUpdateTask($user, $task)) {
            abort(403, 'Unauthorized: Insufficient permissions to reschedule task.');
        }

        $validated = $request->validate([
            'start_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:start_date',
        ]);

        $preview = $this->schedulingService->previewCascade(
            rootTask: $task,
            newStartDate: $validated['start_date'],
            newDueDate: $validated['due_date']
        );

        return response()->json([
            'success' => true,
            'preview' => $preview,
        ]);
    }

    /**
     * Execute transactional rescheduling of a task, optionally cascading downstream.
     */
    public function reschedule(Request $request, string $taskUuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $task = PmTask::with('project')->where('uuid', $taskUuid)->firstOrFail();

        if (!$this->accessService->canUpdateTask($user, $task)) {
            abort(403, 'Unauthorized: Insufficient permissions to reschedule task.');
        }

        $validated = $request->validate([
            'start_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:start_date',
            'cascade' => 'nullable|boolean',
        ]);

        $cascade = (bool) ($validated['cascade'] ?? false);

        $result = $this->schedulingService->executeReschedule(
            task: $task,
            newStartDate: $validated['start_date'],
            newDueDate: $validated['due_date'],
            cascade: $cascade,
            actor: $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Task schedule updated successfully.' . ($cascade ? " Cascaded to {$result['affected_count']} task(s)." : ''),
            'task' => [
                'id' => $result['task']->id,
                'uuid' => $result['task']->uuid,
                'title' => $result['task']->title,
                'start_date' => $result['task']->start_date?->toDateString(),
                'due_date' => $result['task']->due_date?->toDateString(),
            ],
            'affected_count' => $result['affected_count'],
            'rescheduled_tasks' => $result['rescheduled_tasks'],
        ]);
    }

    /**
     * Evaluate milestone slippage and target date risk condition.
     */
    public function milestoneCondition(Request $request, string $milestoneUuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $milestone = PmMilestone::with('project')->where('uuid', $milestoneUuid)->firstOrFail();

        if (!$this->accessService->canViewProject($user, $milestone->project)) {
            abort(403, 'Unauthorized.');
        }

        $condition = $this->schedulingService->evaluateMilestoneCondition($milestone);

        return response()->json([
            'milestone' => [
                'id' => $milestone->id,
                'uuid' => $milestone->uuid,
                'title' => $milestone->title,
                'due_date' => $milestone->due_date?->toDateString(),
            ],
            'evaluation' => $condition,
        ]);
    }

    /**
     * Get optimized Gantt dataset including tasks, dependencies with conflicts,
     * milestones, task lists, and timeline bounds.
     */
    public function projectGantt(Request $request, string $projectUuid): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $project = PmProject::with(['milestones', 'taskLists'])->where('uuid', $projectUuid)->firstOrFail();

        if (!$this->accessService->canViewProject($user, $project)) {
            abort(403, 'Unauthorized.');
        }

        $tasks = PmTask::where('project_id', $project->id)
            ->with(['assignees:id,name,email,avatar', 'milestone:id,uuid,title', 'taskList:id,uuid,name'])
            ->orderByRaw('CASE WHEN start_date IS NULL THEN 1 ELSE 0 END, start_date ASC')
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date ASC')
            ->orderBy('id', 'asc')
            ->get();

        // Dependencies within this project
        $taskIds = $tasks->pluck('id')->all();
        $dependencies = PmTaskDependency::whereIn('task_id', $taskIds)
            ->with(['task:id,uuid,title,start_date,due_date', 'dependsOnTask:id,uuid,title,start_date,due_date'])
            ->get();

        // Compute conflicts on dependencies
        $formattedDeps = $dependencies->map(function (PmTaskDependency $dep) {
            $isConflict = false;
            $conflictReason = null;

            if ($dep->task && $dep->dependsOnTask) {
                if ($dep->dependency_type === DependencyType::FinishToStart) {
                    if ($dep->task->start_date && $dep->dependsOnTask->due_date) {
                        $minStart = $dep->dependsOnTask->due_date->copy()->addDays($dep->lag_days);
                        if ($dep->task->start_date->lt($minStart)) {
                            $isConflict = true;
                            $conflictReason = "Starts before predecessor due date ({$dep->dependsOnTask->due_date->toDateString()}) + {$dep->lag_days}d lag";
                        }
                    }
                } elseif ($dep->dependency_type === DependencyType::StartToStart) {
                    if ($dep->task->start_date && $dep->dependsOnTask->start_date) {
                        $minStart = $dep->dependsOnTask->start_date->copy()->addDays($dep->lag_days);
                        if ($dep->task->start_date->lt($minStart)) {
                            $isConflict = true;
                            $conflictReason = "Starts before predecessor start date ({$dep->dependsOnTask->start_date->toDateString()}) + {$dep->lag_days}d lag";
                        }
                    }
                } elseif ($dep->dependency_type === DependencyType::FinishToFinish) {
                    if ($dep->task->due_date && $dep->dependsOnTask->due_date) {
                        $minDue = $dep->dependsOnTask->due_date->copy()->addDays($dep->lag_days);
                        if ($dep->task->due_date->lt($minDue)) {
                            $isConflict = true;
                            $conflictReason = "Finishes before predecessor due date ({$dep->dependsOnTask->due_date->toDateString()}) + {$dep->lag_days}d lag";
                        }
                    }
                }
            }

            return [
                'id' => $dep->id,
                'task_id' => $dep->task_id,
                'task_uuid' => $dep->task?->uuid,
                'depends_on_task_id' => $dep->depends_on_task_id,
                'depends_on_task_uuid' => $dep->dependsOnTask?->uuid,
                'dependency_type' => $dep->dependency_type->value,
                'lag_days' => $dep->lag_days,
                'is_conflict' => $isConflict,
                'conflict_reason' => $conflictReason,
            ];
        });

        // Calculate timeline bounds
        $minDate = null;
        $maxDate = null;

        foreach ($tasks as $t) {
            $s = $t->start_date ? $t->start_date->copy() : $t->due_date?->copy();
            $d = $t->due_date ? $t->due_date->copy() : $t->start_date?->copy();

            if ($s && (!$minDate || $s->lt($minDate))) {
                $minDate = $s;
            }
            if ($d && (!$maxDate || $d->gt($maxDate))) {
                $maxDate = $d;
            }
        }

        foreach ($project->milestones as $m) {
            if ($m->due_date) {
                if (!$minDate || $m->due_date->lt($minDate)) {
                    $minDate = $m->due_date->copy();
                }
                if (!$maxDate || $m->due_date->gt($maxDate)) {
                    $maxDate = $m->due_date->copy();
                }
            }
        }

        $startDateStr = $minDate ? $minDate->copy()->subDays(3)->toDateString() : Carbon::now()->subDays(3)->toDateString();
        $endDateStr = $maxDate ? $maxDate->copy()->addDays(7)->toDateString() : Carbon::now()->addDays(28)->toDateString();
        $totalDays = Carbon::parse($startDateStr)->diffInDays(Carbon::parse($endDateStr)) + 1;

        $formattedTasks = $tasks->map(function (PmTask $t) {
            $duration = ($t->start_date && $t->due_date)
                ? max(1, Carbon::parse($t->start_date)->diffInDays(Carbon::parse($t->due_date)) + 1)
                : 1;

            return [
                'id' => $t->id,
                'uuid' => $t->uuid,
                'title' => $t->title,
                'status' => $t->status->value,
                'priority' => $t->priority->value,
                'start_date' => $t->start_date?->toDateString(),
                'due_date' => $t->due_date?->toDateString(),
                'duration_days' => $duration,
                'estimated_hours' => (float) $t->effective_estimated_hours,
                'effort_source' => $t->effort_source ?? 'inferred',
                'task_list_id' => $t->task_list_id,
                'task_list_name' => $t->taskList?->name,
                'milestone_id' => $t->milestone_id,
                'milestone_name' => $t->milestone?->title,
                'assigned_to' => $t->assigned_to,
                'assignees' => $t->assignees->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'avatar' => $u->avatar,
                ]),
                'completed_at' => $t->completed_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'project' => [
                    'id' => $project->id,
                    'uuid' => $project->uuid,
                    'name' => $project->name,
                    'start_date' => $project->start_date?->toDateString(),
                    'due_date' => $project->due_date?->toDateString(),
                ],
                'timeline_bounds' => [
                    'start_date' => $startDateStr,
                    'end_date' => $endDateStr,
                    'total_days' => $totalDays,
                ],
                'tasks' => $formattedTasks,
                'dependencies' => $formattedDeps,
                'milestones' => $project->milestones->map(fn ($m) => [
                    'id' => $m->id,
                    'uuid' => $m->uuid,
                    'title' => $m->title,
                    'due_date' => $m->due_date?->toDateString(),
                    'status' => $m->status->value,
                    'is_overdue' => $m->isOverdue(),
                ]),
                'task_lists' => $project->taskLists->map(fn ($l) => [
                    'id' => $l->id,
                    'uuid' => $l->uuid,
                    'name' => $l->name,
                    'order_index' => $l->order_index,
                ]),
            ],
        ]);
    }
}

