<?php

namespace App\Pm\Notifications;

use App\Models\User;
use App\Pm\Models\PmComment;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
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

    /**
     * Determine channels based on preferences.
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
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Database payload.
     */
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
            'comment_id' => $this->comment->id,
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'action_url' => "/pm/projects/{$this->project->uuid}",
        ];
    }

    /**
     * Email message.
     */
    public function toMail($notifiable): MailMessage
    {
        $context = $this->task ? "task '{$this->task->title}'" : "project '{$this->project->name}'";
        $preview = Str::limit($this->comment->body, 250);

        return (new MailMessage)
            ->subject("Mentioned by {$this->actor->name} in SFU MSA PMS")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->actor->name} mentioned you in a comment on {$context}:")
            ->line("\"{$preview}\"")
            ->action('View in PMS', url("/pm/projects/{$this->project->uuid}"))
            ->line('Thank you for contributing to SFU MSA!');
    }
}

