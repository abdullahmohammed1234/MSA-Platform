<?php

namespace App\Pm\Services;

use App\Models\User;
use App\Pm\Enums\TaskStatus;
use App\Pm\Models\PmComment;
use App\Pm\Models\PmMilestone;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmProjectMember;
use App\Pm\Models\PmTask;
use App\Pm\Notifications\MilestoneCompletedNotification;
use App\Pm\Notifications\TaskAssignedNotification;
use App\Pm\Notifications\TaskCommentMentionNotification;
use App\Pm\Notifications\TaskCompletedNotification;
use App\Pm\Notifications\TaskDeadlineChangedNotification;
use App\Pm\Notifications\TaskReopenedNotification;
use App\Pm\Notifications\TaskStatusChangedNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

class PmNotificationService
{
    public function __construct(
        protected ProjectAccessService $accessService
    ) {}

    /**
     * Dispatch notification for newly assigned task assignees.
     *
     * @param  array<int>  $newAssigneeIds  IDs of assignees who were added/assigned
     */
    public function notifyTaskAssigned(PmTask $task, array $newAssigneeIds, User $actor, bool $isReassignment = false): void
    {
        $project = $this->resolveProject($task);
        if (! $project) {
            return;
        }

        $task->loadMissing('department');

        foreach (array_unique($newAssigneeIds) as $userId) {
            if ((int) $userId === (int) $actor->id) {
                continue;
            }

            /** @var User|null $recipient */
            $recipient = User::find($userId);
            if (! $recipient) {
                continue;
            }

            if (! $this->accessService->canViewProject($recipient, $project)) {
                Log::warning("PmNotificationService: Suppressed notification to user {$userId} - unauthorized for project {$project->id}");
                continue;
            }

            $this->safeDispatch(
                $recipient,
                new TaskAssignedNotification($task, $project, $actor, $isReassignment)
            );
        }
    }

    /**
     * In-app status change notification to assignees and task creator.
     */
    public function notifyTaskStatusChanged(PmTask $task, TaskStatus $oldStatus, TaskStatus $newStatus, User $actor): void
    {
        if ($oldStatus === $newStatus) {
            return;
        }

        $project = $this->resolveProject($task);
        if (! $project) {
            return;
        }

        $recipientIds = $task->assignees()->pluck('users.id')->all();
        if ($task->assigned_to) {
            $recipientIds[] = $task->assigned_to;
        }
        if ($task->created_by) {
            $recipientIds[] = $task->created_by;
        }

        foreach (array_unique($recipientIds) as $userId) {
            if ((int) $userId === (int) $actor->id) {
                continue;
            }

            /** @var User|null $recipient */
            $recipient = User::find($userId);
            if (! $recipient) {
                continue;
            }

            if (! $this->accessService->canViewProject($recipient, $project)) {
                continue;
            }

            $this->safeDispatch(
                $recipient,
                new TaskStatusChangedNotification($task, $project, $oldStatus, $newStatus, $actor)
            );
        }

        // Canonical owner emails for meaningful completion / reopen transitions.
        if ($oldStatus !== TaskStatus::Completed && $newStatus === TaskStatus::Completed) {
            $this->notifyTaskCompleted($task, $project, $actor);
        } elseif ($oldStatus === TaskStatus::Completed && $newStatus !== TaskStatus::Completed) {
            $this->notifyTaskReopened($task, $project, $actor);
        }
    }

    /**
     * Email + in-app notification to the canonical project owner on completion.
     */
    public function notifyTaskCompleted(PmTask $task, ?PmProject $project, User $actor): void
    {
        $project = $project ?? $this->resolveProject($task);
        if (! $project || ! $project->owner_id) {
            return;
        }

        if ((int) $project->owner_id === (int) $actor->id) {
            return;
        }

        $task->loadMissing('department');

        /** @var User|null $owner */
        $owner = User::find($project->owner_id);
        if (! $owner || ! $this->accessService->canViewProject($owner, $project)) {
            return;
        }

        $this->safeDispatch($owner, new TaskCompletedNotification($task, $project, $actor));
    }

    /**
     * Email + in-app notification to the canonical project owner on reopen.
     */
    public function notifyTaskReopened(PmTask $task, ?PmProject $project, User $actor): void
    {
        $project = $project ?? $this->resolveProject($task);
        if (! $project || ! $project->owner_id) {
            return;
        }

        if ((int) $project->owner_id === (int) $actor->id) {
            return;
        }

        $task->loadMissing('department');

        /** @var User|null $owner */
        $owner = User::find($project->owner_id);
        if (! $owner || ! $this->accessService->canViewProject($owner, $project)) {
            return;
        }

        $this->safeDispatch($owner, new TaskReopenedNotification($task, $project, $actor));
    }

    /**
     * Notify current assignees when a task deadline is set, changed, or removed.
     *
     * @param  string|null  $previousDueDate  Y-m-d
     * @param  string|null  $newDueDate  Y-m-d
     */
    public function notifyDeadlineChanged(PmTask $task, ?string $previousDueDate, ?string $newDueDate, User $actor): void
    {
        $previous = $this->normalizeDate($previousDueDate);
        $next = $this->normalizeDate($newDueDate);

        if ($previous === $next) {
            return;
        }

        $project = $this->resolveProject($task);
        if (! $project) {
            return;
        }

        $task->loadMissing('department');

        $recipientIds = $task->assignees()->pluck('users.id')->all();
        if ($task->assigned_to) {
            $recipientIds[] = $task->assigned_to;
        }

        foreach (array_unique($recipientIds) as $userId) {
            if ((int) $userId === (int) $actor->id) {
                continue;
            }

            /** @var User|null $recipient */
            $recipient = User::find($userId);
            if (! $recipient || ! $this->accessService->canViewProject($recipient, $project)) {
                continue;
            }

            $this->safeDispatch(
                $recipient,
                new TaskDeadlineChangedNotification($task, $project, $actor, $previous, $next)
            );
        }
    }

