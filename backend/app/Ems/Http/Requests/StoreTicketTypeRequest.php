<?php

namespace App\Ems\Http\Requests;

use App\Ems\Http\Requests\EmsFormRequest;

class StoreTicketTypeRequest extends EmsFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'sales_start_at' => ['nullable', 'date'],
            'sales_end_at' => ['nullable', 'date', 'after_or_equal:sales_start_at'],
            'is_active' => ['sometimes', 'boolean'],
            'is_visible' => ['sometimes', 'boolean'],
            'max_per_order' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->nullifyBlanks([
            'description',
            'currency',
            'quantity',
            'sales_start_at',
            'sales_end_at',
            'max_per_order',
        ]));

        if ($this->filled('currency')) {
            $this->merge(['currency' => strtoupper((string) $this->input('currency'))]);
        }
    }
}
