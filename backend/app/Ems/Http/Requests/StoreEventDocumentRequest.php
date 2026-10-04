<?php

namespace App\Ems\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventDocumentRequest extends FormRequest
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
        $maxKb = (int) config('ems.documents.max_file_size_kb', 10240);

        return [
            'file' => ['required', 'file', 'mimes:pdf', "max:{$maxKb}"],
            'name' => ['required', 'string', 'max:255'],
            'document_type' => [
                'required',
                'string',
                'in:itinerary,menu,schedule,map,program,information,other',
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
