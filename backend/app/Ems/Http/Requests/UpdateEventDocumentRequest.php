<?php

namespace App\Ems\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
