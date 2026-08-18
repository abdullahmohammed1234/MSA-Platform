<?php

namespace App\Ems\Http\Requests;

use App\Ems\Http\Requests\EmsFormRequest;

class RefundPaymentRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:192'],
        ];
    }
}
