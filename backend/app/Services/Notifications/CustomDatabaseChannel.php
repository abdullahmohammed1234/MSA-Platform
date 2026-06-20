<?php

namespace App\Services\Notifications;

use App\Models\Notification as NotificationModel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class CustomDatabaseChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return \App\Models\Notification
     */
    public function send($notifiable, Notification $notification)
    {
        // If notifiable doesn't have an ID, we can't save the notification
        if (!isset($notifiable->id)) {
            return null;
        }

        // Get the notification data
        $data = method_exists($notification, 'toDatabase')
            ? $notification->toDatabase($notifiable)
            : $notification->toArray($notifiable);

        $title = $data['title'] ?? 'Notification';
        $message = $data['message'] ?? '';

        // Clean up title and message from the data block to avoid redundancy
        unset($data['title']);
        unset($data['message']);

        return NotificationModel::create([
            'uuid' => $notification->id ?? (string) Str::uuid(),
            'user_id' => $notifiable->id,
            'type' => get_class($notification),
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'read_at' => null,
        ]);
    }
}
