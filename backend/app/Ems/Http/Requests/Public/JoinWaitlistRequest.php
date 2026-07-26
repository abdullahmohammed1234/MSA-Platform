<?php

namespace App\Ems\Http\Requests\Public;

use App\Ems\Http\Requests\EmsFormRequest;

class JoinWaitlistRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'min:1', 'max:80'],
            'last_name' => ['required', 'string', 'min:1', 'max:80'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
            'ticket_type_id' => ['nullable', 'uuid'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->nullifyBlanks(['phone', 'ticket_type_id']));
    }
}
