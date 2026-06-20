<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;

class LessonPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lesson $lesson): bool
    {
        return $lesson->module->course->status === 'published' ||
               $user->hasPermission('manage_courses') ||
               $user->hasPermission('manage_lessons');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_lessons') || $user->hasPermission('manage_courses');
    }

    public function update(User $user, Lesson $lesson): bool
    {
        return $user->hasPermission('manage_lessons') || $user->hasPermission('manage_courses');
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        return $user->hasPermission('manage_lessons') || $user->hasPermission('manage_courses');
    }
}
