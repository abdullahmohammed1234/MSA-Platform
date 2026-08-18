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
            'square_sync' => $this->squareSyncPayload(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function squareSyncPayload(): array
    {
        $mapping = $this->relationLoaded('squareCatalogMapping')
            ? $this->squareCatalogMapping
            : $this->squareCatalogMapping()->first();

        if ($mapping === null) {
            return [
                'status' => 'not_synced',
                'status_label' => 'Not Synced',
                'catalog_item_id' => null,
                'catalog_variation_id' => null,
                'last_synced_at' => null,
                'last_error' => null,
                'last_conflict_summary' => null,
            ];
        }

        return [
            'status' => $mapping->sync_status->value,
            'status_label' => $mapping->sync_status->label(),
            'catalog_item_id' => $mapping->square_catalog_item_id,
            'catalog_variation_id' => $mapping->square_catalog_variation_id,
            'location_id' => $mapping->square_location_id,
            'last_synced_at' => $mapping->last_synced_at?->toIso8601String(),
            'last_error' => $mapping->last_error,
            'last_conflict_summary' => $mapping->last_conflict_summary,
        ];
    }
}
