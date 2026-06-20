<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Repositories\ProgressRepository;
use App\Repositories\EnrollmentRepository;
use App\Repositories\CourseRepository;
use App\Repositories\QuizRepository;

class ProgressService
{
    protected $progressRepository;
    protected $enrollmentRepository;
    protected $courseRepository;
    protected $quizRepository;

    public function __construct(
        ProgressRepository $progressRepository,
        EnrollmentRepository $enrollmentRepository,
        CourseRepository $courseRepository,
        QuizRepository $quizRepository,
    ) {
        $this->progressRepository = $progressRepository;
        $this->enrollmentRepository = $enrollmentRepository;
        $this->courseRepository = $courseRepository;
        $this->quizRepository = $quizRepository;
    }

    /**
     * Mark a lesson as complete for a user and check for course completion.
     */
    public function completeLesson(int $userId, int $courseId, int $lessonId): array
    {
        // 1. Update or create progress record
        $progress = $this->progressRepository->updateOrCreateProgress($userId, $courseId, $lessonId, [
            'completion_percentage' => 100,
            'completed' => true,
            'completed_at' => now(),
        ]);

        // 2. Recompute course progress percentage
        $percentage = $this->calculateCourseProgressPercentage($userId, $courseId);

        // 3. Check if user completes the entire course (Lessons + Quizzes)
        $courseCompleted = $this->checkAndCompleteCourse($userId, $courseId);

        return [
            'progress' => $progress,
            'completion_percentage' => $percentage,
            'course_completed' => $courseCompleted,
        ];
    }

    /**
     * Calculate completion percentage of a course.
     */
    public function calculateCourseProgressPercentage(int $userId, int $courseId): int
    {
        $course = $this->courseRepository->find($courseId);
        if (!$course) {
            return 0;
        }

        // Count only required lessons
        $requiredLessons = $course->lessons()->where('is_required', true)->get();
        $totalRequired = $requiredLessons->count();
        if ($totalRequired === 0) {
            return 100;
        }

        $completedRequiredCount = 0;
        foreach ($requiredLessons as $lesson) {
            $prog = $this->progressRepository->findProgress($userId, $lesson->id);
            if ($prog && $prog->completed) {
                $completedRequiredCount++;
            }
        }

        return (int) round(($completedRequiredCount / $totalRequired) * 100);
    }

    /**
     * Check if a course meets all requirements for completion, and issue a certificate.
     */
    public function checkAndCompleteCourse(int $userId, int $courseId): bool
    {
        $course = $this->courseRepository->find($courseId);
        if (!$course) {
            return false;
        }

        // 1. Check if enrollment exists
        $enrollment = $this->enrollmentRepository->findEnrollment($userId, $courseId);
        if (!$enrollment || $enrollment->status === 'completed') {
            return $enrollment ? true : false;
        }

        // 2. Check if all required lessons are completed
        $requiredLessons = $course->lessons()->where('is_required', true)->get();
        foreach ($requiredLessons as $lesson) {
            $prog = $this->progressRepository->findProgress($userId, $lesson->id);
            if (!$prog || !$prog->completed) {
                return false;
            }
        }

        // 3. Check if all quizzes are passed
        $quizzes = $course->quizzes()->where('status', 'published')->get();
        foreach ($quizzes as $quiz) {
            $bestAttempt = $this->quizRepository->getBestAttempt($userId, $quiz->id);
            if (!$bestAttempt || !$bestAttempt->passed) {
                return false;
            }
        }

        // 4. Mark enrollment as completed
        $this->enrollmentRepository->updateStatus($enrollment, 'completed');

        return true;
    }
}
