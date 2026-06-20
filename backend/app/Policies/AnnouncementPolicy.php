<?php

namespace App\Policies;

use App\Models\User;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_announcements');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('manage_announcements');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('manage_announcements');
    }

    public function restore(User $user): bool
    {
        return $user->hasPermission('manage_announcements');
    }

    public function forceDelete(User $user): bool
    {
        return $user->hasPermission('manage_announcements');
    }
}
