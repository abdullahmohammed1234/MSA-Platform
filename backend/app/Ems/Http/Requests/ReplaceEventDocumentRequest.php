<?php

namespace App\Ems\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceEventDocumentRequest extends FormRequest
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
        ];
    }
}
