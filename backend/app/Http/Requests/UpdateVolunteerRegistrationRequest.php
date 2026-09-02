<?php

namespace App\Http\Requests;

use App\Enums\VolunteerRegistrationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVolunteerRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::enum(VolunteerRegistrationStatus::class)],
            'admin_notes' => ['nullable', 'string', 'max:10000'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ];
    }
}
