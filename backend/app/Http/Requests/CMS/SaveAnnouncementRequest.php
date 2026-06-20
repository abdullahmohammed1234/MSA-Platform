<?php

namespace App\Http\Requests\CMS;

use Illuminate\Foundation\Http\FormRequest;

class SaveAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'summary' => 'nullable|string',
            'featured_image' => 'nullable|string',
            'status' => 'required|string|in:draft,published,archived',
        ];
    }
}
