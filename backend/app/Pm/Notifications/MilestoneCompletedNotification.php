<?php

namespace App\Pm\Notifications;

use App\Models\User;
use App\Pm\Models\PmMilestone;
use App\Pm\Models\PmProject;
use App\Services\Notifications\CustomDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MilestoneCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public PmMilestone $milestone,
        public PmProject $project,
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
     * Database payload.
     */
    public function toArray($notifiable): array
    {
        return [
            'title' => "Milestone Reached: {$this->milestone->title}",
            'message' => "Milestone '{$this->milestone->title}' in project '{$this->project->name}' has been marked as reached by {$this->actor->name}.",
            'type' => 'pm.milestone_completed',
            'project_id' => $this->project->id,
            'project_uuid' => $this->project->uuid,
            'milestone_id' => $this->milestone->id,
            'milestone_uuid' => $this->milestone->uuid,
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'action_url' => "/pm/projects/{$this->project->uuid}",
        ];
    }
}

