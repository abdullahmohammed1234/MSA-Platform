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

class TaskDeadlineChangedNotification extends Notification
{
    use Queueable;

    /**
     * @param  string|null  $previousDueDate  Y-m-d or null
     * @param  string|null  $newDueDate  Y-m-d or null
     */
    public function __construct(
        public PmTask $task,
        public PmProject $project,
        public User $actor,
        public ?string $previousDueDate,
        public ?string $newDueDate
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

    public function changeKind(): string
    {
        if ($this->previousDueDate === null && $this->newDueDate !== null) {
            return 'set';
        }
        if ($this->previousDueDate !== null && $this->newDueDate === null) {
            return 'removed';
        }

        return 'changed';
    }

    public function toArray($notifiable): array
    {
        $kind = $this->changeKind();
        $title = match ($kind) {
            'set' => "Deadline Set: {$this->task->title}",
            'removed' => "Deadline Removed: {$this->task->title}",
            default => "Deadline Changed: {$this->task->title}",
        };
        $message = match ($kind) {
            'set' => "{$this->actor->name} set the deadline for '{$this->task->title}' to {$this->formatDate($this->newDueDate)}.",
            'removed' => "{$this->actor->name} removed the deadline for '{$this->task->title}' (was {$this->formatDate($this->previousDueDate)}).",
            default => "{$this->actor->name} changed the deadline for '{$this->task->title}' from {$this->formatDate($this->previousDueDate)} to {$this->formatDate($this->newDueDate)}.",
        };

        return [
            'title' => $title,
            'message' => $message,
            'type' => 'pm.task_deadline_changed',
            'project_id' => $this->project->id,
            'project_uuid' => $this->project->uuid,
            'task_id' => $this->task->id,
            'task_uuid' => $this->task->uuid,
            'department_id' => $this->task->department_id,
            'department_name' => $this->task->department?->name,
            'previous_due_date' => $this->previousDueDate,
            'new_due_date' => $this->newDueDate,
            'change_kind' => $kind,
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'action_url' => PmFrontendUrl::projectPath($this->project->uuid),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $kind = $this->changeKind();
        $subject = match ($kind) {
            'set' => "[SFU MSA Projects] Deadline Set: {$this->task->title}",
            'removed' => "[SFU MSA Projects] Deadline Removed: {$this->task->title}",
            default => "[SFU MSA Projects] Deadline Changed: {$this->task->title}",
        };

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name},")
            ->line("Project: {$this->project->name}")
            ->line("Task: {$this->task->title}");

        if ($this->task->department?->name) {
            $mail->line('Department: ' . $this->task->department->name);
        }

        $mail->line(match ($kind) {
            'set' => "{$this->actor->name} set the deadline to {$this->formatDate($this->newDueDate)}.",
            'removed' => "{$this->actor->name} removed the deadline (previously {$this->formatDate($this->previousDueDate)}).",
            default => "{$this->actor->name} changed the deadline from {$this->formatDate($this->previousDueDate)} to {$this->formatDate($this->newDueDate)}.",
        });

        return $mail
            ->action('View Project in PMS', PmFrontendUrl::project($this->project->uuid))
            ->line('This notification was sent by SFU MSA Projects.');
    }

    protected function formatDate(?string $date): string
    {
        if ($date === null || $date === '') {
            return 'none';
        }

        try {
            return \Carbon\Carbon::parse($date)->format('M d, Y');
        } catch (\Throwable) {
            return $date;
        }
    }
}
