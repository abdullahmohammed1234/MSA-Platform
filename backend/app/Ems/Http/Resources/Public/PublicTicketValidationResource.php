<?php

namespace App\Ems\Http\Resources\Public;

use App\Ems\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ticket validation response — confirms existence and status without exposing
 * private attendee contact details beyond what a scanner needs.
 *
 * @mixin Ticket
 */
class PublicTicketValidationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'valid' => true,
            'code' => $this->code,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'holder_name' => $this->holder_name,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'event' => $this->whenLoaded('event', fn () => [
                'uuid' => $this->event->uuid,
                'name' => $this->event->name,
                'slug' => $this->event->slug,
                'start_at' => $this->event->start_at?->toIso8601String(),
                'location' => $this->event->location,
            ]),
            // Explicitly omitted: holder_email, phone, notes, student_id, payments.
        ];
    }
}
