<?php

namespace App\Http\Requests\Academy;

use Illuminate\Foundation\Http\FormRequest;

class SubmitQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answers' => 'required|array',
            'answers.*' => 'nullable', // Each question can have single or multiple option values
        ];
    }
}
