<?php
# d:\projects\msa + dawah\MSA Platform\backend\tests\Unit\Services\CourseManagementServiceTest.php

namespace Tests\Unit\Services;

use App\Models\Course;
use App\Models\User;
use App\Repositories\CourseRepository;
use App\Services\CourseManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CourseManagementService $courseService;
    protected CourseRepository $courseRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->courseRepository = new CourseRepository();
        $this->courseService = new CourseManagementService($this->courseRepository);
    }

    public function test_get_courses_returns_paginated_results()
    {
        Course::factory()->count(3)->create();

        $results = $this->courseService->getCourses();

        $this->assertCount(3, $results->items());
    }

    public function test_get_course_returns_specific_course()
    {
        $course = Course::factory()->create();

        $result = $this->courseService->getCourse($course->id);

        $this->assertEquals($course->id, $result->id);
    }

    public function test_get_course_by_uuid()
    {
        $course = Course::factory()->create();

        $result = $this->courseService->getCourseByUuid($course->uuid);

        $this->assertEquals($course->id, $result->id);
    }

    public function test_create_course_creates_and_generates_slug()
    {
        $user = User::factory()->create();
        $data = [
            'title' => 'Islamic Studies',
            'description' => 'A course on basic Islamic studies.',
            'difficulty' => 'beginner',
            'created_by' => $user->id,
        ];

        $course = $this->courseService->createCourse($data);

        $this->assertInstanceOf(Course::class, $course);
        $this->assertEquals('islamic-studies', $course->slug);
        $this->assertDatabaseHas('courses', ['title' => 'Islamic Studies']);
    }

    public function test_update_course_updates_details()
    {
        $course = Course::factory()->create(['title' => 'Old Title']);
        $data = ['title' => 'Updated Title'];

        $updated = $this->courseService->updateCourse($course, $data);

        $this->assertTrue($updated);
        $this->assertEquals('Updated Title', $course->fresh()->title);
        $this->assertEquals('updated-title', $course->fresh()->slug);
    }

    public function test_delete_course_soft_deletes()
    {
        $course = Course::factory()->create();

        $deleted = $this->courseService->deleteCourse($course);

        $this->assertTrue($deleted);
        $this->assertSoftDeleted('courses', ['id' => $course->id]);
    }

    public function test_publish_course_updates_status()
    {
        $course = Course::factory()->create(['status' => 'draft']);

        $published = $this->courseService->publishCourse($course);

        $this->assertTrue($published);
        $this->assertEquals('published', $course->fresh()->status);
        $this->assertNotNull($course->fresh()->published_at);
    }

    public function test_archive_course_updates_status()
    {
        $course = Course::factory()->create(['status' => 'published']);

        $archived = $this->courseService->archiveCourse($course);

        $this->assertTrue($archived);
        $this->assertEquals('archived', $course->fresh()->status);
    }
}
