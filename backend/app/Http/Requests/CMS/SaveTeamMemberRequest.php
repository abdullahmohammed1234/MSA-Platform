<?php

namespace App\Http\Requests\CMS;

use Illuminate\Foundation\Http\FormRequest;

class SaveTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'role' => 'required|string|max:255',
            'dept' => 'required|string|max:255',
            'img' => 'nullable|string|max:2048',
            'bio' => 'nullable|string',
            'email' => 'nullable|email|max:255',
            'linkedin' => 'nullable|string|max:255',
            'display_order' => 'nullable|integer',
            'status' => 'required|string|in:draft,published,archived',
        ];
    }
}
