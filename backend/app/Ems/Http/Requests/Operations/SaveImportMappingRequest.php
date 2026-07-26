<?php

namespace App\Ems\Http\Requests\Operations;

use App\Ems\Http\Requests\EmsFormRequest;

class SaveImportMappingRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'mapping' => ['required', 'array'],
            'mapping.name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'mapping.email' => ['sometimes', 'nullable', 'string', 'max:120'],
            'mapping.phone' => ['sometimes', 'nullable', 'string', 'max:120'],
            'mapping.ticket_type' => ['sometimes', 'nullable', 'string', 'max:120'],
            'mapping.member_status' => ['sometimes', 'nullable', 'string', 'max:120'],
            'mapping.registration_status' => ['sometimes', 'nullable', 'string', 'max:120'],
            'mapping.payment_status' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }
}
