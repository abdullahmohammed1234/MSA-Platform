<?php

namespace Tests\Feature\Academy;

use App\Models\Course;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $normalUser;
    protected $coordinatorUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create permissions
        $permissions = [
            'manage_courses',
            'manage_modules',
            'manage_lessons',
            'view_progress',
        ];

        $pModels = [];
        foreach ($permissions as $perm) {
            $pModels[$perm] = Permission::create([
                'uuid' => (string) Str::uuid(),
                'name' => ucfirst(str_replace('_', ' ', $perm)),
                'slug' => $perm,
                'module' => 'Academy',
                'description' => 'Test permission ' . $perm,
            ]);
        }

        // 2. Create roles
        $adminRole = Role::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin',
            'slug' => 'admin',
        ]);
        $adminRole->permissions()->attach($pModels['manage_courses']->id);
        $adminRole->permissions()->attach($pModels['manage_modules']->id);
        $adminRole->permissions()->attach($pModels['manage_lessons']->id);

        $coordinatorRole = Role::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Dawah Coordinator',
            'slug' => 'dawah-coordinator',
        ]);
        $coordinatorRole->permissions()->attach($pModels['manage_courses']->id);
        $coordinatorRole->permissions()->attach($pModels['manage_modules']->id);

        // 3. Create users
        $this->adminUser = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($adminRole);

        $this->coordinatorUser = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Coordinator User',
            'email' => 'coordinator@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $this->coordinatorUser->roles()->attach($coordinatorRole);

        $this->normalUser = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Normal User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    /** @test */
    public function guests_and_normal_users_cannot_access_admin_courses()
    {
        $this->getJson(route('api.admin.courses.index'))
            ->assertStatus(401);

        $this->actingAs($this->normalUser)
            ->getJson(route('api.admin.courses.index'))
            ->assertStatus(403);
    }

    /** @test */
    public function admins_and_coordinators_can_access_admin_courses()
    {
        $course = Course::create([
            'title' => 'Introduction to Dawah',
            'slug' => 'intro-to-dawah',
            'description' => 'Test course description',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('api.admin.courses.index'))
            ->assertStatus(200);

        $this->assertCount(1, $response->json('courses'));
        $this->assertEquals('Introduction to Dawah', $response->json('courses.0.title'));
    }

    /** @test */
    public function admin_can_create_a_course()
    {
        $data = [
            'title' => 'New Dawah Methods',
            'slug' => 'new-dawah-methods',
            'description' => 'Learning new dawah methodologies',
            'difficulty' => 'intermediate',
            'status' => 'draft',
            'estimated_duration' => 120,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.courses.store'), $data)
            ->assertStatus(201);

        $this->assertDatabaseHas('courses', [
            'title' => 'New Dawah Methods',
            'slug' => 'new-dawah-methods',
            'status' => 'draft',
        ]);
    }

    /** @test */
    public function admin_can_update_a_course()
    {
        $course = Course::create([
            'title' => 'Dawah 101',
            'slug' => 'dawah-101',
            'status' => 'draft',
        ]);

        $data = [
            'title' => 'Dawah 101 (Revised)',
            'slug' => 'dawah-101-revised',
            'status' => 'published',
        ];

        $this->actingAs($this->adminUser)
            ->putJson(route('api.admin.courses.update', $course->id), $data)
            ->assertStatus(200);

        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'title' => 'Dawah 101 (Revised)',
            'status' => 'published',
        ]);
    }

    /** @test */
    public function admin_can_delete_a_course_softly()
    {
        $course = Course::create([
            'title' => 'Delete Me',
            'slug' => 'delete-me',
            'status' => 'draft',
        ]);

        $this->actingAs($this->adminUser)
            ->deleteJson(route('api.admin.courses.destroy', $course->id))
            ->assertStatus(200);

        $this->assertSoftDeleted('courses', [
            'id' => $course->id
        ]);
    }

    /** @test */
    public function admin_can_manage_modules_and_lessons()
    {
        $course = Course::create([
            'title' => 'Module Course',
            'slug' => 'module-course',
            'status' => 'draft',
        ]);

        // 1. Create module
        $moduleResponse = $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.academy.modules.store'), [
                'course_id' => $course->id,
                'title' => 'Module 1',
                'description' => 'First module',
                'order' => 1,
            ])
            ->assertStatus(201);

        $moduleId = $moduleResponse->json('module.id');
        $this->assertDatabaseHas('modules', [
            'course_id' => $course->id,
            'title' => 'Module 1',
        ]);

        // 2. Create lesson in module
        $lessonResponse = $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.academy.lessons.store'), [
                'module_id' => $moduleId,
                'title' => 'Lesson 1.1',
                'slug' => 'lesson-1-1',
                'content' => 'Intro lesson content',
                'is_required' => true,
                'order' => 1,
            ])
            ->assertStatus(201);

        $lessonId = $lessonResponse->json('lesson.id');
        $this->assertDatabaseHas('lessons', [
            'module_id' => $moduleId,
            'title' => 'Lesson 1.1',
            'is_required' => true,
        ]);

        // 3. Reorder lessons
        $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.academy.lessons.reorder', $moduleId), [
                'lessons' => [$lessonId]
            ])
            ->assertStatus(200);
    }
}
