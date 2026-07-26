<?php

namespace App\Ems\Http\Requests\Public;

use App\Ems\Http\Requests\EmsFormRequest;

class CalendarPublicEventRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'starts_after' => ['nullable', 'date'],
            'starts_before' => ['nullable', 'date', 'after_or_equal:starts_after'],
            'category_id' => ['nullable', 'integer', 'exists:ems_event_categories,id'],
            'category_slug' => ['nullable', 'string', 'max:120'],
            'upcoming' => ['nullable', 'boolean'],
            'past' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $validated = $this->validated();

        foreach (['upcoming', 'past'] as $flag) {
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
        ]));

        $this->merge($this->coerceBooleanFlags([
            'upcoming',
            'past',
        ]));
    }
}
