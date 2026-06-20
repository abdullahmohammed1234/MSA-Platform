<?php

namespace App\Policies;

use App\Models\LearningPath;
use App\Models\User;

class LearningPathPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LearningPath $path): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_learning_paths');
    }

    public function update(User $user, LearningPath $path): bool
    {
        return $user->hasPermission('manage_learning_paths');
    }

    public function delete(User $user, LearningPath $path): bool
    {
        return $user->hasPermission('manage_learning_paths');
    }
}
