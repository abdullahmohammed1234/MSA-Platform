<?php

namespace App\Notifications;

use App\Services\Notifications\CustomDatabaseChannel;
use App\Services\Notifications\WebPushChannel;

class TestPushNotification extends ImmediateNotification
{
    public function getCategory(): string
    {
        return 'push_enabled';
    }

    /**
     * Test notification routes to Web Push channel and database log without sending emails.
     */
    public function via($notifiable): array
    {
        $preferences = $notifiable->notificationPreferences;
        if ($preferences && isset($preferences->push_enabled) && ! (bool) $preferences->push_enabled) {
            return [];
        }

        $channels = [WebPushChannel::class];
        if (! $preferences || $preferences->in_app_enabled) {
            $channels[] = CustomDatabaseChannel::class;
        }

        return $channels;
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'SFU MSA Connect Test',
            'message' => 'Web Push notification delivered successfully to your device.',
            'action_url' => '/app/notifications',
            'url' => '/app/notifications',
            'type' => 'test',
        ];
    }
}
