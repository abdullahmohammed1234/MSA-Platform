<?php

namespace App\Ems\Http\Resources;

use App\Ems\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketType */
class TicketTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'currency' => $this->currency,
            'quantity' => $this->quantity,
            'quantity_sold' => $this->quantity_sold,
            'remaining_quantity' => $this->remainingQuantity(),
            'sales_start_at' => $this->sales_start_at?->toIso8601String(),
            'sales_end_at' => $this->sales_end_at?->toIso8601String(),
            'is_active' => $this->is_active,
            'is_visible' => $this->is_visible,
            'is_free' => $this->isFree(),
            'is_sold_out' => $this->isSoldOut(),
            'is_on_sale' => $this->isOnSale(),
            'max_per_order' => $this->max_per_order,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
