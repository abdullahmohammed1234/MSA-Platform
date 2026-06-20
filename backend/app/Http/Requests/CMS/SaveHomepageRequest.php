<?php

namespace App\Http\Requests\CMS;

use Illuminate\Foundation\Http\FormRequest;

class SaveHomepageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controlled by Policy & Middleware
    }

    public function rules(): array
    {
        return [
            'blocks' => 'required|array',
            'blocks.*' => 'nullable|string',
        ];
    }
}