    /**
     * Parse @mentions in comment body and notify authorized recipients.
     */
    public function notifyCommentMentions(PmComment $comment, PmProject $project, ?PmTask $task, User $author): void
    {
        $candidates = $this->resolveMentionedUsers($comment->body, $project);

        if ($task) {
            $task->loadMissing('department');
        }

        foreach ($candidates as $recipient) {
            if ((int) $recipient->id === (int) $author->id) {
                continue;
            }

            if (! $this->accessService->canViewProject($recipient, $project)) {
                Log::warning("PmNotificationService: Mentioned user {$recipient->id} cannot access project {$project->id}. Suppressing notification.");
                continue;
            }

            $this->safeDispatch(
                $recipient,
                new TaskCommentMentionNotification($comment, $project, $task, $author)
            );
        }
    }

    /**
     * Dispatch notification when milestone status transitions to Reached (completed).
     */
    public function notifyMilestoneCompleted(PmMilestone $milestone, PmProject $project, User $actor): void
    {
        $memberUserIds = PmProjectMember::where('project_id', $project->id)
            ->pluck('user_id')
            ->all();

        if ($project->created_by) {
            $memberUserIds[] = $project->created_by;
        }

        foreach (array_unique($memberUserIds) as $userId) {
            if ((int) $userId === (int) $actor->id) {
                continue;
            }

            /** @var User|null $recipient */
            $recipient = User::find($userId);
            if (! $recipient) {
                continue;
            }

            if (! $this->accessService->canViewProject($recipient, $project)) {
                continue;
            }

            $this->safeDispatch(
                $recipient,
                new MilestoneCompletedNotification($milestone, $project, $actor)
            );
        }
    }

    /**
     * Safe dispatch ensuring database in-app notification is persisted even if email transport fails.
     */
    protected function safeDispatch(User $recipient, $notification): void
    {
        $project = $notification->project ?? null;
        if ($project && ! $this->accessService->canViewProject($recipient, $project)) {
            Log::warning("PmNotificationService: Suppressed dispatch to user {$recipient->id} - unauthorized for project {$project->id}");

            return;
        }

        try {
            $customDbChannel = app(\App\Services\Notifications\CustomDatabaseChannel::class);
            $customDbChannel->send($recipient, $notification);
        } catch (Throwable $e) {
            Log::error('PmNotificationService in-app notification error: ' . $e->getMessage(), [
                'recipient_id' => $recipient->id,
                'notification' => get_class($notification),
                'exception' => $e,
            ]);
        }

        $channels = method_exists($notification, 'via') ? $notification->via($recipient) : [];
        if (in_array('mail', $channels, true)) {
            try {
                if ($project && ! $this->accessService->canViewProject($recipient, $project)) {
                    Log::warning("PmNotificationService: Suppressed mail delivery - recipient {$recipient->id} lost access to project {$project->id}");

                    return;
                }

                $mailChannel = app(\Illuminate\Notifications\Channels\MailChannel::class);
                $mailChannel->send($recipient, $notification);
            } catch (Throwable $e) {
                Log::error('PmNotificationService mail delivery exception: ' . $e->getMessage(), [
                    'recipient_id' => $recipient->id,
                    'notification' => get_class($notification),
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function resolveProject(PmTask $task): ?PmProject
    {
        return $task->project ?? PmProject::find($task->project_id);
    }

    protected function normalizeDate(?string $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($date)->toDateString();
        } catch (Throwable) {
            return $date;
        }
    }

    /**
     * Resolve mentioned users from comment body.
     * Supports emails, single-token names, and exact multi-word project member names.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function resolveMentionedUsers(string $body, PmProject $project)
    {
        $matched = collect();

        preg_match_all('/@([a-zA-Z0-9._-]+(?:@[a-zA-Z0-9._-]+)?)/', $body, $matches);
        $tokens = array_unique($matches[1] ?? []);

        if (! empty($tokens)) {
            $byToken = User::where(function ($query) use ($tokens) {
                $query->whereIn('email', $tokens)
                    ->orWhereIn('name', $tokens);
            })->get();
            $matched = $matched->merge($byToken);
        }

        // Exact multi-word display-name mentions among project-accessible members.
        $memberIds = PmProjectMember::where('project_id', $project->id)
            ->where('invitation_status', 'accepted')
            ->pluck('user_id')
            ->all();

        if ($project->owner_id) {
            $memberIds[] = $project->owner_id;
        }

        $memberIds = array_unique($memberIds);
        if (! empty($memberIds)) {
            $members = User::whereIn('id', $memberIds)->get();
            foreach ($members as $member) {
                $name = trim((string) $member->name);
                if ($name === '' || ! str_contains($name, ' ')) {
                    continue;
                }
                if (preg_match('/@' . preg_quote($name, '/') . '(?!\w)/u', $body)) {
                    $matched->push($member);
                }
            }
        }

        return $matched->unique('id')->values();
    }
}
