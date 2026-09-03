<?php

namespace App\Store\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $primaryImg = $this->primaryImage();

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price_cents' => $this->price_cents,
            'formatted_price' => $this->formatted_price,
            'currency' => $this->currency,
            'sku' => $this->sku,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'has_variants' => $this->has_variants,
            'inventory_quantity' => $this->inventory_quantity,
            'primary_image_url' => $primaryImg?->image_url,
            'images' => $this->images->map(fn ($img) => [
                'uuid' => $img->uuid,
                'image_url' => $img->image_url,
                'is_primary' => $img->is_primary,
                'display_order' => $img->display_order,
            ]),
            'variants' => $this->variants->map(fn ($v) => [
                'id' => $v->id,
                'uuid' => $v->uuid,
                'name' => $v->name,
                'sku' => $v->sku,
                'price_override_cents' => $v->price_override_cents,
                'effective_price_cents' => $v->effective_price_cents,
                'formatted_price' => $v->formatted_price,
                'inventory_quantity' => $v->inventory_quantity,
                'is_active' => $v->is_active,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
