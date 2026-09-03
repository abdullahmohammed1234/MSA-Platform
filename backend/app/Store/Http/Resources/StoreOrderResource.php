<?php

namespace App\Store\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'order_number' => $this->order_number,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'subtotal_cents' => $this->subtotal_cents,
            'tax_cents' => $this->tax_cents,
            'total_cents' => $this->total_cents,
            'formatted_total' => $this->formatted_total,
            'currency' => $this->currency,
            'payment_status' => $this->payment_status->value,
            'payment_status_label' => $this->payment_status->label(),
            'fulfillment_status' => $this->fulfillment_status->value,
            'fulfillment_status_label' => $this->fulfillment_status->label(),
            'square_payment_id' => $this->square_payment_id,
            'square_order_id' => $this->square_order_id,
            'square_checkout_url' => $this->square_checkout_url,
            'notes' => $this->notes,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'fulfilled_at' => $this->fulfilled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'items' => $this->items->map(fn ($item) => [
                'uuid' => $item->uuid,
                'product_name' => $item->product_name_snapshot,
                'variant_name' => $item->variant_name_snapshot,
                'sku' => $item->sku_snapshot,
                'unit_price_cents' => $item->unit_price_cents,
                'formatted_unit_price' => $item->formatted_unit_price,
                'quantity' => $item->quantity,
                'line_total_cents' => $item->line_total_cents,
                'formatted_line_total' => $item->formatted_line_total,
            ]),
        ];
    }
}
