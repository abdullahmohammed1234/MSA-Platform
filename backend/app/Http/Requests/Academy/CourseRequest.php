<?php

namespace App\Http\Requests\Academy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $course = $this->route('course');
        $courseId = $course ? (is_numeric($course) ? $course : $course->id) : null;
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');

        return [
            'title' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:255',
            'slug' => array_filter([
                $isUpdate ? 'sometimes' : 'nullable',
                'string',
                'max:255',
                Rule::unique('courses', 'slug')->ignore($courseId),
            ]),
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|string|max:255',
            'difficulty' => 'nullable|string|in:beginner,intermediate,advanced',
            'estimated_duration' => 'nullable|integer|min:1',
            'status' => 'nullable|string|in:draft,published,archived',
        ];
    }
}
