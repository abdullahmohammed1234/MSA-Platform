<?php

namespace App\Ems\Http\Requests\Public;

use App\Ems\Http\Requests\EmsFormRequest;

/**
 * Free-event registration form.
 *
 * Field set is intentionally small for Phase 2; custom questions arrive in
 * Phase 8 via the registration metadata column.
 */
class RegisterForEventRequest extends EmsFormRequest
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
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
            'ticket_type_id' => ['nullable', 'uuid'],
            'promo_code' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->nullifyBlanks([
            'phone',
            'student_id',
            'notes',
            'ticket_type_id',
            'promo_code',
        ]));
    }
}
