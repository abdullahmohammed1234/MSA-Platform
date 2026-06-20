<?php

namespace App\Repositories;

use App\Models\Lesson;
use Illuminate\Database\Eloquent\Collection;

class LessonRepository
{
    public function find(int $id): ?Lesson
    {
        return Lesson::with(['module.course'])->find($id);
    }

    public function findBySlug(string $slug): ?Lesson
    {
        return Lesson::with(['module.course'])->where('slug', $slug)->first();
    }

    public function getByModule(int $moduleId): Collection
    {
        return Lesson::where('module_id', $moduleId)
            ->orderBy('order', 'asc')
            ->get();
    }

    public function create(array $data): Lesson
    {
        // Auto-assign order if not provided
        if (!isset($data['order'])) {
            $data['order'] = Lesson::where('module_id', $data['module_id'])->count() + 1;
        }

        return Lesson::create($data);
    }

    public function update(Lesson $lesson, array $data): bool
    {
        return $lesson->update($data);
    }

    public function delete(Lesson $lesson): ?bool
    {
        return $lesson->delete();
    }

    public function reorder(int $moduleId, array $lessonIds): void
    {
        foreach ($lessonIds as $index => $id) {
            Lesson::where('id', $id)
                ->where('module_id', $moduleId)
                ->update(['order' => $index + 1]);
        }
    }
}
