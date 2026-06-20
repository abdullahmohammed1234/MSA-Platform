<?php

namespace App\Policies;

use App\Models\Progress;
use App\Models\User;

class ProgressPolicy
{
    public function view(User $user, Progress $progress): bool
    {
        return $user->id === $progress->user_id ||
               $user->hasPermission('view_progress') ||
               $user->hasPermission('manage_progress');
    }

    public function create(User $user): bool
    {
        return true; // Users can record their own progress when completing a lesson
    }

    public function update(User $user, Progress $progress): bool
    {
        return $user->id === $progress->user_id ||
               $user->hasPermission('manage_progress');
    }
}
