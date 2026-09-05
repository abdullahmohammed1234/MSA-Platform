<?php

namespace App\Mlibms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SelfServiceCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'copy_barcode' => 'required|string|max:100',
        ];
    }
}
