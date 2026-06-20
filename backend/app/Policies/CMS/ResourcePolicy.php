<?php

namespace App\Policies\CMS;

use App\Models\User;

class ResourcePolicy
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
        return $user->hasPermission('manage_resources');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('manage_resources');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('manage_resources');
    }
}
