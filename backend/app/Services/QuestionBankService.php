<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Answer;
use Illuminate\Pagination\LengthAwarePaginator;

class QuestionBankService
{
    public function getQuestions(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Question::with(['quiz.course']);

        if (!empty($filters['search'])) {
            $query->where('question', 'like', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['difficulty'])) {
            $query->where('difficulty', $filters['difficulty']);
        }

        if (!empty($filters['quiz_id'])) {
            $query->where('quiz_id', $filters['quiz_id']);
        }

        return $query->latest()->paginate($perPage);
    }

    public function getQuestion(int $id): ?Question
    {
        return Question::with(['quiz.course'])->find($id);
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

    public function deleteQuestion(Question $question): bool
    {
        return $question->delete();
    }

    public function getQuestionUsage(Question $question): array
    {
        $attemptsCount = Answer::where('question_id', $question->id)->count();
        $correctCount = Answer::where('question_id', $question->id)->where('is_correct', true)->count();
        
        return [
            'attempts_count' => $attemptsCount,
            'correct_count' => $correctCount,
            'success_rate' => $attemptsCount > 0 ? round(($correctCount / $attemptsCount) * 100, 2) : 0
        ];
    }
}
