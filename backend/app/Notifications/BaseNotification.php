<?php

namespace App\Notifications;

use App\Services\Notifications\CustomDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Models\User;

abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Get the category identifier for user preference toggles.
     * e.g., 'course_completion', 'new_announcements', 'upcoming_training', 'certificate_earned'
     */
    abstract public function getCategory(): string;

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable): array
    {
        // Default to database and email channels if notifiable is not a User
        if (!$notifiable instanceof User) {
            return [CustomDatabaseChannel::class, 'mail'];
        }

        // Load or find preferences
        $preferences = $notifiable->notificationPreferences;

        // If no preference record exists, default to both channels
        if (!$preferences) {
            return [CustomDatabaseChannel::class, 'mail'];
        }

        $category = $this->getCategory();

        // If user has explicitly opted out of this category, deliver to no channels
        if (isset($preferences->$category) && !$preferences->$category) {
            return [];
        }

        $channels = [];

        // Check channel preferences
        if ($preferences->in_app_enabled) {
            $channels[] = CustomDatabaseChannel::class;
        }

        if ($preferences->email_enabled) {
            $channels[] = 'mail';
        }

        return $channels;
    }
}
