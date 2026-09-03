<?php

namespace App\Store\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreCartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'subtotal_cents' => $this->subtotal_cents,
            'formatted_subtotal' => '$' . number_format($this->subtotal_cents / 100, 2),
            'items' => $this->items->map(function ($item) {
                $primaryImg = $item->product?->primaryImage();
                return [
                    'uuid' => $item->uuid,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'product_name' => $item->product?->name,
                    'product_slug' => $item->product?->slug,
                    'variant_name' => $item->variant?->name,
                    'sku' => $item->variant?->sku ?: $item->product?->sku,
                    'unit_price_cents' => $item->unit_price_cents,
                    'formatted_unit_price' => '$' . number_format($item->unit_price_cents / 100, 2),
                    'quantity' => $item->quantity,
                    'line_total_cents' => $item->line_total_cents,
                    'formatted_line_total' => '$' . number_format($item->line_total_cents / 100, 2),
                    'image_url' => $primaryImg?->image_url,
                    'max_available' => $item->variant ? $item->variant->inventory_quantity : ($item->product?->inventory_quantity ?? 0),
                ];
            }),
            'item_count' => $this->items->sum('quantity'),
        ];
    }
}
