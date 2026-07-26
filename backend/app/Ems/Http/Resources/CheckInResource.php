<?php

namespace App\Ems\Http\Resources;

use App\Ems\Models\CheckIn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CheckIn
 */
class CheckInResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'event_id' => $this->event_id,
            'ticket_code' => $this->ticket?->code,
            'ticket_uuid' => $this->ticket?->uuid,
            'registration_uuid' => $this->registration?->uuid,
            'attendee_name' => $this->registration?->attendee_name ?? $this->ticket?->holder_name,
            'attendee_email' => $this->registration?->attendee_email ?? $this->ticket?->holder_email,
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'device' => $this->device,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'staff_name' => $this->checkedInBy?->name,
            'notes' => $this->notes,
        ];
    }
}
