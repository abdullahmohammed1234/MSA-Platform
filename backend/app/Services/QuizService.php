<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\Question;
use App\Models\QuizAttempt;
use App\Repositories\QuizRepository;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class QuizService
{
    protected $quizRepository;

    public function __construct(QuizRepository $quizRepository)
    {
        $this->quizRepository = $quizRepository;
    }

    public function getQuiz(int $id): ?Quiz
    {
        return $this->quizRepository->find($id);
    }

    public function getCourseQuizzes(int $courseId): Collection
    {
        return $this->quizRepository->getByCourse($courseId);
    }

    public function getAdminQuizzes(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->quizRepository->getAllAdmin($filters, $perPage);
    }

    public function createQuiz(array $data): Quiz
    {
        return $this->quizRepository->create($data);
    }

    public function updateQuiz(Quiz $quiz, array $data): bool
    {
        return $this->quizRepository->update($quiz, $data);
    }

    public function deleteQuiz(Quiz $quiz): ?bool
    {
        return $this->quizRepository->delete($quiz);
    }

    // Question Management
    public function getQuestion(int $id): ?Question
    {
        return $this->quizRepository->findQuestion($id);
    }

    public function createQuestion(array $data): Question
    {
        return $this->quizRepository->createQuestion($data);
    }

    public function updateQuestion(Question $question, array $data): bool
    {
        return $this->quizRepository->updateQuestion($question, $data);
    }

    public function deleteQuestion(Question $question): ?bool
    {
        return $this->quizRepository->deleteQuestion($question);
    }

    /**
     * Submit and grade a quiz attempt.
     */
    public function submitAttempt(int $userId, int $quizId, array $userAnswers): QuizAttempt
    {
        $quiz = $this->quizRepository->find($quizId);
        if (!$quiz) {
            throw new Exception("Quiz not found.");
        }

        // 1. Verify Attempt Limit
        if ($quiz->attempt_limit !== null) {
            $attemptsCount = $this->quizRepository->getAttemptsCount($userId, $quizId);
            if ($attemptsCount >= $quiz->attempt_limit) {
                throw new Exception("You have reached the maximum number of attempts for this quiz.");
            }
        }

        // 2. Create the Attempt (Pending Submission)
        $attempt = $this->quizRepository->createAttempt([
            'user_id' => $userId,
            'quiz_id' => $quizId,
            'score' => 0,
            'passed' => false,
            'started_at' => now()->subMinutes(5), // Assume started recently
            'submitted_at' => now(),
        ]);

        $totalPoints = 0;
        $earnedPoints = 0;
        $gradedAnswers = [];

        // 3. Score each question
        foreach ($quiz->questions as $question) {
            $totalPoints += $question->points;
            $userAnsVal = $userAnswers[$question->id] ?? null;

            $isCorrect = $this->gradeQuestion($question, $userAnsVal);
            $pointsAwarded = $isCorrect ? $question->points : 0;
            $earnedPoints += $pointsAwarded;

            $gradedAnswers[] = [
                'quiz_attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'answer' => $userAnsVal !== null ? (is_array($userAnsVal) ? $userAnsVal : [$userAnsVal]) : [],
                'is_correct' => $isCorrect,
                'points_awarded' => $pointsAwarded
            ];
        }

        // 4. Save graded answers
        foreach ($gradedAnswers as $ansData) {
            $this->quizRepository->saveAnswer($ansData);
        }

        // 5. Calculate final score (percentage)
        $finalScore = $totalPoints > 0 ? (int) round(($earnedPoints / $totalPoints) * 100) : 100;
        $passed = $finalScore >= $quiz->passing_score;

        // 6. Update Attempt
        $attempt->update([
            'score' => $finalScore,
            'passed' => $passed,
            'submitted_at' => now()
        ]);

        return $attempt->load('answers');
    }

    /**
     * Helper to grade a single question.
     */
    protected function gradeQuestion(Question $question, mixed $userAnswer): bool
    {
        if ($userAnswer === null) {
            return false;
        }

        $correctAnswer = $question->correct_answer; // Decrypted array from model

        if (!is_array($correctAnswer)) {
            $correctAnswer = [$correctAnswer];
        }

        switch ($question->type) {
            case 'multiple_choice':
            case 'true_false':
                $userVal = is_array($userAnswer) ? ($userAnswer[0] ?? null) : $userAnswer;
                $correctVal = $correctAnswer[0] ?? null;
                return strval($userVal) === strval($correctVal);

            case 'multiple_select':
                if (!is_array($userAnswer)) {
                    $userAnswer = [$userAnswer];
                }
                // Sort arrays to compare values exactly
                $userSorted = array_map('strval', $userAnswer);
                $correctSorted = array_map('strval', $correctAnswer);
                sort($userSorted);
                sort($correctSorted);
                return $userSorted === $correctSorted;

            case 'short_answer':
                $userText = strtolower(trim(is_array($userAnswer) ? ($userAnswer[0] ?? '') : $userAnswer));
                // Match against list of acceptable answers
                foreach ($correctAnswer as $correctVal) {
                    if ($userText === strtolower(trim(strval($correctVal)))) {
                        return true;
                    }
                }
                return false;

            default:
                return false;
        }
    }
}
