<?php

namespace App\Notifications;

use App\Models\AchievementAward;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AchievementUnlockedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $award;

    public function __construct(AchievementAward $award)
    {
        $this->award = $award;
    }

    public function via($notifiable): array
    {
        // Achievements are mostly in-app notifications, but we can support mail as well
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $achievement = $this->award->achievement;
        return [
            'title' => 'Achievement Unlocked!',
            'message' => "Congratulations! You have unlocked the achievement: {$achievement->title}.",
            'type' => 'achievement',
            'achievement_uuid' => $achievement->uuid,
            'title_text' => $achievement->title,
            'points' => $achievement->points,
        ];
    }
}
