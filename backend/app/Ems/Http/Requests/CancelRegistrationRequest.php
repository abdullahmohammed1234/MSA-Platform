<?php

namespace App\Ems\Http\Requests;

class CancelRegistrationRequest extends EmsFormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'issue_refund' => ['sometimes', 'boolean'],
            'refund_amount' => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'A cancellation reason is required.',
            'reason.min' => 'The cancellation reason must be at least 3 characters.',
            'refund_amount.min' => 'The refund amount must be greater than zero.',
        ];
    }
}

