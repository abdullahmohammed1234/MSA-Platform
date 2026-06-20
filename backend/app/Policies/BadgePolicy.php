<?php

namespace App\Policies;

use App\Models\Badge;
use App\Models\User;

class BadgePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Badge $badge): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_badges');
    }

    public function update(User $user, Badge $badge): bool
    {
        return $user->hasPermission('manage_badges');
    }

    public function delete(User $user, Badge $badge): bool
    {
        return $user->hasPermission('manage_badges');
    }
}
