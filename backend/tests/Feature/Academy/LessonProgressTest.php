<?php

namespace Tests\Feature\Academy;

use App\Models\Course;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Enrollment;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LessonProgressTest extends TestCase
{
    use RefreshDatabase;

    protected $student;
    protected $course;
    protected $module;
    protected $lesson1;
    protected $lesson2;
    protected $quiz;
    protected $question;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create student user
        $this->student = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        // 2. Create course
        $this->course = Course::create([
            'title' => 'Dawah Academy Basics',
            'slug' => 'dawah-basics',
            'status' => 'published',
        ]);

        // 3. Create module
        $this->module = Module::create([
            'course_id' => $this->course->id,
            'title' => 'First Module',
            'order' => 1,
        ]);

        // 4. Create two required lessons
        $this->lesson1 = Lesson::create([
            'module_id' => $this->module->id,
            'title' => 'Introduction to Faith',
            'slug' => 'intro-to-faith',
            'is_required' => true,
            'order' => 1,
        ]);

        $this->lesson2 = Lesson::create([
            'module_id' => $this->module->id,
            'title' => 'Methodologies of Dawah',
            'slug' => 'method-of-dawah',
            'is_required' => true,
            'order' => 2,
        ]);

        // 5. Create a quiz
        $this->quiz = Quiz::create([
            'course_id' => $this->course->id,
            'title' => 'Final Exam',
            'passing_score' => 100,
            'status' => 'published',
        ]);

        $this->question = Question::create([
            'quiz_id' => $this->quiz->id,
            'type' => 'true_false',
            'question' => 'Is dawah mandatory?',
            'options' => ['true', 'false'],
            'correct_answer' => ['true'],
            'points' => 1,
            'order' => 1,
        ]);
    }

    /** @test */
    public function student_can_enroll_in_course()
    {
        $response = $this->actingAs($this->student)
            ->postJson(route('api.academy.courses.enroll', $this->course->id))
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function completing_lessons_recalculates_progress_percentage()
    {
        // 1. Enroll
        Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'active',
        ]);

        // 2. Complete Lesson 1 (should result in 50% course progress)
        $response = $this->actingAs($this->student)
            ->postJson(route('api.academy.lessons.complete', [$this->course->id, $this->lesson1->id]))
            ->assertStatus(200)
            ->assertJsonPath('completion_percentage', 50)
            ->assertJsonPath('course_completed', false);

        $this->assertDatabaseHas('progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson1->id,
            'completed' => true,
        ]);

        // 3. Complete Lesson 2 (should result in 100% course progress, but not completed because quiz is not passed)
        $response = $this->actingAs($this->student)
            ->postJson(route('api.academy.lessons.complete', [$this->course->id, $this->lesson2->id]))
            ->assertStatus(200)
            ->assertJsonPath('completion_percentage', 100)
            ->assertJsonPath('course_completed', false);
    }

    /** @test */
    public function passing_quiz_after_completing_lessons_triggers_automatic_completion()
    {
        // 1. Enroll and complete all required lessons
        Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->student)
            ->postJson(route('api.academy.lessons.complete', [$this->course->id, $this->lesson1->id]))
            ->assertStatus(200);

        $this->actingAs($this->student)
            ->postJson(route('api.academy.lessons.complete', [$this->course->id, $this->lesson2->id]))
            ->assertStatus(200);

        // 2. Submit passing quiz attempt (correct answer: 'true')
        $response = $this->actingAs($this->student)
            ->postJson(route('api.academy.quizzes.submit', [$this->course->id, $this->quiz->id]), [
                'answers' => [
                    $this->question->id => 'true',
                ]
            ])
            ->assertStatus(200)
            ->assertJsonPath('attempt.passed', true)
            ->assertJsonPath('course_completed', true);

        // 3. Check enrollment is status = completed
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'completed',
        ]);
    }
}
