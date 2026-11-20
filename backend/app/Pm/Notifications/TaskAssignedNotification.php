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

class TaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public PmTask $task,
        public PmProject $project,
        public User $actor,
        public bool $isReassignment = false
    ) {}

    /**
     * Determine notification channels based on user preferences.
     */
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

    /**
     * Database notification payload.
     */
    public function toArray($notifiable): array
    {
        $actionVerb = $this->isReassignment ? 'reassigned' : 'assigned';
        $titleVerb = $this->isReassignment ? 'Reassigned' : 'Assigned';

        return [
            'title' => "Task {$titleVerb}: {$this->task->title}",
            'message' => "You have been {$actionVerb} to task '{$this->task->title}' in project '{$this->project->name}' by {$this->actor->name}.",
            'type' => 'pm.task_assigned',
            'project_id' => $this->project->id,
            'project_uuid' => $this->project->uuid,
            'task_id' => $this->task->id,
            'task_uuid' => $this->task->uuid,
            'department_id' => $this->task->department_id,
            'department_name' => $this->task->department?->name,
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'is_reassignment' => $this->isReassignment,
            'action_url' => PmFrontendUrl::projectPath($this->project->uuid),
        ];
    }

    /**
     * Email notification message.
     */
    public function toMail($notifiable): MailMessage
    {
        $actionVerb = $this->isReassignment ? 'reassigned' : 'assigned';
        $titleVerb = $this->isReassignment ? 'Reassigned' : 'Assigned';

        $dueDateStr = $this->task->due_date ? $this->task->due_date->format('M d, Y') : 'No deadline specified';

        $mail = (new MailMessage)
            ->subject("[SFU MSA Projects] Task {$titleVerb}: {$this->task->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("You have been {$actionVerb} to task '{$this->task->title}' in project '{$this->project->name}' by {$this->actor->name}.");

        if ($this->task->department?->name) {
            $mail->line('Department: ' . $this->task->department->name);
        }

        return $mail
            ->line("Due Date: {$dueDateStr}")
            ->line('Priority: ' . ucfirst($this->task->priority?->value ?? 'medium'))
            ->action('View Project in PMS', PmFrontendUrl::project($this->project->uuid))
            ->line('This notification was sent by SFU MSA Projects.');
    }
}
