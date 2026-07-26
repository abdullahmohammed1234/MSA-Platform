<?php

namespace App\Ems\Http\Requests;

class UpdateTicketTypeRequest extends StoreTicketTypeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['name'] = ['sometimes', 'required', 'string', 'min:1', 'max:120'];
        $rules['price'] = ['sometimes', 'required', 'numeric', 'min:0', 'max:999999.99'];

        return $rules;
    }
}
