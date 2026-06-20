<?php

namespace App\Http\Resources\Academy;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canSeeAnswer = $user && ($user->hasPermission('manage_quizzes') || $user->hasPermission('manage_courses'));

        return [
            'id' => $this->id,
            'quiz_id' => $this->quiz_id,
            'type' => $this->type,
            'question' => $this->question,
            'options' => $this->options,
            'points' => $this->points,
            'order' => $this->order,
            'correct_answer' => $this->when($canSeeAnswer, $this->correct_answer),
        ];
    }
}
