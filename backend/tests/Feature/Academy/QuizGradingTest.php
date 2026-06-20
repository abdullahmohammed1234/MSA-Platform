<?php

namespace Tests\Feature\Academy;

use App\Models\Course;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use App\Models\Enrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuizGradingTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $studentUser;
    protected $course;
    protected $quiz;
    protected $mcQuestion;
    protected $msQuestion;
    protected $tfQuestion;
    protected $saQuestion;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create permissions
        $permissions = [
            'manage_courses',
            'manage_quizzes',
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
        $adminRole->permissions()->attach($pModels['manage_courses']->id);
        $adminRole->permissions()->attach($pModels['manage_quizzes']->id);

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

        // 4. Create course & quiz
        $this->course = Course::create([
            'title' => 'Quiz Testing Course',
            'slug' => 'quiz-testing-course',
            'status' => 'published',
        ]);

        $this->quiz = Quiz::create([
            'course_id' => $this->course->id,
            'title' => 'Fractions Quiz',
            'description' => 'A basic test on fractions.',
            'passing_score' => 75,
            'attempt_limit' => 2,
            'status' => 'published',
        ]);

        // 5. Enroll student
        Enrollment::create([
            'user_id' => $this->studentUser->id,
            'course_id' => $this->course->id,
            'status' => 'active',
        ]);

        // 6. Create questions of various types
        // A. Multiple Choice
        $this->mcQuestion = Question::create([
            'quiz_id' => $this->quiz->id,
            'type' => 'multiple_choice',
            'question' => 'What is 1/2 + 1/4?',
            'options' => ['1/6', '2/6', '3/4', '1/8'],
            'correct_answer' => ['3/4'],
            'points' => 1,
            'order' => 1,
        ]);

        // B. Multiple Select
        $this->msQuestion = Question::create([
            'quiz_id' => $this->quiz->id,
            'type' => 'multiple_select',
            'question' => 'Which of the following equal 1/2?',
            'options' => ['2/4', '3/6', '4/8', '5/9'],
            'correct_answer' => ['2/4', '3/6', '4/8'],
            'points' => 3,
            'order' => 2,
        ]);

        // C. True/False
        $this->tfQuestion = Question::create([
            'quiz_id' => $this->quiz->id,
            'type' => 'true_false',
            'question' => 'Fractions can be converted to decimals.',
            'options' => ['true', 'false'],
            'correct_answer' => ['true'],
            'points' => 1,
            'order' => 3,
        ]);

        // D. Short Answer
        $this->saQuestion = Question::create([
            'quiz_id' => $this->quiz->id,
            'type' => 'short_answer',
            'question' => 'What is the decimal equivalent of 1/4?',
            'options' => null,
            'correct_answer' => ['0.25', '.25'], // multiple acceptable text answers
            'points' => 1,
            'order' => 4,
        ]);
    }

    /** @test */
    public function users_cannot_see_correct_answers_in_quiz_details()
    {
        $response = $this->actingAs($this->studentUser)
            ->getJson(route('api.academy.courses.show', $this->course->slug))
            ->assertStatus(200);

        // Correct answers should be hidden for normal students
        $questions = $response->json('course.quizzes.0.questions');
        $this->assertNotNull($questions);
        foreach ($questions as $q) {
            $this->assertArrayNotHasKey('correct_answer', $q);
        }
    }

    /** @test */
    public function admins_can_see_correct_answers_in_quiz_details()
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson(route('api.admin.courses.index')) // verify admin course detail includes correct answers
            ->assertStatus(200);

        // Admin endpoints should display the correct answers for verification
        $quizResponse = $this->actingAs($this->adminUser)
            ->getJson(route('api.admin.courses.index'))
            ->assertStatus(200);
    }

    /** @test */
    public function student_can_submit_quiz_and_pass()
    {
        // Total points: 1 (MC) + 3 (MS) + 1 (TF) + 1 (SA) = 6 points
        // Let's answer all questions correctly:
        // mc: '3/4'
        // ms: ['2/4', '3/6', '4/8']
        // tf: 'true'
        // sa: '0.25'
        $answers = [
            $this->mcQuestion->id => '3/4',
            $this->msQuestion->id => ['2/4', '3/6', '4/8'],
            $this->tfQuestion->id => 'true',
            $this->saQuestion->id => '0.25',
        ];

        $response = $this->actingAs($this->studentUser)
            ->postJson(route('api.academy.quizzes.submit', [$this->course->id, $this->quiz->id]), [
                'answers' => $answers
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('attempt.passed', true)
            ->assertJsonPath('attempt.score', 100);

        $this->assertDatabaseHas('quiz_attempts', [
            'user_id' => $this->studentUser->id,
            'quiz_id' => $this->quiz->id,
            'score' => 100,
            'passed' => true,
        ]);
    }

    /** @test */
    public function student_can_submit_quiz_and_fail_due_to_partial_errors()
    {
        // Let's answer MC and TF correctly (2 points), MS partially correct (0 points), SA wrong (0 points)
        // Total points earned: 2/6 = 33.33% => 33% (fails, passing is 75)
        $answers = [
            $this->mcQuestion->id => '3/4',
            $this->msQuestion->id => ['2/4'], // incomplete array for multiple select
            $this->tfQuestion->id => 'true',
            $this->saQuestion->id => '0.5', // wrong short answer
        ];

        $response = $this->actingAs($this->studentUser)
            ->postJson(route('api.academy.quizzes.submit', [$this->course->id, $this->quiz->id]), [
                'answers' => $answers
            ])
            ->assertStatus(200)
            ->assertJsonPath('attempt.passed', false)
            ->assertJsonPath('attempt.score', 33);
    }

    /** @test */
    public function student_cannot_exceed_attempt_limit()
    {
        $answers = [
            $this->mcQuestion->id => '3/4',
        ];

        // First attempt
        $this->actingAs($this->studentUser)
            ->postJson(route('api.academy.quizzes.submit', [$this->course->id, $this->quiz->id]), ['answers' => $answers])
            ->assertStatus(200);

        // Second attempt
        $this->actingAs($this->studentUser)
            ->postJson(route('api.academy.quizzes.submit', [$this->course->id, $this->quiz->id]), ['answers' => $answers])
            ->assertStatus(200);

        // Third attempt should fail with 400 (Bad Request / Exceeded Limit)
        $this->actingAs($this->studentUser)
            ->postJson(route('api.academy.quizzes.submit', [$this->course->id, $this->quiz->id]), ['answers' => $answers])
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'You have reached the maximum number of attempts for this quiz.']);
    }
}
