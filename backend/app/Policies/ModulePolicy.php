<?php

namespace App\Policies;

use App\Models\Module;
use App\Models\User;

class ModulePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Module $module): bool
    {
        return $module->course->status === 'published' || $user->hasPermission('manage_courses') || $user->hasPermission('manage_modules');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_modules') || $user->hasPermission('manage_courses');
    }

    public function update(User $user, Module $module): bool
    {
        return $user->hasPermission('manage_modules') || $user->hasPermission('manage_courses');
    }

    public function delete(User $user, Module $module): bool
    {
        return $user->hasPermission('manage_modules') || $user->hasPermission('manage_courses');
    }
}
