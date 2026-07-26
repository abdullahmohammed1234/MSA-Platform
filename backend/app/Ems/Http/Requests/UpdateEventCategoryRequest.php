<?php

namespace App\Ems\Http\Requests;

use App\Ems\Models\EventCategory;
use Illuminate\Validation\Rule;

class UpdateEventCategoryRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var EventCategory|null $category */
        $category = $this->route('category');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:120',
                Rule::unique('ems_event_categories', 'name')->ignore($category?->id)->withoutTrashed(),
            ],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('ems_event_categories', 'slug')->ignore($category?->id)->withoutTrashed(),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999'],
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
