<?php

namespace App\Ems\Http\Requests;

use Illuminate\Validation\Rule;

class StoreEventCategoryRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:120',
                Rule::unique('ems_event_categories', 'name')->withoutTrashed(),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('ems_event_categories', 'slug')->withoutTrashed(),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A category with this name already exists.',
            'color.regex' => 'The colour must be a six-digit hex value, for example #640c0e.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->nullifyBlanks(['slug', 'description', 'color', 'sort_order']));
    }
}
