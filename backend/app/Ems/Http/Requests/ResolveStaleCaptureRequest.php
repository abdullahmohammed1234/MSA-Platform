<?php

namespace App\Ems\Http\Requests;

use App\Ems\Http\Requests\EmsFormRequest;

class ResolveStaleCaptureRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
