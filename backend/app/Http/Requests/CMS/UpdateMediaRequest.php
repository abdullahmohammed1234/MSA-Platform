<?php

namespace App\Http\Requests\CMS;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:media_categories,id'],
            'audience' => ['required', 'string', 'in:both,brothers,sisters,male,female'],
        ];
    }

    public function messages(): array
    {
        return [
            'audience.required' => 'The audience field is required.',
            'audience.in' => 'The selected audience must be one of: brothers, sisters, or both.',
            'category_id.exists' => 'The selected media category is invalid.',
        ];
    }
}
