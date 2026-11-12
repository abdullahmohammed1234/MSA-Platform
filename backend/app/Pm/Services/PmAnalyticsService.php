<?php

namespace App\Pm\Services;

use App\Models\User;
use App\Pm\Enums\MilestoneStatus;
use App\Pm\Enums\ProjectPriority;
use App\Pm\Enums\TaskStatus;
use App\Pm\Models\PmActivityLog;
use App\Pm\Models\PmMilestone;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PmAnalyticsService
{
    public function __construct(
        protected ProjectAccessService $accessService,
        protected PmWorkloadService $workloadService
    ) {}

    /**
     * Compute comprehensive analytics for a single project.
     */
    public function getProjectAnalytics(PmProject $project, User $user): array
    {
        if (!$this->accessService->canViewProject($user, $project)) {
            abort(403, 'Unauthorized.');
        }

        $now = Carbon::now();
        $startOfToday = $now->copy()->startOfDay();

        // 1. Task Counts & Status Distribution
        $tasks = PmTask::where('project_id', $project->id)->get();
        $totalTasks = $tasks->count();
        $todoTasks = $tasks->where('status', TaskStatus::Todo)->count();
        $inProgressTasks = $tasks->where('status', TaskStatus::InProgress)->count();
        $reviewTasks = $tasks->where('status', TaskStatus::Review)->count();
        $completedTasks = $tasks->where('status', TaskStatus::Completed)->count();
        $cancelledTasks = $tasks->where('status', TaskStatus::Cancelled)->count();

        $overdueTasks = $tasks->filter(function (PmTask $t) use ($startOfToday) {
            return $t->status !== TaskStatus::Completed
                && $t->status !== TaskStatus::Cancelled
                && $t->due_date !== null
                && Carbon::parse($t->due_date)->lt($startOfToday);
        })->count();

        $completionRate = $totalTasks > 0
            ? round(($completedTasks / $totalTasks) * 100, 1)
            : 0.0;

        // 2. Effort Estimation Metrics
        $totalEstimatedHours = round((float) $tasks->sum(fn ($t) => (float) $t->effective_estimated_hours), 2);
        $completedEstimatedHours = round((float) $tasks->where('status', TaskStatus::Completed)
            ->sum(fn ($t) => (float) $t->effective_estimated_hours), 2);
        $remainingEstimatedHours = round((float) $tasks->whereNotIn('status', [TaskStatus::Completed, TaskStatus::Cancelled])
            ->sum(fn ($t) => (float) $t->effective_estimated_hours), 2);

        // 3. Priority Breakdown
        $priorityCounts = [
            'low' => $tasks->where('priority', ProjectPriority::Low)->count(),
            'medium' => $tasks->where('priority', ProjectPriority::Medium)->count(),
            'high' => $tasks->where('priority', ProjectPriority::High)->count(),
            'urgent' => $tasks->where('priority', ProjectPriority::Urgent)->count(),
        ];

        // 4. Milestone Performance
        $milestones = PmMilestone::where('project_id', $project->id)->get();
        $totalMilestones = $milestones->count();
        $completedMilestones = $milestones->where('status', MilestoneStatus::Reached)->count();
        $pendingMilestones = $milestones->where('status', MilestoneStatus::Pending)->count();

        $overdueMilestones = $milestones->filter(function (PmMilestone $m) use ($startOfToday) {
            return $m->status !== MilestoneStatus::Reached
                && $m->due_date !== null
                && Carbon::parse($m->due_date)->lt($startOfToday);
        })->count();

        $onTimeMilestones = 0;
        $totalDelayDays = 0;
        $delayedCount = 0;

        foreach ($milestones->where('status', MilestoneStatus::Reached) as $m) {
            if ($m->due_date && $m->completed_at) {
                $completedAt = Carbon::parse($m->completed_at)->startOfDay();
                $dueDate = Carbon::parse($m->due_date)->startOfDay();

                if ($completedAt->lte($dueDate)) {
                    $onTimeMilestones++;
                } else {
                    $delayedCount++;
                    $totalDelayDays += $dueDate->diffInDays($completedAt);
                }
            } else {
                $onTimeMilestones++;
            }
        }

        $onTimeMilestoneRate = $completedMilestones > 0
            ? round(($onTimeMilestones / $completedMilestones) * 100, 1)
            : 100.0;
        $avgMilestoneDelayDays = $delayedCount > 0
            ? round($totalDelayDays / $delayedCount, 1)
            : 0.0;

        // 5. Lead Time & Cycle Time Analysis
        $leadTimeDays = [];
        $cycleTimeDays = [];

        $completedTaskRecords = $tasks->where('status', TaskStatus::Completed)->whereNotNull('completed_at');
        foreach ($completedTaskRecords as $task) {
            $created = Carbon::parse($task->created_at);
            $completed = Carbon::parse($task->completed_at);
            $leadTimeDays[] = max(0, $created->diffInHours($completed) / 24);

            // Cycle time: find when work began (first in_progress log or start_date)
            $firstProgressLog = PmActivityLog::where('project_id', $project->id)
                ->where('entity_type', 'task')
                ->where('entity_id', $task->id)
                ->where(function ($q) {
                    $q->where('metadata->new_status', 'in_progress')
                      ->orWhere('action', 'task_started');
                })
                ->orderBy('created_at', 'asc')
                ->first();

            if ($firstProgressLog) {
                $startWork = Carbon::parse($firstProgressLog->created_at);
                $cycleTimeDays[] = max(0, $startWork->diffInHours($completed) / 24);
            } elseif ($task->start_date) {
                $startWork = Carbon::parse($task->start_date);
                $cycleTimeDays[] = max(0, $startWork->diffInHours($completed) / 24);
            }
        }

        $avgLeadTime = !empty($leadTimeDays)
            ? round(array_sum($leadTimeDays) / count($leadTimeDays), 1)
            : null;
        $avgCycleTime = !empty($cycleTimeDays)
            ? round(array_sum($cycleTimeDays) / count($cycleTimeDays), 1)
            : null;

        // 6. Task Velocity (Last 8 Weeks)
        $weeklyVelocity = $this->calculateWeeklyVelocity(
            projectId: $project->id,
            weeksCount: 8
        );

        // 7. Team Workload Summary
        $teamWorkload = $this->workloadService->getProjectTeamWorkload(
            project: $project,
            startDate: $now->copy()->startOfWeek()->toDateString(),
            endDate: $now->copy()->endOfWeek()->toDateString(),
            viewer: $user
        );

        return [
            'project' => [
                'id' => $project->id,
                'uuid' => $project->uuid,
                'name' => $project->name,
                'status' => $project->status->value,
                'completion_rate' => $completionRate,
            ],
            'task_counts' => [
                'total' => $totalTasks,
                'completed' => $completedTasks,
                'in_progress' => $inProgressTasks,
                'todo' => $todoTasks,
                'review' => $reviewTasks,
                'cancelled' => $cancelledTasks,
                'overdue' => $overdueTasks,
            ],
            'effort_hours' => [
                'total' => $totalEstimatedHours,
                'completed' => $completedEstimatedHours,
                'remaining' => $remainingEstimatedHours,
            ],
            'priority_distribution' => $priorityCounts,
            'milestones' => [
                'total' => $totalMilestones,
                'completed' => $completedMilestones,
                'pending' => $pendingMilestones,
                'overdue' => $overdueMilestones,
                'on_time_rate' => $onTimeMilestoneRate,
                'avg_delay_days' => $avgMilestoneDelayDays,
            ],
            'cycle_time_metrics' => [
                'avg_lead_time_days' => $avgLeadTime,
                'avg_cycle_time_days' => $avgCycleTime,
                'sample_size' => count($completedTaskRecords),
                'has_sufficient_history' => count($completedTaskRecords) >= 3,
            ],
            'velocity_trend' => $weeklyVelocity,
            'team_workload' => $teamWorkload,
        ];
    }

    /**
     * Compute aggregated department-level analytics scoped strictly to accessible projects (Option B).
     */
    public function getDepartmentAnalytics(int $departmentId, User $user): array
    {
        $accessibleProjects = PmProject::accessibleTo($user)
            ->where('department_id', $departmentId)
            ->get();

        $projectIds = $accessibleProjects->pluck('id')->all();

        if (empty($projectIds)) {
            return [
                'department_id' => $departmentId,
                'accessible_projects_count' => 0,
                'task_counts' => [
                    'total' => 0,
                    'completed' => 0,
                    'in_progress' => 0,
                    'overdue' => 0,
                ],
                'completion_rate' => 0.0,
                'effort_hours' => [
                    'total' => 0.0,
                    'completed' => 0.0,
                    'remaining' => 0.0,
                ],
                'projects' => [],
                'velocity_trend' => [],
            ];
        }

        $now = Carbon::now();
        $startOfToday = $now->copy()->startOfDay();

        $tasks = PmTask::whereIn('project_id', $projectIds)->get();
        $totalTasks = $tasks->count();
        $completedTasks = $tasks->where('status', TaskStatus::Completed)->count();
        $inProgressTasks = $tasks->where('status', TaskStatus::InProgress)->count();
        $overdueTasks = $tasks->filter(function (PmTask $t) use ($startOfToday) {
            return $t->status !== TaskStatus::Completed
                && $t->status !== TaskStatus::Cancelled
                && $t->due_date !== null
                && Carbon::parse($t->due_date)->lt($startOfToday);
        })->count();

        $completionRate = $totalTasks > 0
            ? round(($completedTasks / $totalTasks) * 100, 1)
            : 0.0;

        $totalHours = round((float) $tasks->sum(fn ($t) => (float) $t->effective_estimated_hours), 2);
        $completedHours = round((float) $tasks->where('status', TaskStatus::Completed)
            ->sum(fn ($t) => (float) $t->effective_estimated_hours), 2);
        $remainingHours = round((float) $tasks->whereNotIn('status', [TaskStatus::Completed, TaskStatus::Cancelled])
            ->sum(fn ($t) => (float) $t->effective_estimated_hours), 2);

        $projectBreakdowns = $accessibleProjects->map(function (PmProject $p) use ($tasks) {
            $pTasks = $tasks->where('project_id', $p->id);
            $pTotal = $pTasks->count();
            $pCompleted = $pTasks->where('status', TaskStatus::Completed)->count();

            return [
                'id' => $p->id,
                'uuid' => $p->uuid,
                'name' => $p->name,
                'status' => $p->status->value,
                'total_tasks' => $pTotal,
                'completed_tasks' => $pCompleted,
                'completion_rate' => $pTotal > 0 ? round(($pCompleted / $pTotal) * 100, 1) : 0.0,
            ];
        })->all();

        $weeklyVelocity = $this->calculateWeeklyVelocity(
            projectIds: $projectIds,
            weeksCount: 8
        );

        return [
            'department_id' => $departmentId,
            'accessible_projects_count' => count($accessibleProjects),
            'task_counts' => [
                'total' => $totalTasks,
                'completed' => $completedTasks,
                'in_progress' => $inProgressTasks,
                'overdue' => $overdueTasks,
            ],
            'completion_rate' => $completionRate,
            'effort_hours' => [
                'total' => $totalHours,
                'completed' => $completedHours,
                'remaining' => $remainingHours,
            ],
            'projects' => $projectBreakdowns,
            'velocity_trend' => $weeklyVelocity,
        ];
    }

    /**
     * Compute dashboard overview analytics across all accessible projects.
     */
    public function getDashboardAnalytics(User $user): array
    {
        $accessibleProjectIds = PmProject::accessibleTo($user)->pluck('id')->all();

        if (empty($accessibleProjectIds)) {
            return [
                'accessible_projects_count' => 0,
                'total_tasks' => 0,
                'completed_tasks' => 0,
                'in_progress_tasks' => 0,
                'overdue_tasks' => 0,
                'my_open_tasks' => 0,
                'my_overdue_tasks' => 0,
                'velocity_trend' => [],
            ];
        }

        $now = Carbon::now();
        $startOfToday = $now->copy()->startOfDay();

        $tasks = PmTask::whereIn('project_id', $accessibleProjectIds)->get();
        $totalTasks = $tasks->count();
        $completedTasks = $tasks->where('status', TaskStatus::Completed)->count();
        $inProgressTasks = $tasks->where('status', TaskStatus::InProgress)->count();

        $overdueTasks = $tasks->filter(function (PmTask $t) use ($startOfToday) {
            return $t->status !== TaskStatus::Completed
                && $t->status !== TaskStatus::Cancelled
                && $t->due_date !== null
                && Carbon::parse($t->due_date)->lt($startOfToday);
        })->count();

        // User's own assigned tasks
        $myTasks = $tasks->filter(function (PmTask $t) use ($user) {
            return (int) $t->assigned_to === (int) $user->id;
        });

        $myOpen = $myTasks->whereNotIn('status', [TaskStatus::Completed, TaskStatus::Cancelled])->count();
        $myOverdue = $myTasks->filter(function (PmTask $t) use ($startOfToday) {
            return $t->status !== TaskStatus::Completed
                && $t->status !== TaskStatus::Cancelled
                && $t->due_date !== null
                && Carbon::parse($t->due_date)->lt($startOfToday);
        })->count();

        $weeklyVelocity = $this->calculateWeeklyVelocity(
            projectIds: $accessibleProjectIds,
            weeksCount: 8
        );

        return [
            'accessible_projects_count' => count($accessibleProjectIds),
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'in_progress_tasks' => $inProgressTasks,
            'overdue_tasks' => $overdueTasks,
            'my_open_tasks' => $myOpen,
            'my_overdue_tasks' => $myOverdue,
            'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0.0,
            'velocity_trend' => $weeklyVelocity,
        ];
    }

    /**
     * Calculate weekly task creation vs completion velocity over N weeks.
     */
    protected function calculateWeeklyVelocity(?int $projectId = null, ?array $projectIds = null, int $weeksCount = 8): array
    {
        $now = Carbon::now();
        $weeks = [];

        // Build list of week intervals from oldest to newest
        for ($i = $weeksCount - 1; $i >= 0; $i--) {
            $weekStart = $now->copy()->subWeeks($i)->startOfWeek();
            $weekEnd = $weekStart->copy()->endOfWeek();

            $createdQuery = PmTask::query()
                ->whereBetween('created_at', [$weekStart, $weekEnd]);

            $completedQuery = PmTask::query()
                ->where('status', TaskStatus::Completed->value)
                ->whereBetween('completed_at', [$weekStart, $weekEnd]);

            if ($projectId !== null) {
                $createdQuery->where('project_id', $projectId);
                $completedQuery->where('project_id', $projectId);
            } elseif (!empty($projectIds)) {
                $createdQuery->whereIn('project_id', $projectIds);
                $completedQuery->whereIn('project_id', $projectIds);
            }

            $weeks[] = [
                'week_number' => $weekStart->weekOfYear,
                'week_label' => $weekStart->format('M d'),
                'start_date' => $weekStart->toDateString(),
                'end_date' => $weekEnd->toDateString(),
                'tasks_created' => $createdQuery->count(),
                'tasks_completed' => $completedQuery->count(),
            ];
        }

        return $weeks;
    }
}
