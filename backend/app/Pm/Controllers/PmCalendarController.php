<?php

namespace App\Pm\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Pm\Enums\ProjectVisibility;
use App\Pm\Models\PmMilestone;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmProjectMember;
use App\Pm\Models\PmTask;
use App\Pm\Services\DepartmentMembershipService;
use App\Pm\Services\PmICalendarService;
use App\Pm\Services\PmSchedulingService;
use App\Pm\Services\ProjectAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PmCalendarController extends Controller
{
    public function __construct(
        protected ProjectAccessService $accessService,
        protected DepartmentMembershipService $deptService,
        protected PmSchedulingService $schedulingService
    ) {}

    /**
     * Retrieve calendar events (tasks, milestones, project deadlines)
     * strictly scoped to projects the user is authorized to view.
     */
    public function events(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$this->accessService->canAccessProjects($user)) {
            abort(403, 'Unauthorized.');
        }

        $startDate = $request->query('start');
        $endDate = $request->query('end');
        $departmentId = $request->query('department_id');
        $projectUuid = $request->query('project_uuid');
        $type = $request->query('type', 'all'); // 'all', 'task', 'milestone', 'project'

        // Determine accessible project IDs
        $deptIds = $this->deptService->getUserDepartmentIds($user);
        $memberProjectIds = PmProjectMember::query()
            ->where('user_id', $user->id)
            ->accepted()
            ->pluck('project_id')
            ->all();

        $projectQuery = PmProject::query()
            ->accessibleTo($user)
            ->with(['department:id,name,slug,color', 'participatingDepartments:id,name,slug,color']);

        if ($departmentId) {
            $projectQuery->where(function ($dq) use ($departmentId) {
                $dq->where('department_id', $departmentId)
                    ->orWhereHas('participatingDepartments', function ($pq) use ($departmentId) {
                        $pq->where('departments.id', $departmentId);
                    });
            });
        }

        if ($projectUuid) {
            $projectQuery->where('uuid', $projectUuid);
        }

        $accessibleProjects = $projectQuery->get();
        $accessibleProjectIds = $accessibleProjects->pluck('id')->all();
        $projectsById = $accessibleProjects->keyBy('id');

        $events = [];

        // 1. Project schedule / duration
        if ($type === 'all' || $type === 'project') {
            foreach ($accessibleProjects as $project) {
                $dueDate = $project->target_completion_date ?? $project->due_date;
                $startDateVal = $project->start_date;

                // When project start_date is not explicitly stored, find the project's inception or earliest task date
                if (!$startDateVal) {
                    $minStart = PmTask::where('project_id', $project->id)->whereNotNull('start_date')->min('start_date');
                    $minDue = PmTask::where('project_id', $project->id)->whereNotNull('due_date')->min('due_date');
                    $earliestTaskDate = ($minStart && $minDue) ? min($minStart, $minDue) : ($minStart ?? $minDue);

                    if ($earliestTaskDate) {
                        $startDateVal = is_string($earliestTaskDate) ? substr($earliestTaskDate, 0, 10) : $earliestTaskDate;
                    } elseif ($project->created_at) {
                        $startDateVal = $project->created_at->toDateString();
                    }
                }

                // If dueDate is still missing, fall back to latest task date or start_date
                if (!$dueDate) {
                    $maxDue = PmTask::where('project_id', $project->id)->whereNotNull('due_date')->max('due_date');
                    $maxStart = PmTask::where('project_id', $project->id)->whereNotNull('start_date')->max('start_date');
                    $latestTaskDate = ($maxDue && $maxStart) ? max($maxDue, $maxStart) : ($maxDue ?? $maxStart);
                    if ($latestTaskDate) {
                        $dueDate = is_string($latestTaskDate) ? substr($latestTaskDate, 0, 10) : $latestTaskDate;
                    }
                }

                if ($startDateVal || $dueDate) {
                    $dueStr = $dueDate ? (is_string($dueDate) ? substr($dueDate, 0, 10) : $dueDate->format('Y-m-d')) : null;
                    $startStr = $startDateVal ? (is_string($startDateVal) ? substr($startDateVal, 0, 10) : $startDateVal->format('Y-m-d')) : $dueStr;
                    $endStr = $dueStr ?? $startStr;

                    // Ensure start <= end when both exist
                    if ($startStr && $endStr && $startStr > $endStr) {
                        $temp = $startStr;
                        $startStr = $endStr;
                        $endStr = $temp;
                    }

                    // If a project has only a deadline and no start date, default the duration period
                    // to begin from when the project was created, or if created on or after the deadline,
                    // provide a default project duration period of at least 14 days ending at due date
                    // so the project's active period is clearly visible across each day of the calendar.
                    if ($startStr === $endStr) {
                        if ($project->created_at && $project->created_at->toDateString() < $endStr) {
                            $startStr = $project->created_at->toDateString();
                        } else {
                            $startStr = date('Y-m-d', strtotime($endStr . ' - 14 days'));
                        }
                    }

                    // Check if project duration overlaps [startDate, endDate]
                    $inRange = true;
                    if ($startDate && $endDate) {
                        $inRange = ($startStr <= $endDate) && ($endStr >= $startDate);
                    } elseif ($startDate) {
                        $inRange = ($endStr >= $startDate);
                    } elseif ($endDate) {
                        $inRange = ($startStr <= $endDate);
                    }

                    if ($inRange) {
                        $events[] = [
                            'id' => "project-{$project->id}",
                            'title' => "🏁 {$project->name}",
                            'start' => $startStr,
                            'end' => $endStr,
                            'start_date' => $startStr,
                            'due_date' => $endStr,
                            'allDay' => true,
                            'is_multi_day' => ($startStr !== $endStr),
                            'type' => 'project',
                            'status' => $project->status instanceof \BackedEnum ? $project->status->value : $project->status,
                            'priority' => $project->priority instanceof \BackedEnum ? $project->priority->value : $project->priority,
                            'visibility' => $project->visibility instanceof \BackedEnum ? $project->visibility->value : $project->visibility,
                            'project' => [
                                'id' => $project->id,
                                'uuid' => $project->uuid,
                                'name' => $project->name,
                                'code' => $project->code,
                                'department' => $project->department ? [
                                    'id' => $project->department->id,
                                    'name' => $project->department->name,
                                    'code' => $project->department->code,
                                ] : null,
                            ],
                            'color' => '#640c0e', // MSA Burgundy
                        ];
                    }
                }
            }
        }

        // 2. Milestones
        if (($type === 'all' || $type === 'milestone') && !empty($accessibleProjectIds)) {
            $milestonesQuery = PmMilestone::query()
                ->whereIn('project_id', $accessibleProjectIds)
                ->whereNotNull('due_date');

            if ($startDate && $endDate) {
                $milestonesQuery->whereBetween('due_date', [$startDate, $endDate]);
            }

            $milestones = $milestonesQuery->get();
            foreach ($milestones as $milestone) {
                $project = $projectsById->get($milestone->project_id);
                $dateStr = is_string($milestone->due_date) ? substr($milestone->due_date, 0, 10) : $milestone->due_date->format('Y-m-d');
                $conditionInfo = $this->schedulingService->evaluateMilestoneCondition($milestone);

                $events[] = [
                    'id' => "milestone-{$milestone->id}",
                    'uuid' => $milestone->uuid,
                    'title' => "🎯 {$milestone->title}",
                    'start' => $dateStr,
                    'end' => $dateStr,
                    'due_date' => $dateStr,
                    'allDay' => true,
                    'type' => 'milestone',
                    'status' => $milestone->status instanceof \BackedEnum ? $milestone->status->value : $milestone->status,
                    'condition' => $conditionInfo['condition'],
                    'project' => $project ? [
                        'id' => $project->id,
                        'uuid' => $project->uuid,
                        'name' => $project->name,
                        'code' => $project->code,
                    ] : null,
                    'color' => '#f59e0b', // Amber
                ];
            }
        }

        // 3. Tasks
        if (($type === 'all' || $type === 'task') && !empty($accessibleProjectIds)) {
            $tasksQuery = PmTask::query()
                ->whereIn('project_id', $accessibleProjectIds)
                ->whereNotNull('due_date')
                ->with([
                    'assignee:id,name,email,avatar',
                    'assignees:id,name,email,avatar',
                    'dependencies' => fn ($dq) => $dq->withPivot(['dependency_type', 'lag_days']),
                ]);

            if ($startDate && $endDate) {
                $tasksQuery->where(function ($q) use ($startDate, $endDate) {
                    $q->where(function ($sub) use ($startDate, $endDate) {
                        $sub->whereNotNull('start_date')
                            ->where('start_date', '<=', $endDate)
                            ->where('due_date', '>=', $startDate);
                    })->orWhere(function ($sub) use ($startDate, $endDate) {
                        $sub->whereNull('start_date')
                            ->whereBetween('due_date', [$startDate, $endDate]);
                    });
                });
            } elseif ($startDate) {
                $tasksQuery->where('due_date', '>=', $startDate);
            } elseif ($endDate) {
                $tasksQuery->where(function ($q) use ($endDate) {
                    $q->where(function ($sub) use ($endDate) {
                        $sub->whereNotNull('start_date')
                            ->where('start_date', '<=', $endDate);
                    })->orWhere(function ($sub) use ($endDate) {
                        $sub->whereNull('start_date')
                            ->where('due_date', '<=', $endDate);
                    });
                });
            }

            if ($request->has('my_tasks_only') && $request->boolean('my_tasks_only')) {
                $tasksQuery->where(function ($q) use ($user) {
                    $q->where('assigned_to', $user->id)
                        ->orWhereHas('assignees', fn ($sq) => $sq->where('users.id', $user->id));
                });
            }

            $tasks = $tasksQuery->get();
            foreach ($tasks as $task) {
                $project = $projectsById->get($task->project_id);
                $dueDateStr = is_string($task->due_date) ? substr($task->due_date, 0, 10) : $task->due_date->format('Y-m-d');
                $startDateStr = $task->start_date
                    ? (is_string($task->start_date) ? substr($task->start_date, 0, 10) : $task->start_date->format('Y-m-d'))
                    : $dueDateStr;

                $isMultiDay = ($startDateStr !== $dueDateStr);

                $color = match ($task->priority instanceof \BackedEnum ? $task->priority->value : $task->priority) {
                    'urgent' => '#ef4444', // Red
                    'high' => '#f97316',   // Orange
                    'medium' => '#3b82f6', // Blue
                    default => '#10b981',  // Green
                };

                $conflictInfo = $this->schedulingService->detectConflicts($task);
                $isConflict = $conflictInfo['has_conflicts'];
                $conflictReason = $isConflict && !empty($conflictInfo['conflicts'])
                    ? ($conflictInfo['conflicts'][0]['conflict_reason'] ?? $conflictInfo['conflicts'][0]['message'] ?? null)
                    : null;

                $events[] = [
                    'id' => "task-{$task->id}",
                    'uuid' => $task->uuid,
                    'title' => $task->title,
                    'start' => $startDateStr,
                    'end' => $dueDateStr,
                    'start_date' => $task->start_date ? (is_string($task->start_date) ? substr($task->start_date, 0, 10) : $task->start_date->format('Y-m-d')) : null,
                    'due_date' => $dueDateStr,
                    'allDay' => true,
                    'is_multi_day' => $isMultiDay,
                    'type' => 'task',
                    'status' => $task->status instanceof \BackedEnum ? $task->status->value : $task->status,
                    'priority' => $task->priority instanceof \BackedEnum ? $task->priority->value : $task->priority,
                    'is_conflict' => $isConflict,
                    'conflict_reason' => $conflictReason,
                    'assigned_to' => $task->assigned_to,
                    'assignee' => $task->assignee ? [
                        'id' => $task->assignee->id,
                        'name' => $task->assignee->name,
                        'email' => $task->assignee->email,
                        'avatar' => $task->assignee->avatar,
                    ] : null,
                    'assignees' => $task->assignees->map(fn ($u) => [
                        'id' => $u->id,
                        'name' => $u->name,
                        'email' => $u->email,
                        'avatar' => $u->avatar,
                    ])->values()->all(),
                    'project' => $project ? [
                        'id' => $project->id,
                        'uuid' => $project->uuid,
                        'name' => $project->name,
                        'code' => $project->code,
                    ] : null,
                    'color' => $color,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    protected function isDateInRange(string $date, ?string $start, ?string $end): bool
    {
        if (!$start || !$end) {
            return true;
        }

        return $date >= $start && $date <= $end;
    }

    /**
     * Export calendar events as an RFC 5545 iCalendar (.ics) stream.
     */
    public function export(Request $request, PmICalendarService $iCalendarService)
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$this->accessService->canAccessProjects($user)) {
            abort(403, 'Unauthorized.');
        }

        $startDate = $request->query('start');
        $endDate = $request->query('end');
        $departmentId = $request->query('department_id');
        $projectUuid = $request->query('project_uuid');
        $type = $request->query('type', 'all'); // 'all', 'task', 'milestone', 'project'

        $projectQuery = PmProject::query()
            ->accessibleTo($user)
            ->with(['department:id,name,slug,color', 'participatingDepartments:id,name,slug,color']);

        if ($departmentId) {
            $projectQuery->where(function ($dq) use ($departmentId) {
                $dq->where('department_id', $departmentId)
                    ->orWhereHas('participatingDepartments', function ($pq) use ($departmentId) {
                        $pq->where('departments.id', $departmentId);
                    });
            });
        }

        if ($projectUuid) {
            $projectQuery->where('uuid', $projectUuid);
        }

        $accessibleProjects = $projectQuery->get();
        $accessibleProjectIds = $accessibleProjects->pluck('id')->all();

        $projects = collect();
        $milestones = collect();
        $tasks = collect();

        // 1. Projects
        if ($type === 'all' || $type === 'project') {
            $projects = $accessibleProjects->filter(function ($project) use ($startDate, $endDate) {
                $dueDate = $project->target_completion_date ?? $project->due_date;
                $startDateVal = $project->start_date ?? $project->created_at?->toDateString();
                if (!$dueDate && !$startDateVal) {
                    return false;
                }
                $dueStr = $dueDate ? (is_string($dueDate) ? substr($dueDate, 0, 10) : $dueDate->format('Y-m-d')) : null;
                $startStr = $startDateVal ? (is_string($startDateVal) ? substr($startDateVal, 0, 10) : $startDateVal->format('Y-m-d')) : $dueStr;
                $endStr = $dueStr ?? $startStr;

                if ($startStr && $endStr && $startStr > $endStr) {
                    $startStr = $endStr;
                }

                if ($startDate && $endDate) {
                    return ($startStr <= $endDate) && ($endStr >= $startDate);
                } elseif ($startDate) {
                    return ($endStr >= $startDate);
                } elseif ($endDate) {
                    return ($startStr <= $endDate);
                }
                return true;
            });
        }

        // 2. Milestones
        if (($type === 'all' || $type === 'milestone') && !empty($accessibleProjectIds)) {
            $milestonesQuery = PmMilestone::query()
                ->whereIn('project_id', $accessibleProjectIds)
                ->whereNotNull('due_date');

            if ($startDate && $endDate) {
                $milestonesQuery->whereBetween('due_date', [$startDate, $endDate]);
            }

            $milestones = $milestonesQuery->get();
        }

        // 3. Tasks
        if (($type === 'all' || $type === 'task') && !empty($accessibleProjectIds)) {
            $tasksQuery = PmTask::query()
                ->whereIn('project_id', $accessibleProjectIds)
                ->whereNotNull('due_date')
                ->with(['assignee:id,name,email,avatar', 'assignees:id,name,email,avatar']);

            if ($startDate && $endDate) {
                $tasksQuery->where(function ($q) use ($startDate, $endDate) {
                    $q->where(function ($sub) use ($startDate, $endDate) {
                        $sub->whereNotNull('start_date')
                            ->where('start_date', '<=', $endDate)
                            ->where('due_date', '>=', $startDate);
                    })->orWhere(function ($sub) use ($startDate, $endDate) {
                        $sub->whereNull('start_date')
                            ->whereBetween('due_date', [$startDate, $endDate]);
                    });
                });
            } elseif ($startDate) {
                $tasksQuery->where('due_date', '>=', $startDate);
            } elseif ($endDate) {
                $tasksQuery->where(function ($q) use ($endDate) {
                    $q->where(function ($sub) use ($endDate) {
                        $sub->whereNotNull('start_date')
                            ->where('start_date', '<=', $endDate);
                    })->orWhere(function ($sub) use ($endDate) {
                        $sub->whereNull('start_date')
                            ->where('due_date', '<=', $endDate);
                    });
                });
            }

            if ($request->has('my_tasks_only') && $request->boolean('my_tasks_only')) {
                $tasksQuery->where(function ($q) use ($user) {
                    $q->where('assigned_to', $user->id)
                        ->orWhereHas('assignees', fn ($sq) => $sq->where('users.id', $user->id));
                });
            }

            $tasks = $tasksQuery->get();
        }

        $calendarName = 'SFU MSA PMS';
        if ($projectUuid && $accessibleProjects->isNotEmpty()) {
            $calendarName = 'PMS: ' . $accessibleProjects->first()->name;
        }

        $icsContent = $iCalendarService->generate($projects, $milestones, $tasks, $calendarName);

        return response($icsContent, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="pms-calendar.ics"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
