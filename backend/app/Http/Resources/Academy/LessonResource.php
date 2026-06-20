<?php

namespace App\Http\Resources\Academy;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'module_id' => $this->module_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'video_url' => $this->video_url,
            'attachments' => $this->attachments,
            'order' => $this->order,
            'estimated_duration' => $this->estimated_duration,
            'is_required' => $this->is_required,
        ];
    }
}
