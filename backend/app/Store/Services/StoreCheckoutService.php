<?php

namespace App\Store\Services;

use App\Models\User;
use App\Services\Security\AuditLogger;
use App\Store\Enums\StoreFulfillmentStatus;
use App\Store\Enums\StorePaymentStatus;
use App\Store\Models\StoreCart;
use App\Store\Models\StoreOrder;
use App\Store\Models\StoreProduct;
use App\Store\Models\StoreProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreCheckoutService
{
    protected StorePaymentService $paymentService;
    protected AuditLogger $auditLogger;

    public function __construct(StorePaymentService $paymentService, AuditLogger $auditLogger)
    {
        $this->paymentService = $paymentService;
        $this->auditLogger = $auditLogger;
    }

    /**
     * Revalidate prices, check and reserve inventory under DB row locks, and create StoreOrder.
     */
    public function checkout(StoreCart $cart, array $customerData, ?User $user = null, string $redirectUrl = ''): array
    {
        if ($cart->items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => ['Your cart is empty.']]);
        }

        $order = DB::transaction(function () use ($cart, $customerData, $user) {
            $subtotalCents = 0;
            $orderItemsData = [];

            foreach ($cart->items as $cartItem) {
                // Lock product row for atomic stock reservation
                $product = StoreProduct::where('id', $cartItem->product_id)->lockForUpdate()->firstOrFail();

                if ($product->status->value !== 'active') {
                    throw ValidationException::withMessages([
                        'cart' => ["Product '{$product->name}' is no longer available."],
                    ]);
                }

                $variant = null;
                $unitPriceCents = $product->price_cents;
                $skuSnapshot = $product->sku;
                $variantNameSnapshot = null;

                if ($product->has_variants) {
                    if (!$cartItem->variant_id) {
                        throw ValidationException::withMessages([
                            'cart' => ["Please select a variant for '{$product->name}'."],
                        ]);
                    }

                    $variant = StoreProductVariant::where('id', $cartItem->variant_id)
                        ->where('product_id', $product->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (!$variant->is_active) {
                        throw ValidationException::withMessages([
                            'cart' => ["Option '{$variant->name}' for '{$product->name}' is no longer available."],
                        ]);
                    }

                    if ($variant->inventory_quantity < $cartItem->quantity) {
                        throw ValidationException::withMessages([
                            'cart' => ["Insufficient stock for '{$product->name} ({$variant->name})'. Only {$variant->inventory_quantity} remaining."],
                        ]);
                    }

                    // Reserve inventory
                    $variant->decrement('inventory_quantity', $cartItem->quantity);

                    $unitPriceCents = $variant->effective_price_cents;
                    $skuSnapshot = $variant->sku ?: $product->sku;
                    $variantNameSnapshot = $variant->name;
                } else {
                    if ($product->inventory_quantity < $cartItem->quantity) {
                        throw ValidationException::withMessages([
                            'cart' => ["Insufficient stock for '{$product->name}'. Only {$product->inventory_quantity} remaining."],
                        ]);
                    }

                    // Reserve inventory
                    $product->decrement('inventory_quantity', $cartItem->quantity);
                }

                $lineTotalCents = $unitPriceCents * $cartItem->quantity;
                $subtotalCents += $lineTotalCents;

                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'product_name_snapshot' => $product->name,
                    'variant_name_snapshot' => $variantNameSnapshot,
                    'sku_snapshot' => $skuSnapshot,
                    'unit_price_cents' => $unitPriceCents,
                    'quantity' => $cartItem->quantity,
                    'line_total_cents' => $lineTotalCents,
                ];
            }

            $taxCents = 0; // Configurable tax hook if needed
            $totalCents = $subtotalCents + $taxCents;

            // Create Order record
            $newOrder = StoreOrder::create([
                'user_id' => $user?->id,
                'customer_name' => trim((string) $customerData['customer_name']),
                'customer_email' => trim(strtolower((string) $customerData['customer_email'])),
                'customer_phone' => $customerData['customer_phone'] ?? null,
                'subtotal_cents' => $subtotalCents,
                'tax_cents' => $taxCents,
                'total_cents' => $totalCents,
                'currency' => 'CAD',
                'payment_status' => StorePaymentStatus::Pending,
                'fulfillment_status' => StoreFulfillmentStatus::Pending,
                'notes' => $customerData['notes'] ?? null,
            ]);

            foreach ($orderItemsData as $itemRow) {
                $newOrder->items()->create($itemRow);
            }

            // Clear cart items
            $cart->items()->delete();

            $this->auditLogger->log(
                action: 'store.order.created',
                target: $newOrder,
                description: "Created Store Order #{$newOrder->order_number} for {$newOrder->customer_email}",
                payload: ['total_cents' => $totalCents, 'item_count' => count($orderItemsData)],
                userId: $user?->id
            );

            return $newOrder->load('items');
        });

        // Initialize Square Checkout payment URL
        $checkoutUrl = $this->paymentService->createCheckoutLink($order, $redirectUrl);

        return [
            'order' => $order,
            'checkout_url' => $checkoutUrl,
        ];
    }

    /**
     * Release stock for abandoned/expired pending orders.
     */
    public function cleanupExpiredOrders(int $expirationMinutes = 30): int
    {
        $expiredThreshold = now()->subMinutes($expirationMinutes);

        $expiredOrders = StoreOrder::where('payment_status', StorePaymentStatus::Pending)
            ->where('created_at', '<', $expiredThreshold)
            ->get();

        $cleanedCount = 0;

        foreach ($expiredOrders as $order) {
            DB::transaction(function () use ($order) {
                $lockedOrder = StoreOrder::where('id', $order->id)
                    ->where('payment_status', StorePaymentStatus::Pending)
                    ->lockForUpdate()
                    ->first();

                if (!$lockedOrder) {
                    return;
                }

                foreach ($lockedOrder->items as $item) {
                    if ($item->variant_id) {
                        $variant = StoreProductVariant::where('id', $item->variant_id)->lockForUpdate()->first();
                        if ($variant) {
                            $prevQty = $variant->inventory_quantity;
                            $variant->increment('inventory_quantity', $item->quantity);

                            \App\Store\Models\StoreInventoryAdjustment::create([
                                'product_id' => $item->product_id,
                                'variant_id' => $variant->id,
                                'previous_quantity' => $prevQty,
                                'new_quantity' => $prevQty + $item->quantity,
                                'adjustment' => $item->quantity,
                                'reason' => "Expired Checkout Stock Release #{$lockedOrder->order_number}",
                            ]);
                        }
                    } else {
                        $product = StoreProduct::where('id', $item->product_id)->lockForUpdate()->first();
                        if ($product) {
                            $prevQty = $product->inventory_quantity;
                            $product->increment('inventory_quantity', $item->quantity);

                            \App\Store\Models\StoreInventoryAdjustment::create([
                                'product_id' => $product->id,
                                'previous_quantity' => $prevQty,
                                'new_quantity' => $prevQty + $item->quantity,
                                'adjustment' => $item->quantity,
                                'reason' => "Expired Checkout Stock Release #{$lockedOrder->order_number}",
                            ]);
                        }
                    }
                }

                $lockedOrder->update([
                    'payment_status' => StorePaymentStatus::Failed,
                    'fulfillment_status' => StoreFulfillmentStatus::Cancelled,
                    'notes' => trim(($lockedOrder->notes ? $lockedOrder->notes . "\n" : '') . '[System]: Payment window expired; stock released.'),
                ]);

                $this->auditLogger->log(
                    action: 'store.order.expired_cleaned_up',
                    target: $lockedOrder,
                    description: "Released stock and cancelled expired Store Order #{$lockedOrder->order_number}",
                    payload: ['order_uuid' => $lockedOrder->uuid]
                );
            });

            $cleanedCount++;
        }

        return $cleanedCount;
    }
}
