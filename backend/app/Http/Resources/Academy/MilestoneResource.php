<?php

namespace App\Http\Resources\Academy;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MilestoneResource extends JsonResource
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
            'type' => $this->type,
            'threshold' => $this->threshold,
            'unlocked' => !is_null($award),
            'unlocked_at' => $award ? $award->awarded_at->toIso8601String() : null,
        ];
    }
}
