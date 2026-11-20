<?php

namespace App\Pm\Notifications;

use App\Models\User;
use App\Pm\Models\PmComment;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
use App\Pm\Support\PmFrontendUrl;
use App\Services\Notifications\CustomDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class TaskCommentMentionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public PmComment $comment,
        public PmProject $project,
        public ?PmTask $task,
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
        $context = $this->task ? "task '{$this->task->title}'" : "project '{$this->project->name}'";
        $preview = Str::limit($this->comment->body, 120);

        return [
            'title' => "Mentioned by {$this->actor->name}",
            'message' => "{$this->actor->name} mentioned you in a comment on {$context}: \"{$preview}\"",
            'type' => 'pm.comment_mention',
            'project_id' => $this->project->id,
            'project_uuid' => $this->project->uuid,
            'task_id' => $this->task?->id,
            'task_uuid' => $this->task?->uuid,
            'department_id' => $this->task?->department_id,
            'department_name' => $this->task?->department?->name,
            'comment_id' => $this->comment->id,
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'action_url' => PmFrontendUrl::projectPath($this->project->uuid),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $context = $this->task ? "task '{$this->task->title}'" : "project '{$this->project->name}'";
        $preview = Str::limit($this->comment->body, 250);

        $mail = (new MailMessage)
            ->subject("[SFU MSA Projects] Mentioned by {$this->actor->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->actor->name} mentioned you in a comment on {$context}:")
            ->line("\"{$preview}\"");

        if ($this->task?->department?->name) {
            $mail->line('Department: ' . $this->task->department->name);
        }

        return $mail
            ->action('View Project in PMS', PmFrontendUrl::project($this->project->uuid))
            ->line('This notification was sent by SFU MSA Projects.');
    }
}
