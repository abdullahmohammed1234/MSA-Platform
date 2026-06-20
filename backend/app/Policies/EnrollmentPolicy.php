<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_volunteers') || $user->hasPermission('manage_courses');
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        return $user->id === $enrollment->user_id ||
               $user->hasPermission('manage_volunteers') ||
               $user->hasPermission('manage_courses');
    }

    public function create(User $user): bool
    {
        return true; // Any authenticated user can enroll themselves in a course
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return $user->hasPermission('manage_volunteers') || $user->hasPermission('manage_courses');
    }

    public function delete(User $user, Enrollment $enrollment): bool
    {
        return $user->id === $enrollment->user_id ||
               $user->hasPermission('manage_volunteers') ||
               $user->hasPermission('manage_courses');
    }
}
