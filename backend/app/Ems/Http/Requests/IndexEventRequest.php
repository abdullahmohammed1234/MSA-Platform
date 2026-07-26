<?php

namespace App\Ems\Http\Requests;

use App\Ems\Enums\EventStatus;
use Illuminate\Validation\Rule;

/**
 * Validates the event list query string.
 */
class IndexEventRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:180'],
            'status' => ['nullable', Rule::in(EventStatus::values())],
            'category_id' => ['nullable', 'integer', 'exists:ems_event_categories,id'],
            'organizer_id' => ['nullable', 'integer', 'exists:users,id'],
            'upcoming' => ['nullable', 'boolean'],
            'starts_after' => ['nullable', 'date'],
            'starts_before' => ['nullable', 'date', 'after_or_equal:starts_after'],
            'sort_by' => ['nullable', Rule::in(['start_at', 'name', 'status', 'created_at', 'updated_at'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . config('ems.defaults.max_per_page', 100)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * The validated filters, with blanks dropped so the service only sees
     * filters the caller actually supplied.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return array_filter(
            $this->validated(),
            static fn ($value): bool => $value !== null && $value !== ''
        );
    }
}
