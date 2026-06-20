<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Course $course): bool
    {
        return $course->status === 'published' || $user->hasPermission('manage_courses');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_courses');
    }

    public function update(User $user, Course $course): bool
    {
        return $user->hasPermission('manage_courses');
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->hasPermission('manage_courses');
    }

    public function restore(User $user, Course $course): bool
    {
        return $user->hasPermission('manage_courses');
    }

    public function forceDelete(User $user, Course $course): bool
    {
        return $user->hasPermission('manage_courses');
    }
}
