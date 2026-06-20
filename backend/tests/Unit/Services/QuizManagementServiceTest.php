<?php
# d:\projects\msa + dawah\MSA Platform\backend\tests\Unit\Services\QuizManagementServiceTest.php

namespace Tests\Unit\Services;

use App\Models\Quiz;
use App\Models\Course;
use App\Repositories\QuizRepository;
use App\Services\QuizManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected QuizManagementService $quizService;
    protected QuizRepository $quizRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quizRepository = new QuizRepository();
        $this->quizService = new QuizManagementService($this->quizRepository);
    }

    public function test_get_quizzes_by_course()
    {
        $course = Course::factory()->create();
        Quiz::factory()->create(['course_id' => $course->id]);

        $quizzes = $this->quizService->getQuizzesByCourse($course->id);

        $this->assertCount(1, $quizzes);
    }

    public function test_get_quiz()
    {
        $quiz = Quiz::factory()->create();

        $result = $this->quizService->getQuiz($quiz->id);

        $this->assertEquals($quiz->id, $result->id);
    }

    public function test_create_quiz()
    {
        $course = Course::factory()->create();
        $data = [
            'course_id' => $course->id,
            'title' => 'Final Exam',
            'description' => 'Course final assessment',
            'passing_score' => 75,
            'status' => 'draft',
        ];

        $quiz = $this->quizService->createQuiz($data);

        $this->assertInstanceOf(Quiz::class, $quiz);
        $this->assertEquals('Final Exam', $quiz->title);
        $this->assertDatabaseHas('quizzes', ['title' => 'Final Exam']);
    }

    public function test_update_quiz()
    {
        $quiz = Quiz::factory()->create(['title' => 'Midterm']);
        $data = ['title' => 'Midterm Revised'];

        $updated = $this->quizService->updateQuiz($quiz, $data);

        $this->assertTrue($updated);
        $this->assertEquals('Midterm Revised', $quiz->fresh()->title);
    }

    public function test_delete_quiz()
    {
        $quiz = Quiz::factory()->create();

        $deleted = $this->quizService->deleteQuiz($quiz);

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('quizzes', ['id' => $quiz->id]);
    }
}
