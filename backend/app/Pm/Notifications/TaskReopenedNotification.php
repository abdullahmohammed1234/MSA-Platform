<?php

namespace App\Pm\Notifications;

use App\Models\User;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
use App\Pm\Support\PmFrontendUrl;
use App\Services\Notifications\CustomDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskReopenedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public PmTask $task,
        public PmProject $project,
        public User $actor
    ) {}

    public function via($notifiable): array
    {
        if ($notifiable instanceof User) {
            $accessService = app(\App\Pm\Services\ProjectAccessService::class);
            if (! $accessService->canViewProject($notifiable, $this->project)) {
                return [];
            }
        }

        $channels = [CustomDatabaseChannel::class];

        if ($notifiable instanceof User && $notifiable->notificationPreferences) {
            if ($notifiable->notificationPreferences->email_enabled) {
                $channels[] = 'mail';
            }
        } else {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => "Task Reopened: {$this->task->title}",
            'message' => "{$this->actor->name} reopened task '{$this->task->title}' in project '{$this->project->name}'.",
            'type' => 'pm.task_reopened',
            'project_id' => $this->project->id,
            'project_uuid' => $this->project->uuid,
            'task_id' => $this->task->id,
            'task_uuid' => $this->task->uuid,
            'department_id' => $this->task->department_id,
            'department_name' => $this->task->department?->name,
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'action_url' => PmFrontendUrl::projectPath($this->project->uuid),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("[SFU MSA Projects] Task Reopened: {$this->task->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->actor->name} reopened task '{$this->task->title}' in project '{$this->project->name}'.");

        if ($this->task->department?->name) {
            $mail->line('Department: ' . $this->task->department->name);
        }

        return $mail
            ->action('View Project in PMS', PmFrontendUrl::project($this->project->uuid))
            ->line('This notification was sent by SFU MSA Projects.');
    }
}
