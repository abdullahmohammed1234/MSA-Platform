<?php

namespace App\Repositories;

use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Collection;

class EnrollmentRepository
{
    public function find(int $id): ?Enrollment
    {
        return Enrollment::with(['user', 'course'])->find($id);
    }

    public function findEnrollment(int $userId, int $courseId): ?Enrollment
    {
        return Enrollment::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();
    }

    public function create(array $data): Enrollment
    {
        return Enrollment::create([
            'user_id' => $data['user_id'],
            'course_id' => $data['course_id'],
            'status' => $data['status'] ?? 'active',
            'enrolled_at' => $data['enrolled_at'] ?? now()
        ]);
    }

    public function updateStatus(Enrollment $enrollment, string $status): bool
    {
        $attributes = ['status' => $status];
        if ($status === 'completed') {
            $attributes['completed_at'] = now();
        }
        return $enrollment->update($attributes);
    }

    public function getUserEnrollments(int $userId): Collection
    {
        return Enrollment::with(['course'])->where('user_id', $userId)->get();
    }
}
