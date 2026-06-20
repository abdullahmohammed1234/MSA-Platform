<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;

class QuestionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Question $question): bool
    {
        return $question->quiz->course->status === 'published' ||
               $user->hasPermission('manage_courses') ||
               $user->hasPermission('manage_quizzes');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_quizzes') || $user->hasPermission('manage_courses');
    }

    public function update(User $user, Question $question): bool
    {
        return $user->hasPermission('manage_quizzes') || $user->hasPermission('manage_courses');
    }

    public function delete(User $user, Question $question): bool
    {
        return $user->hasPermission('manage_quizzes') || $user->hasPermission('manage_courses');
    }
}
