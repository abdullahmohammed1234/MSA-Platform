<?php

namespace App\Services;

use App\Models\User;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Progress;
use App\Models\QuizAttempt;
use App\Models\CertificateAward;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class ProgressManagementService
{
    public function getStudentProgress(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Enrollment::with(['user', 'course']);

        if (!empty($filters['student_id'])) {
            $query->where('user_id', $filters['student_id']);
        }

        if (!empty($filters['course_id'])) {
            $query->where('course_id', $filters['course_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['mentor_id'])) {
            $query->whereHas('user.mentors', function ($q) use ($filters) {
                $q->where('mentor_id', $filters['mentor_id']);
            });
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('enrolled_at', [$filters['start_date'], $filters['end_date']]);
        }

        $enrollments = $query->latest('enrolled_at')->paginate($perPage);

        // Map progress data
        $enrollments->getCollection()->transform(function ($enrollment) {
            $totalLessons = $enrollment->course->modules()->withCount('lessons')->get()->sum('lessons_count');
            
            $completedLessons = Progress::where('user_id', $enrollment->user_id)
                ->where('course_id', $enrollment->course_id)
                ->where('completed', true)
                ->count();

            $quizzes = QuizAttempt::where('user_id', $enrollment->user_id)
                ->whereHas('quiz', function ($q) use ($enrollment) {
                    $q->where('course_id', $enrollment->course_id);
                })
                ->with('quiz')
                ->latest()
                ->get()
                ->unique('quiz_id');

            $certificate = CertificateAward::where('user_id', $enrollment->user_id)
                ->whereHas('certificate', function ($q) use ($enrollment) {
                    $q->where('course_id', $enrollment->course_id);
                })
                ->first();

            return [
                'id' => $enrollment->id,
                'user' => [
                    'id' => $enrollment->user->id,
                    'name' => $enrollment->user->name,
                    'email' => $enrollment->user->email,
                ],
                'course' => [
                    'id' => $enrollment->course->id,
                    'title' => $enrollment->course->title,
                ],
                'status' => $enrollment->status,
                'enrolled_at' => $enrollment->enrolled_at,
                'completed_at' => $enrollment->completed_at,
                'progress_percentage' => $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100, 2) : 0,
                'completed_lessons_count' => $completedLessons,
                'total_lessons_count' => $totalLessons,
                'quiz_scores' => $quizzes->map(function ($attempt) {
                    return [
                        'quiz_title' => $attempt->quiz->title,
                        'score' => $attempt->score,
                        'passed' => $attempt->passed,
                    ];
                })->values()->toArray(),
                'certificate_earned' => $certificate ? [
                    'code' => $certificate->code,
                    'issued_at' => $certificate->issued_at,
                ] : null
            ];
        });

        return $enrollments;
    }
}
