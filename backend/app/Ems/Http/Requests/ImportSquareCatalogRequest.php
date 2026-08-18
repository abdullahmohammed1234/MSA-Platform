<?php

namespace App\Ems\Http\Requests;

use App\Ems\Http\Requests\EmsFormRequest;

class ImportSquareCatalogRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'square_catalog_variation_id' => ['required', 'string', 'max:191'],
        ];
    }
}
