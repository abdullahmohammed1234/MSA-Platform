<?php

namespace App\Ems\Http\Requests\Operations;

use App\Ems\Http\Requests\EmsFormRequest;

class ValidateTicketRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255'],
        ];
    }
}
