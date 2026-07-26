<?php

namespace App\Ems\Http\Resources\Public;

use App\Ems\Models\EventCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventCategory
 */
class PublicCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'color' => $this->color,
            'sort_order' => (int) $this->sort_order,
            'events_count' => $this->when(
                isset($this->public_events_count),
                (int) $this->public_events_count
            ),
        ];
    }
}
