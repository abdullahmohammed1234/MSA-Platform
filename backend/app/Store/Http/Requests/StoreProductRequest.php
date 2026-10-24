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
            'is_merchandise' => 'nullable|boolean',
            'inventory_quantity' => 'nullable|integer|min:0',
            'image_url' => 'nullable|string|max:2048',
            'merchandise_images' => 'nullable|array',
            'images' => 'nullable|array',
            'variants' => 'nullable|array',
            'variants.*.uuid' => 'nullable|string|uuid',
            'variants.*.name' => 'required_with:variants|string|max:255',
            'variants.*.sku' => 'nullable|string|max:100',
            'variants.*.price_override_cents' => 'nullable|integer|min:0',
            'variants.*.inventory_quantity' => 'nullable|integer|min:0',
            'variants.*.is_active' => 'nullable|boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->boolean('is_merchandise')) {
                $product = $this->route('product');
                $merchandiseImages = $this->input('merchandise_images', []);
                $imagesList = $this->input('images', []);

                $requiredSlots = [
                    'sister_front' => ['sister', 'front'],
                    'sister_back' => ['sister', 'back'],
                    'brother_front' => ['brother', 'front'],
                    'brother_back' => ['brother', 'back'],
                    'neutral_front' => ['neutral', 'front'],
                    'neutral_back' => ['neutral', 'back'],
                ];

                foreach ($requiredSlots as $slotKey => [$type, $view]) {
                    $hasInInput = !empty($merchandiseImages[$slotKey])
                        || $this->hasFile("merchandise_images.{$slotKey}")
                        || collect($imagesList)->contains(function ($img) use ($type, $view) {
                            return is_array($img)
                                && ($img['image_type'] ?? '') === $type
                                && ($img['view_type'] ?? '') === $view
                                && (!empty($img['image_url']) || !empty($img['image_path']));
                        });

                    if (!$hasInInput) {
                        if ($product instanceof \App\Store\Models\StoreProduct) {
                            $hasExisting = $product->getMerchandiseImage($type, $view) !== null;
                            if (!$hasExisting) {
                                $validator->errors()->add("merchandise_images.{$slotKey}", "The {$type} {$view} image is required for merchandise products.");
                            }
                        } else {
                            $validator->errors()->add("merchandise_images.{$slotKey}", "The {$type} {$view} image is required for merchandise products.");
                        }
                    }
                }
            }
        });
    }
}
