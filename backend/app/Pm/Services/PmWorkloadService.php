<?php

namespace App\Pm\Services;

use App\Models\User;
use App\Pm\Enums\TaskStatus;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
use App\Pm\Models\PmUserCapacity;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PmWorkloadService
{
    public function __construct(
        protected ProjectAccessService $accessService
    ) {}

    /**
     * Calculate equal effort allocation among assignees with deterministic cents distribution.
     *
     * @return array<int, float> Mapping of user_id => allocated_hours
     */
    public function allocateTaskEffort(PmTask $task): array
    {
        $totalHours = (float) $task->effective_estimated_hours;
        if ($totalHours <= 0) {
            return [];
        }

        // Fetch eligible assignees
        $task->loadMissing(['assignees' => function ($q) {
            $q->where('users.is_active', true);
        }]);

        $assignees = $task->assignees;
        $primaryId = $task->assigned_to;

        // If no pivot assignees, fall back to primary assigned_to
        if ($assignees->isEmpty()) {
            if ($primaryId) {
                return [$primaryId => round($totalHours, 2)];
            }
            return [];
        }

        // Filter assignees to ensure active project role eligibility
        $eligibleAssignees = $assignees->filter(function (User $u) use ($task) {
            return $this->accessService->canBeAssignedToTask($u, $task->project);
        });

        if ($eligibleAssignees->isEmpty()) {
            if ($primaryId) {
                return [$primaryId => round($totalHours, 2)];
            }
            return [];
        }

        $n = $eligibleAssignees->count();
        $totalCents = (int) round($totalHours * 100);
        $baseCents = intdiv($totalCents, $n);
        $remainderCents = $totalCents % $n;

        // Sort: primary assignee first, then ascending by user_id
        $sortedAssignees = $eligibleAssignees->sort(function (User $a, User $b) use ($primaryId) {
            if ($a->id === $primaryId) return -1;
            if ($b->id === $primaryId) return 1;
            return $a->id <=> $b->id;
        })->values();

        $allocations = [];
        foreach ($sortedAssignees as $index => $assignee) {
            $cents = $baseCents + ($index < $remainderCents ? 1 : 0);
            $allocations[$assignee->id] = round($cents / 100, 2);
        }

        return $allocations;
    }

    /**
     * Compute working days (Mon-Fri default) between two dates.
     *
     * @return array<string> List of date strings Y-m-d
     */
    public function getWorkingDaysInRange(Carbon|string $start, Carbon|string $end): array
    {
        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->startOfDay();

        if ($endDate->lt($startDate)) {
            $endDate = $startDate->copy();
        }

        $workingDays = [];
        $curr = $startDate->copy();

        while ($curr->lte($endDate)) {
            if ($curr->isWeekday()) {
                $workingDays[] = $curr->toDateString();
            }
            $curr->addDay();
        }

        // Fallback for zero working days (e.g. weekend-only tasks)
        if (empty($workingDays)) {
            $workingDays[] = $startDate->toDateString();
        }

        return $workingDays;
    }

    /**
     * Distribute an allocated effort amount across a task's working days.
     *
     * @return array<string, float> Mapping of date Y-m-d => daily_hours
     */
    public function distributeEffortOverSchedule(float $allocatedHours, ?string $startDate, ?string $dueDate): array
    {
        if ($allocatedHours <= 0) {
            return [];
        }

        if (!$startDate && !$dueDate) {
            return [];
        }

        $s = $startDate ?: $dueDate;
        $d = $dueDate ?: $startDate;

        $workingDays = $this->getWorkingDaysInRange($s, $d);
        $dayCount = count($workingDays);

        if ($dayCount === 0) {
            return [];
        }

        $totalCents = (int) round($allocatedHours * 100);
        $baseCents = intdiv($totalCents, $dayCount);
        $remainderCents = $totalCents % $dayCount;

        $dailyEffort = [];
        foreach ($workingDays as $idx => $dateStr) {
            $cents = $baseCents + ($idx < $remainderCents ? 1 : 0);
            $dailyEffort[$dateStr] = round($cents / 100, 2);
        }

        return $dailyEffort;
    }

    /**
     * Compute comprehensive workload for a member across a date range.
     * Preserves Option B privacy by anonymizing external commitments when viewer lacks access.
     *
     * @return array<string, mixed>
     */
    public function getMemberWorkload(
        User $member,
        string $startDate,
        string $endDate,
        ?User $viewer = null,
        ?PmProject $projectScope = null
    ): array {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        // Load member capacity (with defensive fallback if migrations not yet executed)
        try {
            $capacity = PmUserCapacity::where('user_id', $member->id)->first();
        } catch (\Throwable $e) {
            $capacity = null;
        }
        $defaultWeekly = (float) config('pm.capacity.default_weekly_hours', 10.0);
        $defaultDaily = (float) config('pm.capacity.default_daily_hours', 2.0);
        $maxConcurrent = $capacity ? $capacity->max_concurrent_tasks : (int) config('pm.capacity.default_max_concurrent_tasks', 5);

        // Fetch all active tasks assigned to this member that overlap the date range
        $tasksQuery = PmTask::query()
            ->where(function ($q) use ($member) {
                $q->where('assigned_to', $member->id)
                    ->orWhereHas('assignees', fn ($sq) => $sq->where('users.id', $member->id));
            })
            ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])
            ->where(function ($q) use ($start, $end) {
                $startStr = $start->toDateString();
                $endStr = $end->toDateString();
                $q->where(function ($sub) use ($startStr, $endStr) {
                    $sub->whereNotNull('start_date')
                        ->whereNotNull('due_date')
                        ->where('due_date', '>=', $startStr)
                        ->where('start_date', '<=', $endStr);
                })->orWhere(function ($sub) use ($startStr, $endStr) {
                    $sub->whereNull('start_date')
                        ->whereNotNull('due_date')
                        ->whereBetween('due_date', [$startStr, $endStr]);
                })->orWhere(function ($sub) use ($startStr, $endStr) {
                    $sub->whereNotNull('start_date')
                        ->whereNull('due_date')
                        ->whereBetween('start_date', [$startStr, $endStr]);
                });
            })
            ->with(['project.department', 'assignees', 'taskList']);

        $tasks = $tasksQuery->get();

        // If project scope is specified, we separate in-project tasks from external tasks
        $inScopeTasks = [];
        $externalCommitmentsDaily = []; // date => total_external_hours
        $dailyAllocatedHours = [];      // date => total_hours
        $dailyTaskCounts = [];          // date => active_task_count

        // Initialize daily tracking
        $curr = $start->copy();
        while ($curr->lte($end)) {
            $dateStr = $curr->toDateString();
            $dailyAllocatedHours[$dateStr] = 0.0;
            $dailyTaskCounts[$dateStr] = 0;
            $externalCommitmentsDaily[$dateStr] = 0.0;
            $curr->addDay();
        }

        foreach ($tasks as $task) {
            $allocations = $this->allocateTaskEffort($task);
            $userEffort = $allocations[$member->id] ?? 0.0;
            if ($userEffort <= 0) {
                continue;
            }

            $dailyDistribution = $this->distributeEffortOverSchedule(
                $userEffort,
                $task->start_date?->toDateString(),
                $task->due_date?->toDateString()
            );

            $isViewable = $viewer ? $this->accessService->canViewProject($viewer, $task->project) : true;
            $isSameProject = $projectScope ? ($task->project_id === $projectScope->id) : true;

            if ($isViewable && $isSameProject) {
                $inScopeTasks[] = [
                    'id' => $task->id,
                    'uuid' => $task->uuid,
                    'title' => $task->title,
                    'project_id' => $task->project_id,
                    'project_uuid' => $task->project?->uuid,
                    'project_name' => $task->project?->name,
                    'status' => $task->status->value,
                    'priority' => $task->priority->value,
                    'start_date' => $task->start_date?->toDateString(),
                    'due_date' => $task->due_date?->toDateString(),
                    'allocated_hours' => $userEffort,
                    'is_confidential' => false,
                ];
            } else {
                $inScopeTasks[] = [
                    'id' => null,
                    'uuid' => null,
                    'title' => 'Confidential External Commitment',
                    'project_id' => null,
                    'project_uuid' => null,
                    'project_name' => null,
                    'status' => 'in_progress',
                    'priority' => null,
                    'start_date' => $task->start_date?->toDateString(),
                    'due_date' => $task->due_date?->toDateString(),
                    'allocated_hours' => $userEffort,
                    'is_confidential' => true,
                ];
            }

            // Distribute into daily buckets
            foreach ($dailyDistribution as $dStr => $hrs) {
                if (isset($dailyAllocatedHours[$dStr])) {
                    $dailyAllocatedHours[$dStr] = round($dailyAllocatedHours[$dStr] + $hrs, 2);
                    $dailyTaskCounts[$dStr]++;

                    if (!$isViewable || !$isSameProject) {
                        $externalCommitmentsDaily[$dStr] = round($externalCommitmentsDaily[$dStr] + $hrs, 2);
                    }
                }
            }
        }

        // Build daily breakdown records
        $dailyBreakdown = [];
        $overAllocatedDaysCount = 0;
        $totalAllocatedHours = 0.0;
        $maxDailyUtilization = 0.0;

        $curr = $start->copy();
        while ($curr->lte($end)) {
            $dateStr = $curr->toDateString();
            $dayOfWeek = $curr->dayOfWeekIso; // 1 = Monday ... 7 = Sunday

            $dayCapacity = $capacity
                ? $capacity->getDailyCapacityForDay($dayOfWeek)
                : (($dayOfWeek >= 1 && $dayOfWeek <= 5) ? $defaultDaily : 0.0);

            $allocated = $dailyAllocatedHours[$dateStr] ?? 0.0;
            $totalAllocatedHours = round($totalAllocatedHours + $allocated, 2);

            $utilization = $dayCapacity > 0
                ? round(($allocated / $dayCapacity) * 100, 1)
                : ($allocated > 0 ? 999.0 : 0.0);

            if ($utilization > $maxDailyUtilization) {
                $maxDailyUtilization = $utilization;
            }

            $classification = 'available';
            if ($utilization > 100.0) {
                $classification = 'over_allocated';
                $overAllocatedDaysCount++;
            } elseif ($utilization >= 70.0) {
                $classification = 'optimal';
            } elseif ($utilization > 0.0) {
                $classification = 'under_utilized';
            }

            $dailyBreakdown[$dateStr] = [
                'date' => $dateStr,
                'day_of_week' => $dayOfWeek,
                'capacity_hours' => $dayCapacity,
                'allocated_hours' => $allocated,
                'external_hours' => $externalCommitmentsDaily[$dateStr] ?? 0.0,
                'remaining_hours' => round(max(0, $dayCapacity - $allocated), 2),
                'active_tasks_count' => $dailyTaskCounts[$dateStr] ?? 0,
                'utilization_rate' => $utilization,
                'status' => $classification,
            ];

            $curr->addDay();
        }

        return [
            'user' => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'avatar' => $member->avatar,
            ],
            'capacity' => [
                'weekly_hours' => $capacity ? (float) $capacity->weekly_capacity_hours : $defaultWeekly,
                'daily_hours' => $capacity ? (float) $capacity->daily_capacity_hours : $defaultDaily,
                'max_concurrent_tasks' => $maxConcurrent,
            ],
            'summary' => [
                'total_allocated_hours' => $totalAllocatedHours,
                'active_tasks_count' => count($inScopeTasks),
                'over_allocated_days' => $overAllocatedDaysCount,
                'max_daily_utilization' => $maxDailyUtilization,
                'is_over_allocated' => $overAllocatedDaysCount > 0,
            ],
            'tasks' => $inScopeTasks,
            'daily_breakdown' => $dailyBreakdown,
        ];
    }

    /**
     * Compute team workload across all eligible project members.
     *
     * @return array<string, mixed>
     */
    public function getProjectTeamWorkload(PmProject $project, string $startDate, string $endDate, User $viewer): array
    {
        $eligibleMembers = $this->accessService->getEligibleTaskAssignees($project);

        $teamWorkload = [];
        foreach ($eligibleMembers as $member) {
            $teamWorkload[] = $this->getMemberWorkload(
                member: $member,
                startDate: $startDate,
                endDate: $endDate,
                viewer: $viewer,
                projectScope: $project
            );
        }

        return [
            'project' => [
                'id' => $project->id,
                'uuid' => $project->uuid,
                'name' => $project->name,
            ],
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'team_workload' => $teamWorkload,
        ];
    }
}
