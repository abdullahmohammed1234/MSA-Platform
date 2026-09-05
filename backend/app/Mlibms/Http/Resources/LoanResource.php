<?php

namespace App\Mlibms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'copy' => new CopyResource($this->whenLoaded('copy')),
            'member' => new MemberResource($this->whenLoaded('member')),
            'checked_out_at' => $this->checked_out_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'returned_at' => $this->returned_at?->toIso8601String(),
            'renewed_count' => $this->renewed_count,
            'last_renewed_at' => $this->last_renewed_at?->toIso8601String(),
            'reminder_sent_at' => $this->reminder_sent_at?->toIso8601String(),
            'status' => $this->status?->value ?? $this->status,
            'status_label' => $this->status?->label() ?? ucfirst((string) $this->status),
            'is_overdue' => $this->isOverdue(),
        ];
    }
}
