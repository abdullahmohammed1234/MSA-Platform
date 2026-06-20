<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Repositories\EnrollmentRepository;
use Exception;
use Illuminate\Database\Eloquent\Collection;

class EnrollmentService
{
    protected $enrollmentRepository;

    public function __construct(EnrollmentRepository $enrollmentRepository)
    {
        $this->enrollmentRepository = $enrollmentRepository;
    }

    public function enroll(int $userId, int $courseId): Enrollment
    {
        $existing = $this->enrollmentRepository->findEnrollment($userId, $courseId);
        if ($existing) {
            if ($existing->status === 'dropped') {
                $this->enrollmentRepository->updateStatus($existing, 'active');
                return $existing->fresh();
            }
            return $existing;
        }

        return $this->enrollmentRepository->create([
            'user_id' => $userId,
            'course_id' => $courseId,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);
    }

    public function drop(int $userId, int $courseId): bool
    {
        $enrollment = $this->enrollmentRepository->findEnrollment($userId, $courseId);
        if (!$enrollment) {
            throw new Exception("Enrollment not found.");
        }

        return $this->enrollmentRepository->updateStatus($enrollment, 'dropped');
    }

    public function getEnrollment(int $userId, int $courseId): ?Enrollment
    {
        return $this->enrollmentRepository->findEnrollment($userId, $courseId);
    }

    public function getUserEnrollments(int $userId): Collection
    {
        return $this->enrollmentRepository->getUserEnrollments($userId);
    }
}
