<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVolunteerRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                function ($attribute, $value, $fail) {
                    $email = trim(strtolower($value));
                    if (!preg_match('/^[a-zA-Z0-9._%+-]+@(?:[a-zA-Z0-9-]+\.)*sfu\.ca$/i', $email)) {
                        $fail('Volunteers must register with an @sfu.ca email address.');
                    }
                },
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'student_number' => ['required', 'string', 'regex:/^\d{9}$/'],
            'department' => [
                'required',
                'string',
                Rule::in([
                    'Education',
                    'Finance',
                    'Events',
                    'Marketing (Media & Comms)',
                    'IT',
                    'General Inquiries',
                ]),
            ],
            'interests' => ['required', 'string', 'max:5000'],
            'experience' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_number.regex' => 'Student number must be exactly 9 digits.',
            'department.in' => 'Selected department is invalid.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => trim(strtolower((string) $this->input('email'))),
            ]);
        }

        if ($this->has('student_number')) {
            $this->merge([
                'student_number' => trim((string) $this->input('student_number')),
            ]);
        }

        if ($this->has('phone')) {
            $this->merge([
                'phone' => trim((string) $this->input('phone')),
            ]);
        }
    }
}
