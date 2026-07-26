<?php

namespace App\Ems\Http\Resources\Public;

use App\Ems\Models\Event;
use Illuminate\Http\Request;

/**
 * Full public landing-page payload for a single event.
 *
 * @mixin Event
 */
class PublicEventDetailResource extends PublicEventResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $base */
        $base = parent::toArray($request);

        return array_merge($base, [
            'description' => $this->description,
            'organizer' => [
                'name' => $this->organizer_name ?? ($this->relationLoaded('organizer') && $this->organizer ? $this->organizer->name : config('ems.notifications.from_name', 'SFU MSA Events')),
            ],
            'published_at' => $this->published_at?->toIso8601String(),
            'registration_open_at' => $this->registration_open_at?->toIso8601String(),
            'registration_closed_at' => $this->registration_closed_at?->toIso8601String(),
            'registration_deadline_at' => $this->registration_deadline_at?->toIso8601String(),
            'waitlist_enabled' => (bool) $this->waitlist_enabled,
            'max_tickets_per_order' => $this->max_tickets_per_order,
            'max_registrations_per_attendee' => $this->max_registrations_per_attendee,
            'payments_enabled' => (bool) config('ems.payments.enabled', false),
            'ticket_types' => PublicTicketTypeResource::collection(
                $this->whenLoaded('ticketTypes')
            ),
        ]);
    }
}
