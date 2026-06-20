<?php

namespace App\Http\Resources\Academy;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'quiz_id' => $this->quiz_id,
            'score' => $this->score,
            'passed' => $this->passed,
            'started_at' => $this->started_at->toIso8601String(),
            'submitted_at' => $this->submitted_at ? $this->submitted_at->toIso8601String() : null,
            'quiz' => $this->whenLoaded('quiz', function () {
                return [
                    'id' => $this->quiz->id,
                    'title' => $this->quiz->title,
                    'course_id' => $this->quiz->course_id,
                    'course_title' => $this->quiz->relationLoaded('course') && $this->quiz->course
                        ? $this->quiz->course->title
                        : null,
                ];
            }),
            'answers' => $this->whenLoaded('answers', function () {
                return $this->answers->map(function ($ans) {
                    return [
                        'id' => $ans->id,
                        'question_id' => $ans->question_id,
                        'answer' => $ans->answer,
                        'is_correct' => $ans->is_correct,
                        'points_awarded' => $ans->points_awarded,
                    ];
                });
            }),
        ];
    }
}
