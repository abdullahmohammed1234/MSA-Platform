<?php

namespace App\Ems\Http\Requests;

use App\Ems\Http\Requests\EmsFormRequest;

class ReorderTicketTypesRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ordered_uuids' => ['required', 'array', 'min:1'],
            'ordered_uuids.*' => ['required', 'uuid'],
        ];
    }
}
