<?php

namespace App\Services;

use App\Models\Course;
use App\Repositories\CourseRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class CourseManagementService
{
    protected $courseRepository;

    public function __construct(CourseRepository $courseRepository)
    {
        $this->courseRepository = $courseRepository;
    }

    public function getCourses(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->courseRepository->getAllAdmin($filters, $perPage);
    }

    public function getCourse(int $id): ?Course
    {
        return $this->courseRepository->find($id);
    }

    public function getCourseByUuid(string $uuid): ?Course
    {
        return $this->courseRepository->findByUuid($uuid);
    }

    public function createCourse(array $data): Course
    {
        if (empty($data['slug'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['title']);
        }
        return $this->courseRepository->create($data);
    }

    public function updateCourse(Course $course, array $data): bool
    {
        if (empty($data['slug']) && isset($data['title'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['title']);
        }
        return $this->courseRepository->update($course, $data);
    }

    public function deleteCourse(Course $course): bool
    {
        return $this->courseRepository->delete($course);
    }

    public function publishCourse(Course $course): bool
    {
        return $this->courseRepository->update($course, [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function archiveCourse(Course $course): bool
    {
        return $this->courseRepository->update($course, [
            'status' => 'archived',
        ]);
    }
}
