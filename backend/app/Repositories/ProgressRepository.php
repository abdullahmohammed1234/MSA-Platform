<?php

namespace App\Repositories;

use App\Models\Progress;
use Illuminate\Database\Eloquent\Collection;

class ProgressRepository
{
    public function findProgress(int $userId, int $lessonId): ?Progress
    {
        return Progress::where('user_id', $userId)
            ->where('lesson_id', $lessonId)
            ->first();
    }

    public function getProgressForCourse(int $userId, int $courseId): Collection
    {
        return Progress::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->get();
    }

    public function getCompletedLessonsCount(int $userId, int $courseId): int
    {
        return Progress::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('completed', true)
            ->count();
    }

    public function updateOrCreateProgress(int $userId, int $courseId, int $lessonId, array $data): Progress
    {
        return Progress::updateOrCreate(
            [
                'user_id' => $userId,
                'lesson_id' => $lessonId
            ],
            [
                'course_id' => $courseId,
                'completion_percentage' => $data['completion_percentage'] ?? 100,
                'completed' => $data['completed'] ?? true,
                'completed_at' => $data['completed_at'] ?? now()
            ]
        );
    }
}
