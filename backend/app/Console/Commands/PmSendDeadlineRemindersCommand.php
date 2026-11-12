<?php

namespace App\Console\Commands;

use App\Models\Notification as NotificationModel;
use App\Models\User;
use App\Pm\Enums\TaskStatus;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
use App\Pm\Notifications\OverdueTaskNotification;
use App\Pm\Notifications\UpcomingDeadlineNotification;
use App\Pm\Services\ProjectAccessService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class PmSendDeadlineRemindersCommand extends Command
{
    protected $signature = 'pm:send-deadline-reminders {--dry-run : Simulate execution without dispatching notifications}';

    protected $description = 'Send deadline reminders for PMS tasks (48h, 24h, and overdue) with Option B authorization and deduplication';

    public function handle(ProjectAccessService $accessService): int
    {
        $this->info('Starting PMS deadline reminder scan...');
        $dryRun = $this->option('dry-run');

        $now = Carbon::now();
        $in24h = $now->copy()->addHours(24);
        $in48h = $now->copy()->addHours(48);
        $startOfToday = $now->copy()->startOfDay();

        // Active, incomplete tasks with due dates
        $tasks = PmTask::query()
            ->with(['project', 'assignees'])
            ->whereNotNull('due_date')
            ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])
            ->whereHas('project', function ($q) {
                $q->whereNull('deleted_at');
            })
            ->get();

        $sentCount = 0;
        $skippedDuplicate = 0;
        $skippedUnauthorized = 0;

        foreach ($tasks as $task) {
            $project = $task->project;
            if (!$project) {
                continue;
            }

            $dueDate = Carbon::parse($task->due_date);
            $window = null;
            $isOverdue = false;

            if ($dueDate->lt($startOfToday)) {
                $window = 'overdue';
                $isOverdue = true;
            } elseif ($dueDate->lte($in24h)) {
                $window = '24 hours';
            } elseif ($dueDate->lte($in48h)) {
                $window = '48 hours';
            } else {
                // Beyond 48 hours; not in reminder window
                continue;
            }

            // Identify eligible assignees
            $assigneeIds = $task->assignees->pluck('id')->all();
            if ($task->assigned_to) {
                $assigneeIds[] = $task->assigned_to;
            }
            $uniqueAssigneeIds = array_unique($assigneeIds);

            foreach ($uniqueAssigneeIds as $userId) {
                $user = User::find($userId);
                if (!$user) {
                    continue;
                }

                // Strict Option B Check: Can the user view this project?
                if (!$accessService->canViewProject($user, $project)) {
                    $skippedUnauthorized++;
                    continue;
                }

                // Check deduplication in last 24h for this window
                $alreadySent = NotificationModel::where('user_id', $user->id)
                    ->where(function ($q) use ($task) {
                        $q->where('data->task_id', $task->id)
                          ->orWhere('data->task_id', (string) $task->id);
                    })
                    ->where('data->reminder_window', $window)
                    ->where('created_at', '>=', $now->copy()->subHours(24))
                    ->exists();

                if ($alreadySent) {
                    $skippedDuplicate++;
                    continue;
                }

                if (!$dryRun) {
                    try {
                        if ($isOverdue) {
                            $user->notify(new OverdueTaskNotification($task, $project));
                        } else {
                            $user->notify(new UpcomingDeadlineNotification($task, $project, $window));
                        }
                        $sentCount++;
                    } catch (Throwable $e) {
                        Log::error("Failed sending deadline reminder to user {$user->id} for task {$task->id}: " . $e->getMessage());
                    }
                } else {
                    $sentCount++;
                }
            }
        }

        $this->info("Completed. Sent: {$sentCount}, Skipped (duplicate): {$skippedDuplicate}, Skipped (unauthorized): {$skippedUnauthorized}.");
        return Command::SUCCESS;
    }
}

