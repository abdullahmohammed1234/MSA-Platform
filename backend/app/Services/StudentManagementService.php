<?php

namespace App\Services;

use App\Models\User;
use App\Models\Enrollment;
use App\Models\Progress;
use App\Models\QuizAttempt;
use App\Models\CertificateAward;
use Illuminate\Pagination\LengthAwarePaginator;

class StudentManagementService
{
    public function getStudents(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::whereHas('roles', function ($q) {
            $q->where('slug', 'volunteer');
        })->withCount(['enrollments', 'certificateAwards']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (isset($filters['status'])) {
            if ($filters['status'] === 'active') {
                $query->where('is_active', true);
            } elseif ($filters['status'] === 'suspended') {
                $query->where('is_active', false);
            }
        }

        return $query->latest()->paginate($perPage);
    }

    public function getStudentProfile(User $student): array
    {
        // Load counts and relations
        $student->load(['enrollments.course', 'certificateAwards.certificate', 'quizAttempts.quiz']);

        $enrollments = $student->enrollments->map(function ($enrollment) use ($student) {
            $completedLessons = Progress::where('user_id', $student->id)
                ->where('course_id', $enrollment->course_id)
                ->where('completed', true)
                ->count();
            
            $totalLessons = $enrollment->course->modules()->withCount('lessons')->get()->sum('lessons_count');

            return [
                'course_id' => $enrollment->course_id,
                'title' => $enrollment->course->title,
                'status' => $enrollment->status,
                'enrolled_at' => $enrollment->enrolled_at,
                'completed_at' => $enrollment->completed_at,
                'progress' => $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100, 2) : 0
            ];
        });

        return [
            'id' => $student->id,
            'uuid' => $student->uuid,
            'name' => $student->name,
            'email' => $student->email,
            'avatar' => $student->avatar,
            'is_active' => $student->is_active,
            'last_login_at' => $student->last_login_at,
            'email_verified_at' => $student->email_verified_at,
            'enrollments' => $enrollments,
            'certificates' => $student->certificateAwards,
            'quiz_attempts' => $student->quizAttempts
        ];
    }

    public function suspendAccess(User $student): bool
    {
        return $student->update(['is_active' => false]);
    }

    public function reactivateAccess(User $student): bool
    {
        return $student->update(['is_active' => true]);
    }
}
