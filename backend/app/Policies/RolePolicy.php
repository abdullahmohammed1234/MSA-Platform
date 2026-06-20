<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_roles') || $user->hasPermission('manage_permissions');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission('manage_roles') || $user->hasPermission('manage_permissions');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_roles');
    }

    public function update(User $user, Role $role): bool
    {
        // Admins cannot modify the super-admin role
        if ($role->slug === 'super-admin' && !$user->hasRole('super-admin') && !$user->hasRole('admin')) {
            return false;
        }

        return $user->hasPermission('manage_roles');
    }

    public function delete(User $user, Role $role): bool
    {
        // System roles cannot be deleted
        if (in_array($role->slug, ['super-admin', 'admin', 'volunteer', 'student'])) {
            return false;
        }

        // Admins cannot delete super-admin
        if ($role->slug === 'super-admin' && !$user->hasRole('super-admin') && !$user->hasRole('admin')) {
            return false;
        }

        return $user->hasPermission('manage_roles');
    }
}
