<?php

namespace App\Policies;

use App\Models\User;

class FeaturedOpportunityPolicy
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
        return $this->canManage($user);
    }

    public function update(User $user): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user): bool
    {
        return $this->canManage($user);
    }

    public function restore(User $user): bool
    {
        return $this->canManage($user);
    }

    public function forceDelete(User $user): bool
    {
        return $this->canManage($user);
    }

    protected function canManage(User $user): bool
    {
        return $user->hasPermission('manage_featured_opportunities') ||
               $user->hasPermission('manage_announcements') ||
               $user->hasPermission('manage_homepage') ||
               $user->hasRole('admin') ||
               $user->hasRole('super-admin');
    }
}
