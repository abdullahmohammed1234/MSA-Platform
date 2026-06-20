<?php

namespace App\Policies;

use App\Models\User;

class SecurityEventPolicy
{
    /**
     * Determine whether the user can view any security events.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_security');
    }
}
