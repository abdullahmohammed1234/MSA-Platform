<?php

namespace App\Ems\Http\Requests\Operations;

use App\Ems\Http\Requests\EmsFormRequest;

class TerminalCheckoutRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ticket_type_id' => ['required', 'uuid'],
            'attendee_name' => ['required', 'string', 'max:160'],
            'attendee_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'attendee_phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'device_id' => ['sometimes', 'nullable', 'string', 'max:191'],
        ];
    }
}
