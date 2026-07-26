<?php

namespace App\Ems\Http\Resources\Public;

use App\Ems\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketType */
class PublicTicketTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $remaining = $this->remainingQuantity();

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'currency' => $this->currency,
            'is_free' => $this->isFree(),
            'quantity' => $this->quantity,
            'remaining_quantity' => $remaining,
            'is_sold_out' => $this->isSoldOut(),
            'is_on_sale' => $this->isOnSale(),
            'sales_start_at' => $this->sales_start_at?->toIso8601String(),
            'sales_end_at' => $this->sales_end_at?->toIso8601String(),
            'max_per_order' => $this->max_per_order,
            'sort_order' => $this->sort_order,
        ];
    }
}
