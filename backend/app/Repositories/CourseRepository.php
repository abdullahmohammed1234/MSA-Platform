<?php

namespace App\Repositories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CourseRepository
{
    public function find(int $id): ?Course
    {
        return Course::with(['creator', 'modules.lessons', 'quizzes.questions'])->find($id);
    }

    public function findByUuid(string $uuid): ?Course
    {
        return Course::with(['creator', 'modules.lessons', 'quizzes.questions'])->where('uuid', $uuid)->first();
    }

    public function findBySlug(string $slug): ?Course
    {
        return Course::with(['creator', 'modules.lessons', 'quizzes.questions'])->where('slug', $slug)->first();
    }

    public function getPublished(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Course::with(['creator'])->where('status', 'published');

        if (isset($filters['difficulty'])) {
            $query->where('difficulty', $filters['difficulty']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->latest('published_at')->paginate($perPage);
    }

    public function getAllAdmin(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Course::with(['creator'])->withTrashed();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['difficulty'])) {
            $query->where('difficulty', $filters['difficulty']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->latest()->paginate($perPage);
    }

    public function create(array $data): Course
    {
        return Course::create($data);
    }

    public function update(Course $course, array $data): bool
    {
        return $course->update($data);
    }

    public function delete(Course $course): ?bool
    {
        return $course->delete();
    }

    public function restore(Course $course): bool
    {
        return $course->restore();
    }
}
