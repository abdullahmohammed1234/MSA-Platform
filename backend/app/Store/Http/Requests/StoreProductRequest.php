<?php

namespace App\Store\Http\Requests;

use App\Store\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'price_cents' => 'required|integer|min:0',
            'currency' => 'nullable|string|size:3',
            'sku' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:' . implode(',', array_column(ProductStatus::cases(), 'value')),
            'has_variants' => 'nullable|boolean',
            'inventory_quantity' => 'nullable|integer|min:0',
            'image_url' => 'nullable|string|url|max:2048',
            'variants' => 'nullable|array',
            'variants.*.uuid' => 'nullable|string|uuid',
            'variants.*.name' => 'required_with:variants|string|max:255',
            'variants.*.sku' => 'nullable|string|max:100',
            'variants.*.price_override_cents' => 'nullable|integer|min:0',
            'variants.*.inventory_quantity' => 'nullable|integer|min:0',
            'variants.*.is_active' => 'nullable|boolean',
        ];
    }
}
