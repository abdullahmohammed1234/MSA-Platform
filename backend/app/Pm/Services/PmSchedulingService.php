<?php

namespace App\Pm\Services;

use App\Models\User;
use App\Pm\Enums\DependencyType;
use App\Pm\Enums\TaskStatus;
use App\Pm\Models\PmActivityLog;
use App\Pm\Models\PmMilestone;
use App\Pm\Models\PmTask;
use App\Pm\Models\PmTaskDependency;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PmSchedulingService
{
    /**
     * Maximum depth to prevent runaway cascades in deep dependency chains.
     */
    protected const MAX_CASCADE_DEPTH = 20;

    public function __construct(
        protected ProjectAccessService $accessService
    ) {}

    /**
     * Detect scheduling conflicts for a task based on its predecessor dependencies.
     *
     * @return array{
     *     has_conflicts: bool,
     *     has_unresolved: bool,
     *     conflicts: array<int, array<string, mixed>>,
     *     unresolved: array<int, array<string, mixed>>
     * }
     */
    public function detectConflicts(PmTask $task): array
    {
        $task->loadMissing([
            'dependencies' => function ($query) {
                $query->withPivot(['dependency_type', 'lag_days']);
            },
        ]);

        $conflicts = [];
        $unresolved = [];

        foreach ($task->dependencies as $predecessor) {
            // Completed or cancelled predecessors no longer trigger active scheduling conflicts
            if ($predecessor->status === TaskStatus::Completed || $predecessor->status === TaskStatus::Cancelled) {
                continue;
            }

            $rawType = $predecessor->pivot->dependency_type ?? 'finish_to_start';
            $dependencyType = $rawType instanceof DependencyType ? $rawType : DependencyType::from($rawType);
            $lagDays = (int) ($predecessor->pivot->lag_days ?? 0);

            switch ($dependencyType) {
                case DependencyType::FinishToStart:
                    if (!$predecessor->due_date || !$task->start_date) {
                        $unresolved[] = [
                            'predecessor_id' => $predecessor->id,
                            'predecessor_uuid' => $predecessor->uuid,
                            'predecessor_title' => $predecessor->title,
                            'dependency_type' => $dependencyType->value,
                            'lag_days' => $lagDays,
                            'reason' => 'Missing predecessor due date or successor start date.',
                        ];
                        break;
                    }

                    // Successor start must be >= predecessor due_date + lag_days + 1 day
                    $minStartDate = Carbon::parse($predecessor->due_date)->addDays($lagDays + 1);
                    $actualStartDate = Carbon::parse($task->start_date);

                    if ($actualStartDate->lt($minStartDate)) {
                        $conflicts[] = [
                            'predecessor_id' => $predecessor->id,
                            'predecessor_uuid' => $predecessor->uuid,
                            'predecessor_title' => $predecessor->title,
                            'dependency_type' => $dependencyType->value,
                            'dependency_type_label' => $dependencyType->label(),
                            'lag_days' => $lagDays,
                            'conflict_type' => 'finish_to_start_violation',
                            'conflict_reason' => sprintf(
                                'Successor starts on %s, before predecessor "%s" finishes on %s (min required start: %s).',
                                $actualStartDate->toDateString(),
                                $predecessor->title,
                                Carbon::parse($predecessor->due_date)->toDateString(),
                                $minStartDate->toDateString()
                            ),
                            'required_date' => $minStartDate->toDateString(),
                            'actual_date' => $actualStartDate->toDateString(),
                        ];
                    }
                    break;

                case DependencyType::StartToStart:
                    if (!$predecessor->start_date || !$task->start_date) {
                        $unresolved[] = [
                            'predecessor_id' => $predecessor->id,
                            'predecessor_uuid' => $predecessor->uuid,
                            'predecessor_title' => $predecessor->title,
                            'dependency_type' => $dependencyType->value,
                            'lag_days' => $lagDays,
                            'reason' => 'Missing predecessor start date or successor start date.',
                        ];
                        break;
                    }

                    $minStartDate = Carbon::parse($predecessor->start_date)->addDays($lagDays);
                    $actualStartDate = Carbon::parse($task->start_date);

                    if ($actualStartDate->lt($minStartDate)) {
                        $conflicts[] = [
                            'predecessor_id' => $predecessor->id,
                            'predecessor_uuid' => $predecessor->uuid,
                            'predecessor_title' => $predecessor->title,
                            'dependency_type' => $dependencyType->value,
                            'dependency_type_label' => $dependencyType->label(),
                            'lag_days' => $lagDays,
                            'conflict_type' => 'start_to_start_violation',
                            'conflict_reason' => sprintf(
                                'Successor starts on %s, before predecessor "%s" starts on %s (min required start: %s).',
                                $actualStartDate->toDateString(),
                                $predecessor->title,
                                Carbon::parse($predecessor->start_date)->toDateString(),
                                $minStartDate->toDateString()
                            ),
                            'required_date' => $minStartDate->toDateString(),
                            'actual_date' => $actualStartDate->toDateString(),
                        ];
                    }
                    break;

                case DependencyType::FinishToFinish:
                    if (!$predecessor->due_date || !$task->due_date) {
                        $unresolved[] = [
                            'predecessor_id' => $predecessor->id,
                            'predecessor_uuid' => $predecessor->uuid,
                            'predecessor_title' => $predecessor->title,
                            'dependency_type' => $dependencyType->value,
                            'lag_days' => $lagDays,
                            'reason' => 'Missing predecessor due date or successor due date.',
                        ];
                        break;
                    }

                    $minDueDate = Carbon::parse($predecessor->due_date)->addDays($lagDays);
                    $actualDueDate = Carbon::parse($task->due_date);

                    if ($actualDueDate->lt($minDueDate)) {
                        $conflicts[] = [
                            'predecessor_id' => $predecessor->id,
                            'predecessor_uuid' => $predecessor->uuid,
                            'predecessor_title' => $predecessor->title,
                            'dependency_type' => $dependencyType->value,
                            'dependency_type_label' => $dependencyType->label(),
                            'lag_days' => $lagDays,
                            'conflict_type' => 'finish_to_finish_violation',
                            'conflict_reason' => sprintf(
                                'Successor finishes on %s, before predecessor "%s" finishes on %s (min required due: %s).',
                                $actualDueDate->toDateString(),
                                $predecessor->title,
                                Carbon::parse($predecessor->due_date)->toDateString(),
                                $minDueDate->toDateString()
                            ),
                            'required_date' => $minDueDate->toDateString(),
                            'actual_date' => $actualDueDate->toDateString(),
                        ];
                    }
                    break;
            }
        }

        return [
            'has_conflicts' => count($conflicts) > 0,
            'has_unresolved' => count($unresolved) > 0,
            'conflicts' => $conflicts,
            'unresolved' => $unresolved,
        ];
    }

    /**
     * Calculate cascade rescheduling impact preview without applying changes to the database.
     *
     * @return array{
     *     root_task: array<string, mixed>,
     *     affected_tasks: array<int, array<string, mixed>>,
     *     total_affected_count: int,
     *     max_depth_reached: int
     * }
     */
    public function previewCascade(PmTask $rootTask, string $newStartDate, string $newDueDate): array
    {
        $virtualDates = [
            $rootTask->id => [
                'start_date' => $newStartDate,
                'due_date' => $newDueDate,
            ],
        ];

        $affectedTasks = [];
        $visited = [];
        $maxDepth = 0;

        $this->traverseDependentsCascade(
            taskId: $rootTask->id,
            virtualDates: $virtualDates,
            affectedTasks: $affectedTasks,
            visited: $visited,
            currentDepth: 1,
            maxDepth: $maxDepth
        );

        return [
            'root_task' => [
                'id' => $rootTask->id,
                'uuid' => $rootTask->uuid,
                'title' => $rootTask->title,
                'original_start_date' => $rootTask->start_date?->toDateString(),
                'original_due_date' => $rootTask->due_date?->toDateString(),
                'proposed_start_date' => $newStartDate,
                'proposed_due_date' => $newDueDate,
            ],
            'affected_tasks' => array_values($affectedTasks),
            'total_affected_count' => count($affectedTasks),
            'max_depth_reached' => $maxDepth,
        ];
    }

    /**
     * Recursively traverse and compute required shifts for successor tasks.
     */
    protected function traverseDependentsCascade(
        int $taskId,
        array &$virtualDates,
        array &$affectedTasks,
        array &$visited,
        int $currentDepth,
        int &$maxDepth
    ): void {
        if ($currentDepth > self::MAX_CASCADE_DEPTH) {
            return;
        }

        if ($currentDepth > $maxDepth) {
            $maxDepth = $currentDepth;
        }

        $visited[$taskId] = true;

        $predecessorDates = $virtualDates[$taskId];
        $predStart = Carbon::parse($predecessorDates['start_date']);
        $predDue = Carbon::parse($predecessorDates['due_date']);

        // Fetch direct successors
        $dependencies = PmTaskDependency::where('depends_on_task_id', $taskId)
            ->with(['task'])
            ->get();

        foreach ($dependencies as $dep) {
            $successor = $dep->task;
            if (!$successor || $successor->status === TaskStatus::Completed || $successor->status === TaskStatus::Cancelled) {
                continue;
            }

            $rawType = $dep->dependency_type ?? 'finish_to_start';
            $depType = $rawType instanceof DependencyType ? $rawType : DependencyType::from($rawType);
            $lagDays = (int) ($dep->lag_days ?? 0);

            // Determine successor's current schedule
            $currentStart = isset($virtualDates[$successor->id])
                ? Carbon::parse($virtualDates[$successor->id]['start_date'])
                : ($successor->start_date ? Carbon::parse($successor->start_date) : null);

            $currentDue = isset($virtualDates[$successor->id])
                ? Carbon::parse($virtualDates[$successor->id]['due_date'])
                : ($successor->due_date ? Carbon::parse($successor->due_date) : null);

            // Calculate current duration in inclusive days
            $durationDays = ($currentStart && $currentDue)
                ? max(1, $currentStart->diffInDays($currentDue) + 1)
                : 1;

            $needsShift = false;
            $newSuccStart = $currentStart ? $currentStart->copy() : null;
            $newSuccDue = $currentDue ? $currentDue->copy() : null;

            switch ($depType) {
                case DependencyType::FinishToStart:
                    $minRequiredStart = $predDue->copy()->addDays($lagDays + 1);
                    if (!$currentStart || $currentStart->lt($minRequiredStart)) {
                        $needsShift = true;
                        $newSuccStart = $minRequiredStart;
                        $newSuccDue = $minRequiredStart->copy()->addDays($durationDays - 1);
                    }
                    break;

                case DependencyType::StartToStart:
                    $minRequiredStart = $predStart->copy()->addDays($lagDays);
                    if (!$currentStart || $currentStart->lt($minRequiredStart)) {
                        $needsShift = true;
                        $newSuccStart = $minRequiredStart;
                        $newSuccDue = $minRequiredStart->copy()->addDays($durationDays - 1);
                    }
                    break;

                case DependencyType::FinishToFinish:
                    $minRequiredDue = $predDue->copy()->addDays($lagDays);
                    if (!$currentDue || $currentDue->lt($minRequiredDue)) {
                        $needsShift = true;
                        $newSuccDue = $minRequiredDue;
                        $newSuccStart = $minRequiredDue->copy()->subDays($durationDays - 1);
                    }
                    break;
            }

            if ($needsShift) {
                $virtualDates[$successor->id] = [
                    'start_date' => $newSuccStart->toDateString(),
                    'due_date' => $newSuccDue->toDateString(),
                ];

                $affectedTasks[$successor->id] = [
                    'id' => $successor->id,
                    'uuid' => $successor->uuid,
                    'title' => $successor->title,
                    'original_start_date' => $successor->start_date?->toDateString(),
                    'original_due_date' => $successor->due_date?->toDateString(),
                    'proposed_start_date' => $newSuccStart->toDateString(),
                    'proposed_due_date' => $newSuccDue->toDateString(),
                    'shift_days' => $currentStart ? $currentStart->diffInDays($newSuccStart, false) : 0,
                    'dependency_reason' => "Shifted by {$depType->label()} constraint from '{$dep->dependsOnTask?->title}'",
                ];

                // Propagate further downstream if not in cycle
                if (!isset($visited[$successor->id])) {
                    $this->traverseDependentsCascade(
                        taskId: $successor->id,
                        virtualDates: $virtualDates,
                        affectedTasks: $affectedTasks,
                        visited: $visited,
                        currentDepth: $currentDepth + 1,
                        maxDepth: $maxDepth
                    );
                }
            }
        }
    }

    /**
     * Transactionally execute rescheduling of a task and optionally cascade to downstream tasks.
     *
     * @return array{
     *     task: PmTask,
     *     affected_count: int,
     *     rescheduled_tasks: array<int, array<string, mixed>>
     * }
     */
    public function executeReschedule(
        PmTask $task,
        string $newStartDate,
        string $newDueDate,
        bool $cascade,
        User $actor
    ): array {
        return DB::transaction(function () use ($task, $newStartDate, $newDueDate, $cascade, $actor) {
            // Revalidate ordering
            if ($newDueDate < $newStartDate) {
                throw new \InvalidArgumentException('Due date must be on or after start date.');
            }

            $originalStart = $task->start_date?->toDateString();
            $originalDue = $task->due_date?->toDateString();

            // 1. Update root task
            $task->update([
                'start_date' => $newStartDate,
                'due_date' => $newDueDate,
            ]);

            PmActivityLog::record(
                actorId: $actor->id,
                action: 'task_rescheduled',
                subjectType: 'task',
                subjectId: $task->id,
                projectId: $task->project_id,
                metadata: [
                    'task_title' => $task->title,
                    'old_start_date' => $originalStart,
                    'new_start_date' => $newStartDate,
                    'old_due_date' => $originalDue,
                    'new_due_date' => $newDueDate,
                    'cascade' => $cascade,
                ]
            );

            $rescheduledTasks = [
                [
                    'id' => $task->id,
                    'uuid' => $task->uuid,
                    'title' => $task->title,
                    'start_date' => $newStartDate,
                    'due_date' => $newDueDate,
                ],
            ];

            // 2. Cascade if requested
            if ($cascade) {
                $preview = $this->previewCascade($task, $newStartDate, $newDueDate);
                foreach ($preview['affected_tasks'] as $item) {
                    $downstreamTask = PmTask::where('id', $item['id'])
                        ->where('project_id', $task->project_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$downstreamTask) {
                        continue;
                    }

                    $prevStart = $downstreamTask->start_date?->toDateString();
                    $prevDue = $downstreamTask->due_date?->toDateString();

                    $downstreamTask->update([
                        'start_date' => $item['proposed_start_date'],
                        'due_date' => $item['proposed_due_date'],
                    ]);

                    PmActivityLog::record(
                        actorId: $actor->id,
                        action: 'task_auto_rescheduled',
                        subjectType: 'task',
                        subjectId: $downstreamTask->id,
                        projectId: $downstreamTask->project_id,
                        metadata: [
                            'task_title' => $downstreamTask->title,
                            'triggered_by_task_id' => $task->id,
                            'old_start_date' => $prevStart,
                            'new_start_date' => $item['proposed_start_date'],
                            'old_due_date' => $prevDue,
                            'new_due_date' => $item['proposed_due_date'],
                        ]
                    );

                    $rescheduledTasks[] = [
                        'id' => $downstreamTask->id,
                        'uuid' => $downstreamTask->uuid,
                        'title' => $downstreamTask->title,
                        'start_date' => $item['proposed_start_date'],
                        'due_date' => $item['proposed_due_date'],
                    ];
                }
            }

            return [
                'task' => $task->fresh(['dependencies', 'dependents']),
                'affected_count' => count($rescheduledTasks),
                'rescheduled_tasks' => $rescheduledTasks,
            ];
        });
    }

    /**
     * Evaluate milestone slippage and target date risk condition.
     *
     * @return array{
     *     condition: string,
     *     milestone_due_date: string|null,
     *     max_task_due_date: string|null,
     *     exceeding_tasks_count: int,
     *     active_tasks_count: int
     * }
     */
    public function evaluateMilestoneCondition(PmMilestone $milestone): array
    {
        if (!$milestone->due_date) {
            return [
                'condition' => 'unresolved',
                'milestone_due_date' => null,
                'max_task_due_date' => null,
                'exceeding_tasks_count' => 0,
                'active_tasks_count' => 0,
            ];
        }

        $milestoneDate = Carbon::parse($milestone->due_date);

        $tasks = PmTask::where('milestone_id', $milestone->id)
            ->whereNull('deleted_at')
            ->get();

        if ($tasks->isEmpty()) {
            return [
                'condition' => 'on_track',
                'milestone_due_date' => $milestoneDate->toDateString(),
                'max_task_due_date' => null,
                'exceeding_tasks_count' => 0,
                'active_tasks_count' => 0,
            ];
        }

        $activeTasks = $tasks->filter(fn ($t) => $t->status !== TaskStatus::Completed && $t->status !== TaskStatus::Cancelled);

        if ($activeTasks->isEmpty()) {
            return [
                'condition' => 'completed',
                'milestone_due_date' => $milestoneDate->toDateString(),
                'max_task_due_date' => null,
                'exceeding_tasks_count' => 0,
                'active_tasks_count' => 0,
            ];
        }

        $maxTaskDue = null;
        $exceedingCount = 0;
        $nearDeadlineCount = 0;

        foreach ($activeTasks as $task) {
            if (!$task->due_date) {
                continue;
            }

            $taskDue = Carbon::parse($task->due_date);
            if ($maxTaskDue === null || $taskDue->gt($maxTaskDue)) {
                $maxTaskDue = $taskDue;
            }

            if ($taskDue->gt($milestoneDate)) {
                $exceedingCount++;
            } elseif ($taskDue->equalTo($milestoneDate) || $taskDue->diffInDays($milestoneDate) <= 1) {
                $nearDeadlineCount++;
            }
        }

        $condition = 'on_track';
        if ($exceedingCount > 0) {
            $condition = 'delayed';
        } elseif ($nearDeadlineCount > 0) {
            $condition = 'at_risk';
        }

        return [
            'condition' => $condition,
            'milestone_due_date' => $milestoneDate->toDateString(),
            'max_task_due_date' => $maxTaskDue?->toDateString(),
            'exceeding_tasks_count' => $exceedingCount,
            'active_tasks_count' => $activeTasks->count(),
        ];
    }
}

