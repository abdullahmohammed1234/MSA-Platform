<?php

namespace App\Ems\Http\Requests\Operations;

use App\Ems\Http\Requests\EmsFormRequest;

class WalkInRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attendee_name' => ['required', 'string', 'max:160'],
            'attendee_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'attendee_phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'ticket_type_id' => ['required', 'uuid'],
            'check_in' => ['sometimes', 'boolean'],
            'is_member' => ['sometimes', 'boolean'],
        ];
    }
}
