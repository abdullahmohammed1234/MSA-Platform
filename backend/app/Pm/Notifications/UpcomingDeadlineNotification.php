<?php

namespace App\Pm\Notifications;

use App\Models\User;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
use App\Services\Notifications\CustomDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UpcomingDeadlineNotification extends Notification
{
    use Queueable;

    public function __construct(
        public PmTask $task,
        public PmProject $project,
        public string $window // e.g., '24 hours' or '48 hours'
    ) {}

    public function via($notifiable): array
    {
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
        $dueDateStr = $this->task->due_date ? $this->task->due_date->format('M d, Y') : '';

        return [
            'title' => "Upcoming Deadline ({$this->window}): {$this->task->title}",
            'message' => "Task '{$this->task->title}' in project '{$this->project->name}' is due within {$this->window} ({$dueDateStr}).",
            'type' => 'pm.deadline_warning',
            'project_id' => $this->project->id,
            'project_uuid' => $this->project->uuid,
            'task_id' => $this->task->id,
            'task_uuid' => $this->task->uuid,
            'reminder_window' => $this->window,
            'action_url' => "/pm/projects/{$this->project->uuid}",
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $dueDateStr = $this->task->due_date ? $this->task->due_date->format('M d, Y') : '';

        return (new MailMessage)
            ->subject("Upcoming Deadline ({$this->window}): {$this->task->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Task '{$this->task->title}' in project '{$this->project->name}' is due within {$this->window}.")
            ->line("Due Date: {$dueDateStr}")
            ->line("Priority: " . ucfirst($this->task->priority?->value ?? 'medium'))
            ->action('View Task in PMS', url("/pm/projects/{$this->project->uuid}"))
            ->line('Thank you for contributing to SFU MSA!');
    }
}

