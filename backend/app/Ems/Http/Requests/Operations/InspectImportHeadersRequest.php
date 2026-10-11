<?php

namespace App\Ems\Http\Requests\Operations;

use App\Ems\Http\Requests\EmsFormRequest;

class InspectImportHeadersRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
        ];
    }
}
