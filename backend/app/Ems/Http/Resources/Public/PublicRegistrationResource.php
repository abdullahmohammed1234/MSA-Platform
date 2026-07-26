<?php

namespace App\Ems\Http\Resources\Public;

use App\Ems\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Confirmation payload after a successful free registration.
 *
 * @mixin Registration
 */
class PublicRegistrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'status_label' => $this->status->value === 'confirmed' ? 'Registered' : $this->status->label(),
            'type' => $this->type->value,
            'attendee_name' => $this->attendee_name,
            'attendee_email' => $this->attendee_email,
            'quantity' => $this->quantity,
            'registered_at' => $this->registered_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),

            'event' => $this->whenLoaded('event', fn () => [
                'uuid' => $this->event->uuid,
                'name' => $this->event->name,
                'slug' => $this->event->slug,
                'start_at' => $this->event->start_at?->toIso8601String(),
                'location' => $this->event->location,
            ]),

            'tickets' => PublicTicketResource::collection($this->whenLoaded('tickets')),
        ];
    }
}
