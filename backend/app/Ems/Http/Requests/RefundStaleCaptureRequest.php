<?php

namespace App\Ems\Http\Requests;

use App\Ems\Http\Requests\EmsFormRequest;

class RefundStaleCaptureRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'min:3', 'max:192'],
        ];
    }
}
