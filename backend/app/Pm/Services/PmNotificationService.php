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
     * @param PmTask $task
     * @param array<int> $newAssigneeIds IDs of assignees who were added/assigned
     * @param User $actor The user performing the assignment
     * @param bool $isReassignment Whether this was a reassignment operation
     */
    public function notifyTaskAssigned(PmTask $task, array $newAssigneeIds, User $actor, bool $isReassignment = false): void
    {
        $project = $task->project ?? PmProject::find($task->project_id);
        if (!$project) {
            return;
        }

        $uniqueIds = array_unique($newAssigneeIds);

        foreach ($uniqueIds as $userId) {
            // Avoid self-notification
            if ((int) $userId === (int) $actor->id) {
                continue;
            }

            /** @var User|null $recipient */
            $recipient = User::find($userId);
            if (!$recipient) {
                continue;
            }

            // Revalidate recipient authorization under Option B
            if (!$this->accessService->canViewProject($recipient, $project)) {
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
     * Dispatch notification for task status change to all other assignees and the creator.
     */
    public function notifyTaskStatusChanged(PmTask $task, TaskStatus $oldStatus, TaskStatus $newStatus, User $actor): void
    {
        if ($oldStatus === $newStatus) {
            return;
        }

        $project = $task->project ?? PmProject::find($task->project_id);
        if (!$project) {
            return;
        }

        // Determine candidate recipients: assignees and task creator
        $recipientIds = $task->assignees()->pluck('users.id')->all();
        if ($task->assigned_to) {
            $recipientIds[] = $task->assigned_to;
        }
        if ($task->created_by) {
            $recipientIds[] = $task->created_by;
        }

        $uniqueRecipientIds = array_unique($recipientIds);

        foreach ($uniqueRecipientIds as $userId) {
            if ((int) $userId === (int) $actor->id) {
                continue;
            }

            /** @var User|null $recipient */
            $recipient = User::find($userId);
            if (!$recipient) {
                continue;
            }

            // Revalidate recipient access
            if (!$this->accessService->canViewProject($recipient, $project)) {
                continue;
            }

            $this->safeDispatch(
                $recipient,
                new TaskStatusChangedNotification($task, $project, $oldStatus, $newStatus, $actor)
            );
        }
    }

    /**
     * Parse @mentions in comment body and notify authorized recipients.
     */
    public function notifyCommentMentions(PmComment $comment, PmProject $project, ?PmTask $task, User $author): void
    {
        // Extract potential usernames or emails: @username or @email
        preg_match_all('/@([a-zA-Z0-9._-]+(?:@[a-zA-Z0-9._-]+)?)/', $comment->body, $matches);
        if (empty($matches[1])) {
            return;
        }

        $tokens = array_unique($matches[1]);

        $candidates = User::where(function ($query) use ($tokens) {
            $query->whereIn('email', $tokens)
                ->orWhereIn('name', $tokens);
        })->get();

        foreach ($candidates as $recipient) {
            if ((int) $recipient->id === (int) $author->id) {
                continue;
            }

            // Strict Option B check: Can the recipient view this project?
            if (!$this->accessService->canViewProject($recipient, $project)) {
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
        // Eligible recipients: all active project members
        $memberUserIds = PmProjectMember::where('project_id', $project->id)
            ->pluck('user_id')
            ->all();

        // Also include project creator and active department members if department-private
        if ($project->created_by) {
            $memberUserIds[] = $project->created_by;
        }

        $uniqueIds = array_unique($memberUserIds);

        foreach ($uniqueIds as $userId) {
            if ((int) $userId === (int) $actor->id) {
                continue;
            }

            /** @var User|null $recipient */
            $recipient = User::find($userId);
            if (!$recipient) {
                continue;
            }

            if (!$this->accessService->canViewProject($recipient, $project)) {
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
     * Decouples in-app notification persistence from mail delivery and enforces Option B revalidation.
     */
    protected function safeDispatch(User $recipient, $notification): void
    {
        $project = $notification->project ?? null;
        if ($project && !$this->accessService->canViewProject($recipient, $project)) {
            Log::warning("PmNotificationService: Suppressed dispatch to user {$recipient->id} - unauthorized for project {$project->id}");
            return;
        }

        // 1. Deliver in-app notification via CustomDatabaseChannel first
        // Guarantees immediate UI update and ensures notification record exists regardless of email transport
        try {
            $customDbChannel = app(\App\Services\Notifications\CustomDatabaseChannel::class);
            $customDbChannel->send($recipient, $notification);
        } catch (Throwable $e) {
            Log::error("PmNotificationService in-app notification error: " . $e->getMessage(), [
                'recipient_id' => $recipient->id,
                'notification' => get_class($notification),
                'exception' => $e,
            ]);
        }

        // 2. Deliver email if supported by notification and allowed by recipient preferences
        $channels = method_exists($notification, 'via') ? $notification->via($recipient) : [];
        if (in_array('mail', $channels, true)) {
            try {
                // Revalidate Option B access right before mail transmission
                if ($project && !$this->accessService->canViewProject($recipient, $project)) {
                    Log::warning("PmNotificationService: Suppressed mail delivery - recipient {$recipient->id} lost access to project {$project->id}");
                    return;
                }

                $mailChannel = app(\Illuminate\Notifications\Channels\MailChannel::class);
                $mailChannel->send($recipient, $notification);
            } catch (Throwable $e) {
                // Catch any SMTP network errors or timeouts so HTTP requests never fail or leak exceptions
                Log::error("PmNotificationService mail delivery exception: " . $e->getMessage(), [
                    'recipient_id' => $recipient->id,
                    'notification' => get_class($notification),
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
}

