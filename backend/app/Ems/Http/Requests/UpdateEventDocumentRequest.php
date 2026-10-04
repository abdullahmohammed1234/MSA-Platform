<?php

namespace App\Ems\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('sort_order')) {
            $val = $this->input('sort_order');
            if ($val === '' || $val === null || !is_numeric($val)) {
                $this->merge(['sort_order' => 0]);
            } else {
                $this->merge(['sort_order' => (int) $val]);
            }
        }

        if ($this->has('description') && trim((string) $this->input('description')) === '') {
            $this->merge(['description' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'document_type' => [
                'sometimes',
                'required',
                'string',
                'in:itinerary,menu,schedule,map,program,information,other',
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
