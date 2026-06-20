<?php

namespace Tests\Feature\Academy;

use App\Models\Course;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use App\Models\MentorAssignment;
use App\Models\Enrollment;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\CertificateAward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAcademyTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $normalUser;
    protected $mentorUser;
    protected $studentUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create permissions
        $permissions = [
            'manage_courses',
            'manage_modules',
            'manage_lessons',
            'manage_quizzes',
            'manage_students',
            'manage_volunteers',
            'manage_mentors',
            'assign_mentors',
            'view_progress',
            'manage_certificates',
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
        foreach ($pModels as $pm) {
            $adminRole->permissions()->attach($pm->id);
        }

        $mentorRole = Role::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Mentor',
            'slug' => 'mentor',
        ]);
        $mentorRole->permissions()->attach($pModels['view_progress']->id);

        $studentRole = Role::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Volunteer',
            'slug' => 'volunteer',
        ]);

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

        $this->mentorUser = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Mentor User',
            'email' => 'mentor@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $this->mentorUser->roles()->attach($mentorRole);

        $this->studentUser = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Student User',
            'email' => 'student@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $this->studentUser->roles()->attach($studentRole);

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
    public function admin_can_list_and_manage_questions()
    {
        $course = Course::create([
            'title' => 'Test Course',
            'slug' => 'test-course',
            'status' => 'published',
        ]);

        $quiz = Quiz::create([
            'course_id' => $course->id,
            'title' => 'Test Quiz',
            'passing_score' => 70,
            'status' => 'published',
        ]);

        $question = Question::create([
            'quiz_id' => $quiz->id,
            'type' => 'multiple_choice',
            'question' => 'What is the first pillar?',
            'options' => ['Shahadah', 'Salah'],
            'correct_answer' => [0],
            'points' => 1,
            'order' => 1,
            'category' => 'Aqeedah',
            'difficulty' => 'easy',
        ]);

        // List questions
        $response = $this->actingAs($this->adminUser)
            ->getJson(route('api.admin.academy.questions.index'))
            ->assertStatus(200);

        $this->assertCount(1, $response->json('questions'));

        // Delete question
        $this->actingAs($this->adminUser)
            ->deleteJson(route('api.admin.academy.questions.destroy', $question->id))
            ->assertStatus(200);

        $this->assertDatabaseMissing('questions', ['id' => $question->id]);
    }

    /** @test */
    public function admin_can_manage_student_access()
    {
        // Get student profile
        $this->actingAs($this->adminUser)
            ->getJson(route('api.admin.academy.students.show', $this->studentUser->id))
            ->assertStatus(200);

        // Suspend
        $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.academy.students.suspend', $this->studentUser->id))
            ->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $this->studentUser->id,
            'is_active' => false
        ]);

        // Reactivate
        $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.academy.students.reactivate', $this->studentUser->id))
            ->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $this->studentUser->id,
            'is_active' => true
        ]);
    }

    /** @test */
    public function admin_can_assign_and_remove_mentors()
    {
        // Index mentors
        $this->actingAs($this->adminUser)
            ->getJson(route('api.admin.academy.mentors.index'))
            ->assertStatus(200);

        // Assign mentor
        $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.academy.assignments.store'), [
                'mentor_id' => $this->mentorUser->id,
                'student_id' => $this->studentUser->id,
                'notes' => 'Test connection',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('mentor_assignments', [
            'mentor_id' => $this->mentorUser->id,
            'student_id' => $this->studentUser->id,
            'status' => 'active',
        ]);

        // Disconnect mentor
        $this->actingAs($this->adminUser)
            ->deleteJson(route('api.admin.academy.assignments.destroy', [
                'mentorId' => $this->mentorUser->id,
                'studentId' => $this->studentUser->id,
            ]))
            ->assertStatus(200);

        $this->assertDatabaseMissing('mentor_assignments', [
            'mentor_id' => $this->mentorUser->id,
            'student_id' => $this->studentUser->id,
        ]);
    }

    /** @test */
    public function admin_can_bulk_assign_mentors()
    {
        $student2 = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Student 2',
            'email' => 'student2@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.academy.assignments.bulk'), [
                'mentor_id' => $this->mentorUser->id,
                'student_ids' => [$this->studentUser->id, $student2->id],
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('mentor_assignments', [
            'mentor_id' => $this->mentorUser->id,
            'student_id' => $this->studentUser->id,
        ]);

        $this->assertDatabaseHas('mentor_assignments', [
            'mentor_id' => $this->mentorUser->id,
            'student_id' => $student2->id,
        ]);
    }
}
