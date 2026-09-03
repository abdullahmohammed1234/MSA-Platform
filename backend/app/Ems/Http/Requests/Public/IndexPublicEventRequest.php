<?php

namespace App\Ems\Http\Requests\Public;

use App\Ems\Http\Requests\EmsFormRequest;
use App\Ems\Enums\EventStatus;
use Illuminate\Validation\Rule;

class IndexPublicEventRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'category_id' => ['nullable', 'integer', 'exists:ems_event_categories,id'],
            'category_slug' => ['nullable', 'string', 'max:120'],
            'upcoming' => ['nullable', 'boolean'],
            'past' => ['nullable', 'boolean'],
            'featured' => ['nullable', 'boolean'],
            'registration_open' => ['nullable', 'boolean'],
            'registration_closed' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', Rule::in(EventStatus::values())],
            'sort_by' => ['nullable', 'string', Rule::in(['start_at', 'name'])],
            'sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $validated = $this->validated();

        foreach (['upcoming', 'past', 'featured', 'registration_open', 'registration_closed'] as $flag) {
            if (array_key_exists($flag, $validated)) {
                $validated[$flag] = filter_var($validated[$flag], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $validated;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->nullifyBlanks([
            'search',
            'category_id',
            'category_slug',
            'status',
            'sort_by',
            'sort_direction',
        ]));

        $this->merge($this->coerceBooleanFlags([
            'upcoming',
            'past',
            'featured',
            'registration_open',
            'registration_closed',
        ]));
    }
}
