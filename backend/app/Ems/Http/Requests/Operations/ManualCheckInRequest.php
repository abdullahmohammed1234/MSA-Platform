<?php

namespace App\Ems\Http\Requests\Operations;

use App\Ems\Http\Requests\EmsFormRequest;

class ManualCheckInRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'registration_uuid' => ['required_without:ticket_code', 'nullable', 'uuid'],
            'ticket_code' => ['required_without:registration_uuid', 'nullable', 'string', 'max:255'],
            'device' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
