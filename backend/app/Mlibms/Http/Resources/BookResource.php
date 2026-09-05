<?php

namespace App\Mlibms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'slug' => $this->slug,
            'subtitle' => $this->subtitle,
            'isbn_10' => $this->isbn_10,
            'isbn_13' => $this->isbn_13,
            'edition' => $this->edition,
            'publication_year' => $this->publication_year,
            'language' => $this->language,
            'summary' => $this->summary,
            'cover_image_url' => $this->cover_image_url,
            'default_loan_days' => $this->default_loan_days ?? 14,
            'is_reference_only' => (bool) $this->is_reference_only,
            'category' => new CategoryResource($this->whenLoaded('primaryCategory')),
            'publisher' => $this->whenLoaded('publisher', fn() => [
                'id' => $this->publisher->id,
                'name' => $this->publisher->name,
            ]),
            'authors' => $this->whenLoaded('authors', fn() => $this->authors->map(fn($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'role' => $a->pivot->role ?? 'author',
            ])),
            'total_copies_count' => $this->total_copies_count ?? $this->copies_count ?? $this->copies()->count(),
            'available_copies_count' => $this->available_copies_count ?? $this->availableCopies()->count(),
            'copies' => CopyResource::collection($this->whenLoaded('copies')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
