<?php

namespace App\Http\Requests\Academy;

use Illuminate\Foundation\Http\FormRequest;

class QuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'type' => 'required|string|in:multiple_choice,multiple_select,true_false,short_answer',
            'question' => 'required|string',
            'options' => 'nullable|array',
            'correct_answer' => 'required|array', // Structured representation of the correct answer
            'points' => 'nullable|integer|min:0',
            'order' => 'nullable|integer|min:0',
        ];

        if ($this->isMethod('post')) {
            $rules['quiz_id'] = 'required|integer|exists:quizzes,id';
        }

        return $rules;
    }
}
