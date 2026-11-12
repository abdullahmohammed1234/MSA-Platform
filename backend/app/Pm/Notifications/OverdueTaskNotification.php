<?php

namespace App\Pm\Notifications;

use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
use App\Services\Notifications\CustomDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OverdueTaskNotification extends Notification
{
    use Queueable;

    public function __construct(
        public PmTask $task,
        public PmProject $project
    ) {}

    public function via($notifiable): array
    {
        return [CustomDatabaseChannel::class];
    }

    public function toArray($notifiable): array
    {
        $dueDateStr = $this->task->due_date ? $this->task->due_date->format('M d, Y') : '';

        return [
            'title' => "Task Overdue: {$this->task->title}",
            'message' => "Task '{$this->task->title}' in project '{$this->project->name}' was due on {$dueDateStr} and is currently overdue.",
            'type' => 'pm.task_overdue',
            'project_id' => $this->project->id,
            'project_uuid' => $this->project->uuid,
            'task_id' => $this->task->id,
            'task_uuid' => $this->task->uuid,
            'reminder_window' => 'overdue',
            'action_url' => "/pm/projects/{$this->project->uuid}",
        ];
    }
}

