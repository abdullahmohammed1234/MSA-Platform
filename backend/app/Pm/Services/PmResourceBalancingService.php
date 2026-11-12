<?php

namespace App\Pm\Services;

use App\Models\User;
use App\Pm\Enums\TaskStatus;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
use App\Pm\Models\PmUserCapacity;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PmResourceBalancingService
{
    public function __construct(
        protected ProjectAccessService $accessService,
        protected PmWorkloadService $workloadService
    ) {}

    /**
     * Generate advisory balancing suggestions for a project.
     * Identifies over-allocated contributors and suggests reassigning unblocked tasks
     * to eligible peers with available capacity.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBalancingSuggestions(PmProject $project, User $viewer): array
    {
        // 1. Determine date range across project active tasks
        $activeTasks = PmTask::where('project_id', $project->id)
            ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])
            ->get();

        if ($activeTasks->isEmpty()) {
            return [];
        }

        $minDate = null;
        $maxDate = null;

        foreach ($activeTasks as $task) {
            $s = $task->start_date ? $task->start_date->copy() : $task->due_date?->copy();
            $d = $task->due_date ? $task->due_date->copy() : $task->start_date?->copy();

            if ($s && (!$minDate || $s->lt($minDate))) {
                $minDate = $s;
            }
            if ($d && (!$maxDate || $d->gt($maxDate))) {
                $maxDate = $d;
            }
        }

        $startDate = $minDate ? $minDate->toDateString() : now()->toDateString();
        $endDate = $maxDate ? $maxDate->toDateString() : now()->addDays(14)->toDateString();

        // 2. Fetch team workload
        $teamWorkload = $this->workloadService->getProjectTeamWorkload($project, $startDate, $endDate, $viewer);
        $memberWorkloads = $teamWorkload['team_workload'] ?? [];

        // Map member workloads by user id
        $workloadByUserId = [];
        $overAllocatedUserIds = [];

        foreach ($memberWorkloads as $mw) {
            $uId = $mw['user']['id'];
            $workloadByUserId[$uId] = $mw;
            if ($mw['summary']['is_over_allocated'] ?? false) {
                $overAllocatedUserIds[] = $uId;
            }
        }

        if (empty($overAllocatedUserIds)) {
            return [];
        }

        $eligibleAssignees = $this->accessService->getEligibleTaskAssignees($project);
        $suggestions = [];

        // 3. For each over-allocated user, inspect their in-scope tasks
        foreach ($overAllocatedUserIds as $sourceUserId) {
            $sourceMw = $workloadByUserId[$sourceUserId] ?? null;
            if (!$sourceMw) continue;

            $sourceUser = User::find($sourceUserId);
            if (!$sourceUser) continue;

            $candidateTasks = $activeTasks->filter(function (PmTask $t) use ($sourceUserId) {
                return (int) $t->assigned_to === (int) $sourceUserId;
            });

            foreach ($candidateTasks as $task) {
                $taskHours = (float) $task->effective_estimated_hours;
                $sDate = $task->start_date ? $task->start_date->toDateString() : $task->due_date?->toDateString();
                $dDate = $task->due_date ? $task->due_date->toDateString() : $task->start_date?->toDateString();

                if (!$sDate || !$dDate) continue;

                // Find candidate peers: eligible, not source user
                foreach ($eligibleAssignees as $peer) {
                    if ($peer->id === $sourceUserId) {
                        continue;
                    }

                    $peerMw = $workloadByUserId[$peer->id] ?? null;
                    if (!$peerMw) {
                        continue;
                    }

                    // Check peer's current utilization during the task's schedule range
                    $peerBreakdown = $peerMw['daily_breakdown'] ?? [];
                    $taskWorkingDays = $this->workloadService->getWorkingDaysInRange($sDate, $dDate);
                    $taskWorkingDayCount = count($taskWorkingDays);
                    $dailyTaskHours = $taskWorkingDayCount > 0 ? ($taskHours / $taskWorkingDayCount) : $taskHours;

                    $peerCanTake = true;
                    $peerMaxUtil = 0.0;
                    $peerDailyCap = $peerMw['capacity']['daily_hours'] ?? 2.0;

                    foreach ($taskWorkingDays as $wDate) {
                        $pDay = $peerBreakdown[$wDate] ?? null;
                        $currentAllocated = $pDay['allocated_hours'] ?? 0.0;
                        $projectedAllocated = $currentAllocated + $dailyTaskHours;
                        $projectedUtil = $peerDailyCap > 0 ? ($projectedAllocated / $peerDailyCap) * 100 : 999.0;

                        if ($projectedUtil > 100.0) {
                            $peerCanTake = false;
                            break;
                        }

                        $curUtil = $pDay['utilization_rate'] ?? 0.0;
                        if ($curUtil > $peerMaxUtil) {
                            $peerMaxUtil = $curUtil;
                        }
                    }

                    if ($peerCanTake) {
                        $sourceDailyCap = $sourceMw['capacity']['daily_hours'] ?? 2.0;
                        $sourceUtilImpact = $sourceDailyCap > 0 ? ($dailyTaskHours / $sourceDailyCap) * 100 : 0;
                        $peerUtilImpact = $peerDailyCap > 0 ? ($dailyTaskHours / $peerDailyCap) * 100 : 0;

                        $suggestions[] = [
                            'id' => (string) Str::uuid(),
                            'task_id' => $task->id,
                            'task_uuid' => $task->uuid,
                            'task_title' => $task->title,
                            'estimated_hours' => $taskHours,
                            'start_date' => $sDate,
                            'due_date' => $dDate,
                            'source_user' => [
                                'id' => $sourceUser->id,
                                'name' => $sourceUser->name,
                                'email' => $sourceUser->email,
                                'avatar' => $sourceUser->avatar,
                                'current_utilization' => $sourceMw['summary']['max_daily_utilization'] ?? 100.0,
                                'projected_utilization' => max(0, round(($sourceMw['summary']['max_daily_utilization'] ?? 100.0) - $sourceUtilImpact, 1)),
                            ],
                            'suggested_assignee' => [
                                'id' => $peer->id,
                                'name' => $peer->name,
                                'email' => $peer->email,
                                'avatar' => $peer->avatar,
                                'current_utilization' => $peerMaxUtil,
                                'projected_utilization' => round($peerMaxUtil + $peerUtilImpact, 1),
                            ],
                            'reason' => "Reassigning '{$task->title}' reduces {$sourceUser->name}'s workload while {$peer->name} has capacity available.",
                        ];

                        // One recommendation per task is sufficient
                        break;
                    }
                }
            }
        }

        return $suggestions;
    }

    /**
     * Apply a balancing suggestion by safely reassigning the task to the target user.
     */
    public function applyBalancingSuggestion(
        PmProject $project,
        string $taskUuid,
        int $sourceUserId,
        int $targetUserId,
        User $actor
    ): PmTask {
        if (!$this->accessService->canUpdateProject($actor, $project)) {
            throw new AccessDeniedHttpException('You do not have permission to reassign tasks in this project.');
        }

        $task = PmTask::where('project_id', $project->id)
            ->where('uuid', $taskUuid)
            ->first();

        if (!$task) {
            throw new NotFoundHttpException('Task not found in this project.');
        }

        $targetUser = User::find($targetUserId);
        if (!$targetUser || !$targetUser->is_active) {
            throw new InvalidArgumentException('Target user is invalid or inactive.');
        }

        if (!$this->accessService->canBeAssignedToTask($targetUser, $project)) {
            throw new InvalidArgumentException("User {$targetUser->name} is not eligible for task assignment in this project.");
        }

        return DB::transaction(function () use ($task, $sourceUserId, $targetUser) {
            $task->assigned_to = $targetUser->id;
            $task->save();

            // Synchronize multi-assignee pivot if present
            $existingAssigneeIds = $task->assignees()->pluck('users.id')->all();
            if (!empty($existingAssigneeIds)) {
                $updatedAssigneeIds = array_diff($existingAssigneeIds, [$sourceUserId]);
                if (!in_array($targetUser->id, $updatedAssigneeIds, true)) {
                    $updatedAssigneeIds[] = $targetUser->id;
                }
                $task->assignees()->sync(array_values($updatedAssigneeIds));
            }

            return $task->fresh(['assignees', 'project']);
        });
    }
}

