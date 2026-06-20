<?php

namespace App\Http\Requests\Academy;

use Illuminate\Foundation\Http\FormRequest;

class QuizRequest extends FormRequest
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
            'passing_score' => 'nullable|integer|min:0|max:100',
            'time_limit' => 'nullable|integer|min:1',
            'attempt_limit' => 'nullable|integer|min:1',
            'status' => 'nullable|string|in:draft,published',
        ];

        if ($this->isMethod('post')) {
            $rules['course_id'] = 'required|integer|exists:courses,id';
        }

        return $rules;
    }
}
