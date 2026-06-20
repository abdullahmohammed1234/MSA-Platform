<?php

namespace App\Http\Requests\Academy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LearningPathRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $path = $this->route('learningPath') ?? $this->route('learning_path');
        $pathId = $path ? (is_numeric($path) ? $path : $path->id) : null;

        return [
            'title' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('learning_paths', 'slug')->ignore($pathId),
            ],
            'description' => 'nullable|string',
        ];
    }
}
