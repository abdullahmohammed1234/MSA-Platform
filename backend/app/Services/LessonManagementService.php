<?php

namespace App\Services;

use App\Models\Lesson;
use App\Repositories\LessonRepository;
use Illuminate\Database\Eloquent\Collection;

class LessonManagementService
{
    protected $lessonRepository;

    public function __construct(LessonRepository $lessonRepository)
    {
        $this->lessonRepository = $lessonRepository;
    }

    public function getLessonsByModule(int $moduleId): Collection
    {
        return $this->lessonRepository->getByModule($moduleId);
    }

    public function getLesson(int $id): ?Lesson
    {
        return $this->lessonRepository->find($id);
    }

    public function createLesson(array $data): Lesson
    {
        if (empty($data['slug'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['title']);
        }
        return $this->lessonRepository->create($data);
    }

    public function updateLesson(Lesson $lesson, array $data): bool
    {
        if (empty($data['slug']) && isset($data['title'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['title']);
        }
        return $this->lessonRepository->update($lesson, $data);
    }

    public function deleteLesson(Lesson $lesson): bool
    {
        return $this->lessonRepository->delete($lesson);
    }

    public function reorderLessons(int $moduleId, array $lessonIds): void
    {
        $this->lessonRepository->reorder($moduleId, $lessonIds);
    }
}
