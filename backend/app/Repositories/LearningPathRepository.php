<?php

namespace App\Repositories;

use App\Models\LearningPath;
use Illuminate\Database\Eloquent\Collection;

class LearningPathRepository
{
    public function find(int $id): ?LearningPath
    {
        return LearningPath::with(['courses'])->find($id);
    }

    public function findByUuid(string $uuid): ?LearningPath
    {
        return LearningPath::with(['courses'])->where('uuid', $uuid)->first();
    }

    public function findBySlug(string $slug): ?LearningPath
    {
        return LearningPath::with(['courses'])->where('slug', $slug)->first();
    }

    public function getAll(): Collection
    {
        return LearningPath::with(['courses'])->get();
    }

    public function create(array $data): LearningPath
    {
        return LearningPath::create($data);
    }

    public function update(LearningPath $path, array $data): bool
    {
        return $path->update($data);
    }

    public function delete(LearningPath $path): ?bool
    {
        return $path->delete();
    }

    public function assignCourse(LearningPath $path, int $courseId, int $order): void
    {
        $path->courses()->syncWithoutDetaching([
            $courseId => ['order' => $order]
        ]);
    }

    public function removeCourse(LearningPath $path, int $courseId): void
    {
        $path->courses()->detach($courseId);
    }

    public function reorderCourses(LearningPath $path, array $courseIds): void
    {
        $syncData = [];
        foreach ($courseIds as $index => $courseId) {
            $syncData[$courseId] = ['order' => $index + 1];
        }
        $path->courses()->sync($syncData);
    }
}
