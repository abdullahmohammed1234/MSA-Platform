<?php

namespace App\Services;

use App\Models\LearningPath;
use App\Repositories\LearningPathRepository;
use Illuminate\Database\Eloquent\Collection;

class LearningPathService
{
    protected $learningPathRepository;

    public function __construct(LearningPathRepository $learningPathRepository)
    {
        $this->learningPathRepository = $learningPathRepository;
    }

    public function getLearningPath(int $id): ?LearningPath
    {
        return $this->learningPathRepository->find($id);
    }

    public function getLearningPathByUuid(string $uuid): ?LearningPath
    {
        return $this->learningPathRepository->findByUuid($uuid);
    }

    public function getLearningPathBySlug(string $slug): ?LearningPath
    {
        return $this->learningPathRepository->findBySlug($slug);
    }

    public function getAllLearningPaths(): Collection
    {
        return $this->learningPathRepository->getAll();
    }

    public function createLearningPath(array $data): LearningPath
    {
        return $this->learningPathRepository->create($data);
    }

    public function updateLearningPath(LearningPath $path, array $data): bool
    {
        return $this->learningPathRepository->update($path, $data);
    }

    public function deleteLearningPath(LearningPath $path): ?bool
    {
        return $this->learningPathRepository->delete($path);
    }

    public function assignCourseToPath(LearningPath $path, int $courseId, int $order): void
    {
        $this->learningPathRepository->assignCourse($path, $courseId, $order);
    }

    public function removeCourseFromPath(LearningPath $path, int $courseId): void
    {
        $this->learningPathRepository->removeCourse($path, $courseId);
    }

    public function reorderCoursesInPath(LearningPath $path, array $courseIds): void
    {
        $this->learningPathRepository->reorderCourses($path, $courseIds);
    }
}
