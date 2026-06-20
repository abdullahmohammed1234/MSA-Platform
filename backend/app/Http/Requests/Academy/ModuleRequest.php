<?php

namespace App\Http\Requests\Academy;

use Illuminate\Foundation\Http\FormRequest;

class ModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order' => 'nullable|integer|min:0',
            'estimated_duration' => 'nullable|integer|min:1',
        ];

        if ($this->isMethod('post')) {
            $rules['course_id'] = 'required|integer|exists:courses,id';
        }

        return $rules;
    }
}
