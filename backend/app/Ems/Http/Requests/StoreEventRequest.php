<?php

namespace App\Ems\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Validates event creation.
 *
 * `status` and the lifecycle timestamps are deliberately absent: a new event
 * is always a draft and only EventLifecycleService may move it on.
 */
class StoreEventRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:180'],
            'slug' => [
                'nullable',
                'string',
                'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('ems_events', 'slug')->withoutTrashed(),
            ],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'banner_url' => ['nullable', 'string', 'max:500'],
            'category_id' => ['nullable', 'integer', Rule::exists('ems_event_categories', 'id')->whereNull('deleted_at')],
            'organizer_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'organizer_name' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'waitlist_enabled' => ['nullable', 'boolean'],
            'max_tickets_per_order' => ['nullable', 'integer', 'min:1', 'max:100'],
            'max_registrations_per_attendee' => ['nullable', 'integer', 'min:1', 'max:100'],
            'registration_deadline_at' => ['nullable', 'date'],
            'is_public' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The event name field is required.',
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and single hyphens.',
            'slug.unique' => 'Another event already uses this slug.',
            'start_at.required' => 'The event start date and time is required.',
            'end_at.after' => 'The event must end after it starts.',
            'category_id.exists' => 'The selected category does not exist.',
            'organizer_id.exists' => 'The selected organizer does not exist.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->nullifyBlanks([
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
        ]));
    }
}
