<?php

namespace App\Services\Operations\Rules;

use App\Services\Operations\OperationalAlertService;
use App\Store\Enums\StoreFulfillmentStatus;
use App\Store\Enums\StorePaymentStatus;
use App\Store\Models\StoreOrder;
use App\Store\Models\StoreProduct;
use App\Store\Models\StoreProductVariant;
use Illuminate\Support\Facades\Schema;

class StoreOperationalRules
{
    public function __construct(
        private OperationalAlertService $alertService
    ) {}

    public function detect(): int
    {
        if (! Schema::hasTable('store_products') || ! Schema::hasTable('store_orders')) {
            return 0;
        }

        $detected = 0;

        // 1. Low inventory check for simple products (<= 5 items)
        $lowStockProducts = StoreProduct::where('has_variants', false)
            ->where('inventory_quantity', '<=', 5)
            ->get();

        foreach ($lowStockProducts as $product) {
            $this->alertService->upsertAlert([
                'category' => 'store',
                'severity' => 'medium',
                'title' => "Low Inventory: {$product->name}",
                'description' => "Product '{$product->name}' stock level is low ({$product->inventory_quantity} remaining).",
                'source_type' => 'StoreProduct',
                'source_id' => (string) $product->id,
                'rule_key' => 'store_inventory_low',
                'action_url' => '/store/admin/products',
                'metadata' => [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'inventory_quantity' => $product->inventory_quantity,
                    'threshold' => 5,
                ],
            ]);
            $detected++;
        }

        // Low inventory check for product variants (<= 5 items)
        if (Schema::hasTable('store_product_variants')) {
            $lowStockVariants = StoreProductVariant::where('is_active', true)
                ->where('inventory_quantity', '<=', 5)
                ->with('product')
                ->get();

            foreach ($lowStockVariants as $variant) {
                $productName = $variant->product?->name ?? 'Product';
                $this->alertService->upsertAlert([
                    'category' => 'store',
                    'severity' => 'medium',
                    'title' => "Low Inventory Variant: {$productName} - {$variant->name}",
                    'description' => "Variant '{$variant->name}' of '{$productName}' stock level is low ({$variant->inventory_quantity} remaining).",
                    'source_type' => 'StoreProductVariant',
                    'source_id' => (string) $variant->id,
                    'rule_key' => 'store_inventory_low',
                    'action_url' => '/store/admin/products',
                    'metadata' => [
                        'variant_id' => $variant->id,
                        'product_name' => $productName,
                        'variant_name' => $variant->name,
                        'inventory_quantity' => $variant->inventory_quantity,
                        'threshold' => 5,
                    ],
                ]);
                $detected++;
            }
        }

        // 2. Unfulfilled paid orders (> 48 hours)
        $unfulfilledOrders = StoreOrder::where('payment_status', StorePaymentStatus::Paid->value)
            ->whereNotIn('fulfillment_status', [StoreFulfillmentStatus::Completed->value, StoreFulfillmentStatus::Cancelled->value])
            ->where('paid_at', '<', now()->subHours(48))
            ->get();

        foreach ($unfulfilledOrders as $order) {
            $hoursPaid = (int) now()->diffInHours($order->paid_at);
            $this->alertService->upsertAlert([
                'category' => 'store',
                'severity' => 'high',
                'title' => "Unfulfilled Paid Order: #{$order->order_number}",
                'description' => "Paid store order #{$order->order_number} has been pending fulfillment for {$hoursPaid} hours.",
                'source_type' => 'StoreOrder',
                'source_id' => (string) $order->id,
                'rule_key' => 'store_unfulfilled_paid_orders',
                'action_url' => '/store/admin/orders',
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'paid_at' => $order->paid_at?->toIso8601String(),
                    'hours_pending' => $hoursPaid,
                    'total_cents' => $order->total_cents,
                ],
            ]);
            $detected++;
        }

        return $detected;
    }
}
