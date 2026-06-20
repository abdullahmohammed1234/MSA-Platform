<?php

namespace App\Repositories;

use App\Models\Quiz;
use App\Models\Question;
use App\Models\QuizAttempt;
use App\Models\Answer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class QuizRepository
{
    public function find(int $id): ?Quiz
    {
        return Quiz::with(['questions'])->find($id);
    }

    public function getByCourse(int $courseId): Collection
    {
        return Quiz::with(['questions'])->where('course_id', $courseId)->get();
    }

    public function getAllAdmin(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Quiz::with(['course:id,title,status']);

        if (isset($filters['course_id'])) {
            $query->where('course_id', $filters['course_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->latest()->paginate($perPage);
    }

    public function create(array $data): Quiz
    {
        return Quiz::create($data);
    }

    public function update(Quiz $quiz, array $data): bool
    {
        return $quiz->update($data);
    }

    public function delete(Quiz $quiz): ?bool
    {
        return $quiz->delete();
    }

    // Question Operations
    public function findQuestion(int $id): ?Question
    {
        return Question::find($id);
    }

    public function createQuestion(array $data): Question
    {
        if (!isset($data['order'])) {
            $data['order'] = Question::where('quiz_id', $data['quiz_id'])->count() + 1;
        }
        return Question::create($data);
    }

    public function updateQuestion(Question $question, array $data): bool
    {
        return $question->update($data);
    }

    public function deleteQuestion(Question $question): ?bool
    {
        return $question->delete();
    }

    // Quiz Attempt Operations
    public function createAttempt(array $data): QuizAttempt
    {
        return QuizAttempt::create($data);
    }

    public function findAttempt(int $id): ?QuizAttempt
    {
        return QuizAttempt::with(['quiz.questions', 'answers'])->find($id);
    }

    public function getBestAttempt(int $userId, int $quizId): ?QuizAttempt
    {
        return QuizAttempt::where('user_id', $userId)
            ->where('quiz_id', $quizId)
            ->orderByDesc('score')
            ->first();
    }

    public function getAttemptsCount(int $userId, int $quizId): int
    {
        return QuizAttempt::where('user_id', $userId)
            ->where('quiz_id', $quizId)
            ->count();
    }

    public function getUserAttempts(int $userId, ?int $quizId = null): Collection
    {
        $query = QuizAttempt::with(['quiz.course'])
            ->where('user_id', $userId)
            ->orderByDesc('submitted_at');

        if ($quizId !== null) {
            $query->where('quiz_id', $quizId);
        }

        return $query->get();
    }

    public function saveAnswer(array $data): Answer
    {
        return Answer::updateOrCreate(
            [
                'quiz_attempt_id' => $data['quiz_attempt_id'],
                'question_id' => $data['question_id']
            ],
            [
                'answer' => $data['answer'],
                'is_correct' => $data['is_correct'] ?? false,
                'points_awarded' => $data['points_awarded'] ?? 0
            ]
        );
    }
}
