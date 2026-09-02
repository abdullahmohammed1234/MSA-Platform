<?php

namespace App\Ems\Http\Requests;

use App\Ems\Models\Event;
use Illuminate\Validation\Rule;

/**
 * Validates event edits.
 *
 * Fields are `sometimes` so a partial payload only touches what it names, and
 * `status` remains unsettable — lifecycle changes go through
 * POST /events/{event}/transitions.
 */
class UpdateEventRequest extends EmsFormRequest
{
    /** Whether the client actually sent a start date, before any stand-in. */
    private bool $startAtSubmitted = false;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Event $event */
        $event = $this->route('event');

        return [
            'name' => ['sometimes', 'required', 'string', 'min:3', 'max:180'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('ems_events', 'slug')->ignore($event?->id)->withoutTrashed(),
                Rule::notIn(['admin', 'login', 'register', 'events', 'api', 'dashboard', 'calendar', 'checkout', 'tickets', 'my-tickets', 'categories', 'notifications', 'templates']),
            ],
            'slug_mode' => ['sometimes', 'nullable', 'string', Rule::in(['auto', 'manual'])],
            'reset_slug' => ['sometimes', 'nullable', 'boolean'],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'banner_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('ems_event_categories', 'id')->whereNull('deleted_at')],
            'organizer_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'organizer_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'start_at' => ['sometimes', 'required', 'date'],
            'end_at' => ['sometimes', 'nullable', 'date', 'after:start_at'],
            'timezone' => ['sometimes', 'nullable', 'string', 'timezone:all'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000000'],
            'waitlist_enabled' => ['sometimes', 'boolean'],
            'max_tickets_per_order' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'max_registrations_per_attendee' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'registration_deadline_at' => ['sometimes', 'nullable', 'date'],
            'is_public' => ['sometimes', 'boolean'],
            'notify_audience' => ['sometimes', 'nullable', 'string', Rule::in(['everyone', 'registered', 'ticket_holders', 'none'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and single hyphens.',
            'slug.unique' => 'Another event already uses this slug.',
            'slug.not_in' => 'This slug is reserved and cannot be used.',
            'end_at.after' => 'The event must end after it starts.',
        ];
    }

    /**
     * `end_at` is validated against `start_at`, so when only one of the pair is
     * submitted the stored value has to stand in for the other.
     */
    protected function prepareForValidation(): void
    {
        /** @var Event|null $event */
        $event = $this->route('event');

        $this->startAtSubmitted = $this->has('start_at');

        $merge = $this->nullifyBlanks([
            'slug',
            'short_description',
            'description',
            'banner_url',
            'location',
            'end_at',
            'category_id',
            'organizer_id',
            'organizer_name',
            'capacity',
            'timezone',
            'max_tickets_per_order',
            'max_registrations_per_attendee',
            'registration_deadline_at',
        ]);

        if (isset($merge['slug']) && is_string($merge['slug'])) {
            $merge['slug'] = \Illuminate\Support\Str::slug($merge['slug']);
        }

        if ($event && $this->has('end_at') && ! $this->startAtSubmitted) {
            $merge['start_at'] = $event->start_at?->toDateTimeString();
        }

        $this->merge($merge);
    }

    /**
     * Drop the stand-in `start_at` so an untouched start date is not rewritten.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $validated = $this->validated();

        if (! $this->startAtSubmitted) {
            unset($validated['start_at']);
        }

        return $validated;
    }
}
