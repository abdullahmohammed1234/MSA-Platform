<?php

namespace App\Console\Commands;

use App\Models\Notification as NotificationModel;
use App\Models\User;
use App\Pm\Enums\MilestoneStatus;
use App\Pm\Enums\TaskStatus;
use App\Pm\Models\PmMilestone;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
use App\Pm\Notifications\DailyDigestNotification;
use App\Services\ApplicationAccessService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class PmSendDailyDigestCommand extends Command
{
    protected $signature = 'pm:send-daily-digest {--user= : Target a specific user ID for testing} {--dry-run : Simulate without dispatching}';

    protected $description = 'Send personalized daily task summaries to active PMS members with Option B scoping';

    public function handle(ApplicationAccessService $appAccessService): int
    {
        $this->info('Starting PMS daily digest dispatch...');
        $dryRun = $this->option('dry-run');
        $targetUserId = $this->option('user');

        $now = Carbon::now();
        $today = $now->toDateString();
        $startOfToday = $now->copy()->startOfDay();
        $nextWeek = $now->copy()->addDays(7)->toDateString();

        $usersQuery = User::query();
        if ($targetUserId) {
            $usersQuery->where('id', $targetUserId);
        }

        $users = $usersQuery->get();
        $sentCount = 0;
        $skippedCount = 0;

        foreach ($users as $user) {
            if (!$appAccessService->canAccess($user, 'projects')) {
                continue;
            }

            // Deduplication: Only one daily digest per user per calendar day
            $alreadySentToday = NotificationModel::where('user_id', $user->id)
                ->where(function ($q) {
                    $q->where('type', DailyDigestNotification::class)
                      ->orWhere('data->type', 'pm.daily_digest');
                })
                ->whereDate('created_at', $today)
                ->exists();

            if ($alreadySentToday) {
                $skippedCount++;
                continue;
            }

            // Strictly scope to projects accessible to this user under Option B
            $accessibleProjectIds = PmProject::accessibleTo($user)->pluck('id')->all();
            if (empty($accessibleProjectIds)) {
                continue;
            }

            // Retrieve tasks assigned to this user in accessible projects
            $userTasks = PmTask::query()
                ->with('project')
                ->whereIn('project_id', $accessibleProjectIds)
                ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])
                ->where(function ($q) use ($user) {
                    $q->where('assigned_to', $user->id)
                      ->orWhereHas('assignees', fn ($sq) => $sq->where('users.id', $user->id));
                })
                ->get();

            $dueToday = [];
            $upcoming = [];
            $overdue = [];

            foreach ($userTasks as $task) {
                if (!$task->due_date) {
                    continue;
                }

                $dueDate = Carbon::parse($task->due_date);
                $taskSummary = [
                    'id' => $task->id,
                    'uuid' => $task->uuid,
                    'title' => $task->title,
                    'project_name' => $task->project->name,
                    'due_date' => $dueDate->format('M d, Y'),
                    'priority' => $task->priority?->value ?? 'medium',
                ];

                if ($dueDate->toDateString() === $today) {
                    $dueToday[] = $taskSummary;
                } elseif ($dueDate->lt($startOfToday)) {
                    $overdue[] = $taskSummary;
                } elseif ($dueDate->toDateString() <= $nextWeek) {
                    $upcoming[] = $taskSummary;
                }
            }

            // Retrieve relevant milestones due in next 7 days in accessible projects
            $milestones = PmMilestone::query()
                ->with('project')
                ->whereIn('project_id', $accessibleProjectIds)
                ->where('status', MilestoneStatus::Pending->value)
                ->whereBetween('due_date', [$today, $nextWeek])
                ->get()
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'uuid' => $m->uuid,
                    'title' => $m->title,
                    'project_name' => $m->project->name,
                    'due_date' => Carbon::parse($m->due_date)->format('M d, Y'),
                ])
                ->all();

            // Only send digest if there are tasks or milestones to report
            if (empty($dueToday) && empty($upcoming) && empty($overdue) && empty($milestones)) {
                continue;
            }

            $summaryData = [
                'date' => $today,
                'due_today' => $dueToday,
                'upcoming' => $upcoming,
                'overdue' => $overdue,
                'milestones' => $milestones,
            ];

            if (!$dryRun) {
                try {
                    $user->notify(new DailyDigestNotification($summaryData));
                    $sentCount++;
                } catch (Throwable $e) {
                    Log::error("Failed dispatching daily digest to user {$user->id}: " . $e->getMessage());
                }
            } else {
                $sentCount++;
            }
        }

        $this->info("Daily digest completed. Dispatched: {$sentCount}, Skipped: {$skippedCount}.");
        return Command::SUCCESS;
    }
}

