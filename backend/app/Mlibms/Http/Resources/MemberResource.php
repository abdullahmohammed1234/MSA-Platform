<?php

namespace App\Mlibms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'user_id' => $this->user_id,
            'library_card_number' => $this->library_card_number,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'membership_type' => $this->membership_type?->value ?? $this->membership_type,
            'membership_type_label' => $this->membership_type?->label() ?? ucfirst((string) $this->membership_type),
            'status' => $this->status,
            'max_active_loans' => $this->max_active_loans,
            'registered_at' => $this->registered_at?->toIso8601String(),
            'suspended_at' => $this->suspended_at?->toIso8601String(),
            'suspension_reason' => $this->suspension_reason,
            'active_loans_count' => $this->activeLoans()->count(),
            'is_guest' => $this->isGuest(),
        ];
    }
}
