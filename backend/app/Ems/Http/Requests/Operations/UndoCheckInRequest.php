<?php

namespace App\Ems\Http\Requests\Operations;

use App\Ems\Http\Requests\EmsFormRequest;

class UndoCheckInRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'check_in_uuid' => ['required_without:ticket_code', 'nullable', 'uuid'],
            'ticket_code' => ['required_without:check_in_uuid', 'nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
