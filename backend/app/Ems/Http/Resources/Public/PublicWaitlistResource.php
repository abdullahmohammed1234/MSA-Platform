<?php

namespace App\Ems\Http\Resources\Public;

use App\Ems\Models\WaitlistEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WaitlistEntry */
class PublicWaitlistResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'position' => $this->position,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'quantity' => $this->quantity,
            'attendee_name' => $this->attendee_name,
            'attendee_email' => $this->attendee_email,
            'registration' => $this->whenLoaded('registration', fn () => $this->registration ? [
                'uuid' => $this->registration->uuid,
                'reference' => $this->registration->reference,
                'status' => $this->registration->status->value,
                'status_label' => $this->registration->status->label(),
            ] : null),
        ];
    }
}
