<?php

namespace App\Pm\Notifications;

use App\Models\User;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
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
        // Revalidate recipient authorization under Option B
        if ($notifiable instanceof User) {
            $accessService = app(\App\Pm\Services\ProjectAccessService::class);
            if (!$accessService->canViewProject($notifiable, $this->project)) {
                return [];
            }
        }

        $channels = [CustomDatabaseChannel::class];

        if ($notifiable instanceof User && $notifiable->notificationPreferences) {
            if ($notifiable->notificationPreferences->email_enabled) {
                $channels[] = 'mail';
            }
        } else {
            // Default to mail if preferences are not set
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
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'is_reassignment' => $this->isReassignment,
            'action_url' => "/pm/projects/{$this->project->uuid}",
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

        return (new MailMessage)
            ->subject("Task {$titleVerb}: {$this->task->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("You have been {$actionVerb} to task '{$this->task->title}' in project '{$this->project->name}' by {$this->actor->name}.")
            ->line("Due Date: {$dueDateStr}")
            ->line("Priority: " . ucfirst($this->task->priority?->value ?? 'medium'))
            ->action('View Task in PMS', url("/pm/projects/{$this->project->uuid}"))
            ->line('Thank you for contributing to SFU MSA!');
    }
}

