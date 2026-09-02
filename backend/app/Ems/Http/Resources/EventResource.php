<?php

namespace App\Ems\Http\Resources;

use App\Ems\Enums\EventTransition;
use App\Ems\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_slug_custom' => (bool) $this->is_slug_custom,
            'slug_mode' => $this->is_slug_custom ? 'manual' : 'auto',
            'short_description' => $this->short_description,
            'description' => $this->description,
            'banner_url' => \App\Support\CmsAssetUrl::resolve($this->banner_url),

            'category_id' => $this->category_id,
            'category' => new EventCategoryResource($this->whenLoaded('category')),

            'organizer_id' => $this->organizer_id,
            'organizer_name' => $this->organizer_name,
            'display_organizer_name' => $this->organizer_name ?: ($this->relationLoaded('organizer') ? $this->organizer?->name : null),
            'organizer' => new EmsUserSummaryResource($this->whenLoaded('organizer')),

            'location' => $this->location,
            'start_at' => $this->start_at?->toIso8601String(),
            'end_at' => $this->end_at?->toIso8601String(),
            'timezone' => $this->timezone,
            'capacity' => $this->capacity,
            'waitlist_enabled' => (bool) $this->waitlist_enabled,
            'max_tickets_per_order' => $this->max_tickets_per_order,
            'max_registrations_per_attendee' => $this->max_registrations_per_attendee,
            'registration_deadline_at' => $this->registration_deadline_at?->toIso8601String(),

            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_tone' => $this->status->tone(),
            'is_public' => (bool) $this->is_public,
            'is_publicly_visible' => $this->status->isPubliclyVisible(),
            'is_accepting_registrations' => $this->isAcceptingRegistrations(),

            'published_at' => $this->published_at?->toIso8601String(),
            'registration_open_at' => $this->registration_open_at?->toIso8601String(),
            'registration_closed_at' => $this->registration_closed_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,

            // Every transition legal from this state, each flagged with whether
            // the current viewer may perform it. The UI renders buttons from
            // this rather than reimplementing the state machine.
            'available_transitions' => array_map(
                fn (EventTransition $transition): array => [
                    'action' => $transition->value,
                    'label' => $transition->label(),
                    'to' => $transition->toStatus()->value,
                    'to_label' => $transition->toStatus()->label(),
                    'confirmation' => $transition->confirmation(),
                    'irreversible' => $transition->isIrreversible(),
                    'permitted' => $user !== null && $user->hasPermission($transition->permission()),
                ],
                $this->availableTransitions()
            ),

            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'creator' => new EmsUserSummaryResource($this->whenLoaded('creator')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Counts are only present when the caller asked for them, keeping
            // the list endpoint free of N+1 aggregates.
            'registrations_count' => $this->whenCounted('registrations'),
        ];
    }
}
