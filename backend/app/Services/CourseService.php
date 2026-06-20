<?php

namespace App\Services;

use App\Models\Course;
use App\Repositories\CourseRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class CourseService
{
    protected $courseRepository;

    public function __construct(CourseRepository $courseRepository)
    {
        $this->courseRepository = $courseRepository;
    }

    public function getPublishedCourses(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->courseRepository->getPublished($filters, $perPage);
    }

    public function getAdminCourses(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->courseRepository->getAllAdmin($filters, $perPage);
    }

    public function getCourseByUuid(string $uuid): ?Course
    {
        return $this->courseRepository->findByUuid($uuid);
    }

    public function getCourseBySlug(string $slug): ?Course
    {
        return $this->courseRepository->findBySlug($slug);
    }

    public function createCourse(array $data): Course
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        return $this->courseRepository->create($data);
    }

    public function updateCourse(Course $course, array $data): bool
    {
        if (empty($data['slug']) && isset($data['title'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        if (
            isset($data['status'])
            && $data['status'] === 'published'
            && $course->status !== 'published'
        ) {
            $data['published_at'] = now();
        }

        return $this->courseRepository->update($course, $data);
    }

    public function deleteCourse(Course $course): ?bool
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
