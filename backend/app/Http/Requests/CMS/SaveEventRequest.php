<?php

namespace App\Http\Requests\CMS;

use Illuminate\Foundation\Http\FormRequest;

class SaveEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'location' => 'required|string|max:255',
            'date' => 'required|string|max:255',
            'time' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'registration_url' => 'nullable|string|max:2048',
            'image' => 'nullable|string|max:2048',
            'category' => 'required|string|max:100',
            'status' => 'required|string|in:draft,published,archived',
            'spots_left' => 'required|integer|min:0',
            'featured' => 'required|boolean',
            'registration_deadline' => 'nullable|date',
        ];
    }
}
