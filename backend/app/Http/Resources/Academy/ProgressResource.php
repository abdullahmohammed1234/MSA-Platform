<?php

namespace App\Http\Resources\Academy;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'course_id' => $this->course_id,
            'lesson_id' => $this->lesson_id,
            'completion_percentage' => $this->completion_percentage,
            'completed' => $this->completed,
            'completed_at' => $this->completed_at ? $this->completed_at->toIso8601String() : null,
        ];
    }
}
