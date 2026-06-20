<?php

namespace App\Policies;

use App\Models\Quiz;
use App\Models\User;

class QuizPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Quiz $quiz): bool
    {
        return $quiz->course->status === 'published' ||
               $user->hasPermission('manage_courses') ||
               $user->hasPermission('manage_quizzes');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_quizzes') || $user->hasPermission('manage_courses');
    }

    public function update(User $user, Quiz $quiz): bool
    {
        return $user->hasPermission('manage_quizzes') || $user->hasPermission('manage_courses');
    }

    public function delete(User $user, Quiz $quiz): bool
    {
        return $user->hasPermission('manage_quizzes') || $user->hasPermission('manage_courses');
    }
}
