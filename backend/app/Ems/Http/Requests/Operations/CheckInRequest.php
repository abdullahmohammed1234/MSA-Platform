<?php

namespace App\Ems\Http\Requests\Operations;

use App\Ems\Enums\CheckInMethod;
use App\Ems\Http\Requests\EmsFormRequest;
use Illuminate\Validation\Rule;

class CheckInRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255'],
            'method' => ['sometimes', 'string', Rule::in(CheckInMethod::values())],
            'device' => ['sometimes', 'nullable', 'string', 'max:64'],
            'override' => ['sometimes', 'boolean'],
        ];
    }
}
