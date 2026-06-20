<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class NotificationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the notification.
     */
    public function view(User $user, Notification $notification): bool
    {
        return $user->id === $notification->user_id;
    }

    /**
     * Determine whether the user can update the notification (e.g. mark as read).
     */
    public function update(User $user, Notification $notification): bool
    {
        return $user->id === $notification->user_id;
    }

    /**
     * Determine whether the user can delete the notification.
     */
    public function delete(User $user, Notification $notification): bool
    {
        return $user->id === $notification->user_id;
    }

    /**
     * Determine whether the user can manage general notifications.
     */
    public function manage(User $user): bool
    {
        return $user->hasPermission('manage_notifications');
    }

    /**
     * Determine whether the user can send manual notifications.
     */
    public function send(User $user): bool
    {
        return $user->hasPermission('send_notifications');
    }

    /**
     * Determine whether the user can manage templates.
     */
    public function manageTemplates(User $user): bool
    {
        return $user->hasPermission('manage_notification_templates');
    }
}
