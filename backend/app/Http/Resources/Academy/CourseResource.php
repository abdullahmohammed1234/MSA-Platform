<?php

namespace App\Http\Resources\Academy;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail,
            'difficulty' => $this->difficulty,
            'estimated_duration' => $this->estimated_duration,
            'status' => $this->status,
            'published_at' => $this->published_at ? $this->published_at->toIso8601String() : null,
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ];
            }),
            'modules' => ModuleResource::collection($this->whenLoaded('modules')),
            'quizzes' => QuizResource::collection($this->whenLoaded('quizzes')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
