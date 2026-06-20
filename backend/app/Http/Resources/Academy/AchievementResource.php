<?php

namespace App\Http\Resources\Academy;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AchievementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $award = $this->relationLoaded('awards') ? $this->awards->first() : null;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'type' => $this->type,
            'points' => $this->points,
            'criteria_type' => $this->criteria_type,
            'criteria_value' => $this->criteria_value,
            'unlocked' => !is_null($award),
            'unlocked_at' => $award ? $award->unlocked_at->toIso8601String() : null,
        ];
    }
}
