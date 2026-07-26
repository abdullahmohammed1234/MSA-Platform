<?php

namespace App\Ems\Http\Requests\Operations;

use App\Ems\Http\Requests\EmsFormRequest;

class PreviewImportRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
            'column_mapping' => ['sometimes'],
            'mapping' => ['sometimes', 'array'],
            'mapping.name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'mapping.email' => ['sometimes', 'nullable', 'string', 'max:120'],
            'mapping.phone' => ['sometimes', 'nullable', 'string', 'max:120'],
            'mapping.ticket_type' => ['sometimes', 'nullable', 'string', 'max:120'],
            'mapping.member_status' => ['sometimes', 'nullable', 'string', 'max:120'],
            'mapping.registration_status' => ['sometimes', 'nullable', 'string', 'max:120'],
            'mapping.payment_status' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function mappingArray(): array
    {
        if ($this->filled('column_mapping') && is_string($this->input('column_mapping'))) {
            $decoded = json_decode($this->input('column_mapping'), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $this->input('mapping', []);
    }
}
