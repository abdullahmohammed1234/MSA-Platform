<?php

namespace App\Services;

use App\Models\Quiz;
use App\Repositories\QuizRepository;
use Illuminate\Database\Eloquent\Collection;

class QuizManagementService
{
    protected $quizRepository;

    public function __construct(QuizRepository $quizRepository)
    {
        $this->quizRepository = $quizRepository;
    }

    public function getQuizzesByCourse(int $courseId): Collection
    {
        return $this->quizRepository->getByCourse($courseId);
    }

    public function getQuiz(int $id): ?Quiz
    {
        return $this->quizRepository->find($id);
    }

    public function createQuiz(array $data): Quiz
    {
        return $this->quizRepository->create($data);
    }

    public function updateQuiz(Quiz $quiz, array $data): bool
    {
        return $this->quizRepository->update($quiz, $data);
    }

    public function deleteQuiz(Quiz $quiz): bool
    {
        return $this->quizRepository->delete($quiz);
    }
}
