<?php

namespace Tests\Feature\Security;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AcademyCourseBindingTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private Course $enrolledCourse;
    private Course $otherCourse;
    private Lesson $ownLesson;
    private Lesson $foreignLesson;
    private Quiz $ownQuiz;
    private Quiz $foreignQuiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = $this->learner('student@example.com');

        $this->enrolledCourse = Course::create(['title' => 'Enrolled', 'slug' => 'enrolled', 'status' => 'published']);
        $this->otherCourse = Course::create(['title' => 'Draft Course', 'slug' => 'draft-course', 'status' => 'draft']);

        $this->ownLesson = $this->lessonIn($this->enrolledCourse, 'own-lesson');
        $this->foreignLesson = $this->lessonIn($this->otherCourse, 'foreign-lesson');

        $this->ownQuiz = $this->quizIn($this->enrolledCourse, 'published');
        $this->foreignQuiz = $this->quizIn($this->otherCourse, 'published');

        Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->enrolledCourse->id,
            'status' => 'active',
        ]);
    }

    private function learner(string $email): User
    {
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Learner',
            'email' => $email,
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $this->assignVolunteerRole($user);

        return $user;
    }

    private function lessonIn(Course $course, string $slug): Lesson
    {
        $module = Module::create(['course_id' => $course->id, 'title' => 'Module '.$slug, 'order' => 1]);

        return Lesson::create([
            'module_id' => $module->id,
            'title' => 'Lesson '.$slug,
            'slug' => $slug,
            'is_required' => true,
            'order' => 1,
        ]);
    }

    private function quizIn(Course $course, string $status): Quiz
    {
        $quiz = Quiz::create([
            'course_id' => $course->id,
            'title' => 'Quiz '.$status.' '.$course->slug,
            'passing_score' => 100,
            'status' => $status,
        ]);
        Question::create([
            'quiz_id' => $quiz->id,
            'type' => 'true_false',
            'question' => 'Is this true?',
            'options' => ['true', 'false'],
            'correct_answer' => ['true'],
            'points' => 1,
            'order' => 1,
        ]);

        return $quiz;
    }

    private function answersFor(Quiz $quiz): array
    {
        return ['answers' => [$quiz->questions()->first()->id => 'true']];
    }

    public function test_lesson_from_another_course_cannot_be_completed_through_an_enrolled_course(): void
    {
        $this->actingAs($this->student)
            ->postJson(route('api.academy.lessons.complete', [$this->enrolledCourse->id, $this->foreignLesson->id]))
            ->assertNotFound();

        $this->assertDatabaseMissing('progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $this->foreignLesson->id,
        ]);
    }

    public function test_quiz_from_another_course_cannot_be_submitted_through_an_enrolled_course(): void
    {
        $this->actingAs($this->student)
            ->postJson(route('api.academy.quizzes.submit', [$this->enrolledCourse->id, $this->foreignQuiz->id]), $this->answersFor($this->foreignQuiz))
            ->assertNotFound();

        $this->assertDatabaseMissing('quiz_attempts', [
            'user_id' => $this->student->id,
            'quiz_id' => $this->foreignQuiz->id,
        ]);
    }

    public function test_unpublished_quiz_in_the_enrolled_course_cannot_be_submitted(): void
    {
        $draftQuiz = $this->quizIn($this->enrolledCourse, 'draft');

        $this->actingAs($this->student)
            ->postJson(route('api.academy.quizzes.submit', [$this->enrolledCourse->id, $draftQuiz->id]), $this->answersFor($draftQuiz))
            ->assertNotFound();

        $this->assertDatabaseMissing('quiz_attempts', ['quiz_id' => $draftQuiz->id]);
    }

    public function test_enrolled_student_can_still_complete_own_lessons_and_quizzes(): void
    {
        $this->actingAs($this->student)
            ->postJson(route('api.academy.lessons.complete', [$this->enrolledCourse->id, $this->ownLesson->id]))
            ->assertOk()
            ->assertJsonPath('completion_percentage', 100);

        $this->actingAs($this->student)
            ->postJson(route('api.academy.quizzes.submit', [$this->enrolledCourse->id, $this->ownQuiz->id]), $this->answersFor($this->ownQuiz))
            ->assertOk()
            ->assertJsonPath('attempt.passed', true)
            ->assertJsonPath('course_completed', true);
    }

    public function test_student_cannot_view_a_draft_course(): void
    {
        $this->actingAs($this->student)
            ->getJson(route('api.academy.courses.show', $this->otherCourse->id))
            ->assertForbidden();
    }

    public function test_quiz_attempt_history_only_contains_the_callers_attempts(): void
    {
        $other = $this->learner('other@example.com');
        Enrollment::create(['user_id' => $other->id, 'course_id' => $this->enrolledCourse->id, 'status' => 'active']);

        $this->actingAs($other)
            ->postJson(route('api.academy.quizzes.submit', [$this->enrolledCourse->id, $this->ownQuiz->id]), $this->answersFor($this->ownQuiz))
            ->assertOk();

        $this->actingAs($this->student)
            ->getJson(route('api.academy.quiz-attempts').'?quiz_id='.$this->ownQuiz->id)
            ->assertOk()
            ->assertJsonCount(0, 'attempts');
    }
}
