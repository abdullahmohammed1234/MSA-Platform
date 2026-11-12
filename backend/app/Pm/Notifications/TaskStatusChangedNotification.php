<?php

namespace App\Pm\Notifications;

use App\Models\User;
use App\Pm\Enums\TaskStatus;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
use App\Services\Notifications\CustomDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public PmTask $task,
        public PmProject $project,
        public TaskStatus $oldStatus,
        public TaskStatus $newStatus,
        public User $actor
    ) {}

    /**
     * In-app only delivery.
     */
    public function via($notifiable): array
    {
        // Revalidate recipient authorization under Option B
        if ($notifiable instanceof User) {
            $accessService = app(\App\Pm\Services\ProjectAccessService::class);
            if (!$accessService->canViewProject($notifiable, $this->project)) {
                return [];
            }
        }

        return [CustomDatabaseChannel::class];
    }

    /**
     * Database notification payload.
     */
    public function toArray($notifiable): array
    {
        return [
            'title' => "Task Status Updated: {$this->task->title}",
            'message' => "Task '{$this->task->title}' was changed from {$this->oldStatus->label()} to {$this->newStatus->label()} by {$this->actor->name}.",
            'type' => 'pm.task_status_changed',
            'project_id' => $this->project->id,
            'project_uuid' => $this->project->uuid,
            'task_id' => $this->task->id,
            'task_uuid' => $this->task->uuid,
            'old_status' => $this->oldStatus->value,
            'new_status' => $this->newStatus->value,
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'action_url' => "/pm/projects/{$this->project->uuid}",
        ];
    }
}

