<?php

namespace App\Ems\Http\Resources\Public;

use App\Ems\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public list / card representation of an event.
 *
 * Intentionally omits administrative fields (ids of creators, transitions,
 * internal counters beyond capacity signalling).
 *
 * @mixin Event
 */
class PublicEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $occupied = $this->occupied_seats ?? $this->occupiedSeats();
        $capacity = $this->capacity;
        $remaining = $capacity === null ? null : max(0, $capacity - (int) $occupied);

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'banner_url' => \App\Support\CmsAssetUrl::resolve($this->banner_url),

            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'uuid' => $this->category->uuid,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
                'color' => $this->category->color,
            ] : null),

            'location' => $this->location,
            'start_at' => $this->start_at?->toIso8601String(),
            'end_at' => $this->end_at?->toIso8601String(),
            'timezone' => $this->timezone,

            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_tone' => $this->status->tone(),

            'capacity' => $capacity,
            'remaining_capacity' => $remaining,
            'is_full' => $capacity !== null && $remaining === 0,
            'is_sold_out' => $capacity !== null && $remaining === 0,
            'waitlist_enabled' => (bool) $this->waitlist_enabled,
            'is_featured' => (bool) $this->is_featured,
            'is_accepting_registrations' => $this->isAcceptingRegistrations(),

            'registration_label' => $this->registrationLabel($remaining),
        ];
    }

    private function registrationLabel(?int $remaining): string
    {
        if ($this->isAcceptingRegistrations()) {
            if ($this->capacity !== null && $remaining === 0) {
                return $this->waitlist_enabled ? 'Join Waitlist' : 'Sold Out';
            }

            return 'Registration Open';
        }

        return match ($this->status->value) {
            'published' => 'Coming Soon',
            'registration_closed' => 'Registration Closed',
            'live' => 'Happening Now',
            'completed' => 'Completed',
            default => $this->status->label(),
        };
    }
}
