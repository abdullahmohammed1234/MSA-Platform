<?php

namespace App\Ems\Http\Requests\Public;

use App\Ems\Http\Requests\EmsFormRequest;

class CheckoutEventRequest extends EmsFormRequest
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
            'student_id' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:500'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:50'],
            'ticket_type_id' => ['required', 'uuid'],
            'promo_code' => ['nullable', 'string', 'max:50'],
            'order_uuid' => ['sometimes', 'nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ticket_type_id.required' => 'Please select a ticket type.',
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'email.required' => 'Email is required.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->nullifyBlanks([
            'phone',
            'student_id',
            'notes',
            'promo_code',
        ]));
    }
}
