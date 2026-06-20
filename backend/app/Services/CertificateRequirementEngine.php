<?php

namespace App\Services;

use App\Models\User;
use App\Models\CertificateRequirement;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\Progress;
use App\Models\QuizAttempt;
use App\Models\Enrollment;
use Exception;

class CertificateRequirementEngine
{
    /**
     * Evaluate if a specific requirement is satisfied for a user.
     */
    public function evaluateRequirement(User $user, CertificateRequirement $requirement): bool
    {
        $certificate = $requirement->certificate;
        if (!$certificate) {
            return false;
        }

        switch ($requirement->type) {
            case 'lesson_completion':
                return $this->evaluateLessonCompletion($user, $certificate);

            case 'quiz_completion':
                return $this->evaluateQuizCompletion($user, $certificate);

            case 'passing_score':
                return $this->evaluatePassingScore($user, $certificate);

            case 'course_completion':
                return $this->evaluateCourseCompletion($user, $certificate);

            case 'average_score':
                $minAvg = $requirement->parameters['min_average_score'] ?? 70;
                return $this->evaluateAverageScore($user, $certificate, $minAvg);

            case 'custom':
                return true; // Expandable for custom webhook or manual evaluation triggers

            default:
                throw new Exception("Unknown requirement type: {$requirement->type}");
        }
    }

    /**
     * Verify 100% of required lessons in the course are completed.
     */
    protected function evaluateLessonCompletion(User $user, $certificate): bool
    {
        if ($certificate->type !== 'course' || !$certificate->course_id) {
            return false;
        }

        $course = $certificate->course;
        if (!$course) {
            return false;
        }

        $requiredLessonIds = $course->lessons()
            ->where('is_required', true)
            ->pluck('lessons.id')
            ->toArray();

        if (empty($requiredLessonIds)) {
            return true;
        }

        $completedCount = Progress::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->whereIn('lesson_id', $requiredLessonIds)
            ->where('completed', true)
            ->count();

        return $completedCount === count($requiredLessonIds);
    }

    /**
     * Verify all quizzes in the course have at least one attempt.
     */
    protected function evaluateQuizCompletion(User $user, $certificate): bool
    {
        if ($certificate->type !== 'course' || !$certificate->course_id) {
            return false;
        }

        $course = $certificate->course;
        if (!$course) {
            return false;
        }

        $quizIds = $course->quizzes()
            ->where('status', 'published')
            ->pluck('quizzes.id')
            ->toArray();

        if (empty($quizIds)) {
            return true;
        }

        $attemptedQuizIdsCount = QuizAttempt::where('user_id', $user->id)
            ->whereIn('quiz_id', $quizIds)
            ->select('quiz_id')
            ->distinct()
            ->count();

        return $attemptedQuizIdsCount === count($quizIds);
    }

    /**
     * Verify all quizzes in the course are passed (achieved passing score).
     */
    protected function evaluatePassingScore(User $user, $certificate): bool
    {
        if ($certificate->type !== 'course' || !$certificate->course_id) {
            return false;
        }

        $course = $certificate->course;
        if (!$course) {
            return false;
        }

        $quizIds = $course->quizzes()
            ->where('status', 'published')
            ->pluck('quizzes.id')
            ->toArray();

        if (empty($quizIds)) {
            return true;
        }

        $passedCount = QuizAttempt::where('user_id', $user->id)
            ->whereIn('quiz_id', $quizIds)
            ->where('passed', true)
            ->select('quiz_id')
            ->distinct()
            ->count();

        return $passedCount === count($quizIds);
    }

    /**
     * Verify all courses in the learning path are completed.
     */
    protected function evaluateCourseCompletion(User $user, $certificate): bool
    {
        if ($certificate->type !== 'learning_path' || !$certificate->learning_path_id) {
            return false;
        }

        $learningPath = $certificate->learningPath;
        if (!$learningPath) {
            return false;
        }

        $courseIds = $learningPath->courses()->pluck('courses.id')->toArray();
        if (empty($courseIds)) {
            return true;
        }

        $completedCount = Enrollment::where('user_id', $user->id)
            ->whereIn('course_id', $courseIds)
            ->where('status', 'completed')
            ->count();

        return $completedCount === count($courseIds);
    }

    /**
     * Verify the user's average score across quizzes in the course or learning path is above threshold.
     */
    protected function evaluateAverageScore(User $user, $certificate, int $minAvg): bool
    {
        $quizIds = [];

        if ($certificate->type === 'course' && $certificate->course_id) {
            $course = $certificate->course;
            if ($course) {
                $quizIds = $course->quizzes()->where('status', 'published')->pluck('quizzes.id')->toArray();
            }
        } elseif ($certificate->type === 'learning_path' && $certificate->learning_path_id) {
            $learningPath = $certificate->learningPath;
            if ($learningPath) {
                $courseIds = $learningPath->courses()->pluck('courses.id')->toArray();
                $quizIds = \App\Models\Quiz::whereIn('course_id', $courseIds)
                    ->where('status', 'published')
                    ->pluck('quizzes.id')
                    ->toArray();
            }
        }

        if (empty($quizIds)) {
            return true;
        }

        // Calculate average of user's best attempts
        $scores = [];
        foreach ($quizIds as $quizId) {
            $bestScore = QuizAttempt::where('user_id', $user->id)
                ->where('quiz_id', $quizId)
                ->max('score');

            if ($bestScore === null) {
                return false; // Did not take this quiz
            }
            $scores[] = $bestScore;
        }

        $average = array_sum($scores) / count($scores);
        return $average >= $minAvg;
    }
}
