<?php

namespace App\Http\Resources\Academy;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BadgeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $award = $this->relationLoaded('awards') ? $this->awards->first() : null;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'criteria_type' => $this->criteria_type,
            'criteria_value' => $this->criteria_value,
            'unlocked' => !is_null($award),
            'unlocked_at' => $award ? $award->awarded_at->toIso8601String() : null,
        ];
    }
}
