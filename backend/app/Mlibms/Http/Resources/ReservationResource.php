<?php

namespace App\Mlibms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'book' => new BookResource($this->whenLoaded('book')),
            'copy' => new CopyResource($this->whenLoaded('copy')),
            'member' => new MemberResource($this->whenLoaded('member')),
            'status' => $this->status?->value ?? $this->status,
            'status_label' => $this->status?->label() ?? ucfirst((string) $this->status),
            'queue_position' => $this->queue_position,
            'reserved_at' => $this->reserved_at?->toIso8601String(),
            'ready_at' => $this->ready_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'fulfilled_at' => $this->fulfilled_at?->toIso8601String(),
        ];
    }
}
