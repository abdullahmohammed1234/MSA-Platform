<?php

namespace App\Ems\Http\Requests\Public;

use App\Ems\Http\Requests\EmsFormRequest;

class ResumeCheckoutRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'order_uuid' => ['sometimes', 'nullable', 'uuid'],
            'ticket_type_id' => ['sometimes', 'nullable', 'uuid'],
            'quantity' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            'promo_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'first_name' => ['sometimes', 'nullable', 'string', 'min:1', 'max:80'],
            'last_name' => ['sometimes', 'nullable', 'string', 'min:1', 'max:80'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
