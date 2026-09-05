<?php

namespace App\Mlibms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CopyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'book_id' => $this->book_id,
            'barcode' => $this->barcode,
            'accession_number' => $this->accession_number,
            'condition' => $this->condition?->value ?? $this->condition,
            'condition_label' => $this->condition?->label() ?? ucfirst((string) $this->condition),
            'status' => $this->status?->value ?? $this->status,
            'status_label' => $this->status?->label() ?? ucfirst((string) $this->status),
            'location' => $this->whenLoaded('location', fn() => [
                'id' => $this->location->id,
                'name' => $this->location->name,
                'code' => $this->location->code,
                'shelf_identifier' => $this->location->shelf_identifier,
            ]),
            'book' => new BookResource($this->whenLoaded('book')),
            'acquisition_date' => $this->acquisition_date?->toDateString(),
            'notes' => $this->notes,
        ];
    }
}
