<?php

namespace App\Services;

use App\Models\Lesson;
use App\Repositories\LessonRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class LessonService
{
    protected $lessonRepository;

    public function __construct(LessonRepository $lessonRepository)
    {
        $this->lessonRepository = $lessonRepository;
    }

    public function getLesson(int $id): ?Lesson
    {
        return $this->lessonRepository->find($id);
    }

    public function getLessonBySlug(string $slug): ?Lesson
    {
        return $this->lessonRepository->findBySlug($slug);
    }

    public function getModuleLessons(int $moduleId): Collection
    {
        return $this->lessonRepository->getByModule($moduleId);
    }

    public function createLesson(array $data): Lesson
    {
        $data = $this->ensureSlug($data);

        return $this->lessonRepository->create($data);
    }

    public function updateLesson(Lesson $lesson, array $data): bool
    {
        if (empty($data['slug']) && isset($data['title'])) {
            $data = $this->ensureSlug($data, $lesson->id);
        }

        return $this->lessonRepository->update($lesson, $data);
    }

    public function deleteLesson(Lesson $lesson): ?bool
    {
        return $this->lessonRepository->delete($lesson);
    }

    public function reorderLessons(int $moduleId, array $lessonIds): void
    {
        $this->lessonRepository->reorder($moduleId, $lessonIds);
    }

    private function ensureSlug(array $data, ?int $ignoreLessonId = null): array
    {
        if (!empty($data['slug'])) {
            return $data;
        }

        $baseSlug = Str::slug($data['title'] ?? 'lesson');
        $slug = $baseSlug;
        $suffix = 1;

        while (
            Lesson::where('module_id', $data['module_id'])
                ->when($ignoreLessonId, fn ($query) => $query->where('id', '!=', $ignoreLessonId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        $data['slug'] = $slug;

        return $data;
    }
}
