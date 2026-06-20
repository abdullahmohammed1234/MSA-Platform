<?php

namespace Tests\Feature\Academy;

use App\Models\Course;
use App\Models\LearningPath;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LearningPathTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $studentUser;
    protected $learningPath;
    protected $course1;
    protected $course2;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create permissions
        $permissions = [
            'manage_learning_paths',
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

        // 2. Create admin role & user
        $adminRole = Role::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin',
            'slug' => 'admin',
        ]);
        $adminRole->permissions()->attach($pModels['manage_learning_paths']->id);

        $this->adminUser = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($adminRole);

        // 3. Create student user
        $this->studentUser = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Student User',
            'email' => 'student@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $this->assignVolunteerRole($this->studentUser);

        // 4. Create courses
        $this->course1 = Course::create([
            'title' => 'Course Alpha',
            'slug' => 'course-alpha',
            'status' => 'published',
        ]);

        $this->course2 = Course::create([
            'title' => 'Course Beta',
            'slug' => 'course-beta',
            'status' => 'published',
        ]);

        // 5. Create a learning path
        $this->learningPath = LearningPath::create([
            'title' => 'Volunteer Pathway',
            'slug' => 'volunteer-pathway',
            'description' => 'The path for volunteers.',
        ]);
    }

    /** @test */
    public function guests_cannot_manage_learning_paths()
    {
        $this->postJson(route('api.admin.academy.learning-paths.store'), [])
            ->assertStatus(401);

        $this->actingAs($this->studentUser)
            ->postJson(route('api.admin.academy.learning-paths.store'), [])
            ->assertStatus(403);
    }

    /** @test */
    public function admin_can_create_learning_path()
    {
        $data = [
            'title' => 'Mentor Pathway',
            'slug' => 'mentor-pathway',
            'description' => 'Path to become a mentor.',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.academy.learning-paths.store'), $data)
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('learning_paths', [
            'title' => 'Mentor Pathway',
            'slug' => 'mentor-pathway',
        ]);
    }

    /** @test */
    public function admin_can_assign_and_reorder_courses_in_path()
    {
        // 1. Assign Course Alpha to path
        $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.academy.learning-paths.assign', $this->learningPath->id), [
                'course_id' => $this->course1->id,
                'order' => 1
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('learning_path_course', [
            'learning_path_id' => $this->learningPath->id,
            'course_id' => $this->course1->id,
            'order' => 1,
        ]);

        // 2. Assign Course Beta to path
        $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.academy.learning-paths.assign', $this->learningPath->id), [
                'course_id' => $this->course2->id,
                'order' => 2
            ])
            ->assertStatus(200);

        // 3. Reorder courses in path (Beta becomes 1st, Alpha becomes 2nd)
        $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.academy.learning-paths.reorder', $this->learningPath->id), [
                'courses' => [$this->course2->id, $this->course1->id]
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('learning_path_course', [
            'learning_path_id' => $this->learningPath->id,
            'course_id' => $this->course2->id,
            'order' => 1,
        ]);

        $this->assertDatabaseHas('learning_path_course', [
            'learning_path_id' => $this->learningPath->id,
            'course_id' => $this->course1->id,
            'order' => 2,
        ]);
    }

    /** @test */
    public function students_can_list_learning_paths()
    {
        // Link Course Beta to pathway
        $this->learningPath->courses()->attach($this->course2->id, ['order' => 1]);

        $response = $this->actingAs($this->studentUser)
            ->getJson(route('api.academy.learning-paths'))
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('learning_paths'));
        $this->assertEquals('Volunteer Pathway', $response->json('learning_paths.0.title'));
        $this->assertEquals('Course Beta', $response->json('learning_paths.0.courses.0.title'));
    }
}
