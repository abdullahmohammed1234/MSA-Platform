<?php

namespace App\Http\Requests\CMS;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', 'unique:media_categories,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter a category name.',
            'name.unique' => 'A media category with this name already exists.',
        ];
    }
}
