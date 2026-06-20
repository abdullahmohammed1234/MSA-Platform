<?php

namespace App\Http\Requests\Academy;

use Illuminate\Foundation\Http\FormRequest;

class LessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'video_url' => 'nullable|string|max:255',
            'attachments' => 'nullable|array',
            'order' => 'nullable|integer|min:0',
            'estimated_duration' => 'nullable|integer|min:1',
            'is_required' => 'nullable|boolean',
        ];

        if ($this->isMethod('post')) {
            $rules['module_id'] = 'required|integer|exists:modules,id';
        }

        return $rules;
    }
}
