<?php

namespace App\Http\Resources\Academy;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'description' => $this->description,
            'passing_score' => $this->passing_score,
            'time_limit' => $this->time_limit,
            'attempt_limit' => $this->attempt_limit,
            'status' => $this->status,
            'questions' => QuestionResource::collection($this->whenLoaded('questions')),
        ];
    }
}
