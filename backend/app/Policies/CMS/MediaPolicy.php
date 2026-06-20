<?php

namespace App\Policies\CMS;

use App\Models\User;

class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_media');
    }

    public function view(User $user): bool
    {
        return $user->hasPermission('manage_media');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_media');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('manage_media');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('manage_media');
    }
}
