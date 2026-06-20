<?php

namespace App\Http\Requests\CMS;

use Illuminate\Foundation\Http\FormRequest;

class SaveResourceRequest extends FormRequest
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
            'category' => 'required|string|max:255',
            'icon_name' => 'required|string|max:100',
            'link' => 'required|string|max:2048',
            'is_external' => 'required|boolean',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'status' => 'required|string|in:draft,published,archived',
        ];
    }
}
