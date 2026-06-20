<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_users');
    }

    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->hasPermission('manage_users');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_users');
    }

    public function update(User $user, User $model): bool
    {
        // Admins cannot manage or modify Super Admin users
        if ($model->hasRole('super-admin') && !$user->hasRole('super-admin') && !$user->hasRole('admin')) {
            return false;
        }

        return $user->id === $model->id || $user->hasPermission('manage_users');
    }

    public function delete(User $user, User $model): bool
    {
        // Users cannot delete themselves
        if ($user->id === $model->id) {
            return false;
        }

        // Admins cannot manage or modify Super Admin users
        if ($model->hasRole('super-admin') && !$user->hasRole('super-admin') && !$user->hasRole('admin')) {
            return false;
        }

        return $user->hasPermission('manage_users');
    }

    public function restore(User $user, User $model): bool
    {
        if ($model->hasRole('super-admin') && !$user->hasRole('super-admin') && !$user->hasRole('admin')) {
            return false;
        }

        return $user->hasPermission('manage_users');
    }

    public function forceDelete(User $user, User $model): bool
    {
        if ($model->hasRole('super-admin') && !$user->hasRole('super-admin') && !$user->hasRole('admin')) {
            return false;
        }

        return $user->hasPermission('manage_users');
    }
}
