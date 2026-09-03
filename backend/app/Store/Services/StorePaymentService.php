<?php

namespace App\Store\Services;

use App\Ems\Services\Square\SquareClient;
use App\Services\Security\AuditLogger;
use App\Store\Enums\StoreFulfillmentStatus;
use App\Store\Enums\StorePaymentStatus;
use App\Store\Models\StoreOrder;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StorePaymentService
{
    protected SquareClient $squareClient;
    protected AuditLogger $auditLogger;

    public function __construct(SquareClient $squareClient, AuditLogger $auditLogger)
    {
        $this->squareClient = $squareClient;
        $this->auditLogger = $auditLogger;
    }

    /**
     * Create Square Web Checkout Payment Link for a StoreOrder.
     */
    public function createCheckoutLink(StoreOrder $order, string $redirectUrl): string
    {
        if (!$this->squareClient->enabled()) {
            // Mock checkout URL for dev/sandbox mode without credentials
            $order->update([
                'square_checkout_url' => $redirectUrl . '?order=' . $order->uuid . '&mock_square=true',
            ]);
            return $order->square_checkout_url;
        }

        $lineItems = [];
        foreach ($order->items as $item) {
            $lineItems[] = [
                'name' => $item->product_name_snapshot . ($item->variant_name_snapshot ? " ({$item->variant_name_snapshot})" : ''),
                'quantity' => (string) $item->quantity,
                'base_price_money' => [
                    'amount' => $item->unit_price_cents,
                    'currency' => $order->currency,
                ],
            ];
        }

        $payload = [
            'idempotency_key' => 'store_ord_' . $order->uuid,
            'order' => [
                'location_id' => $this->squareClient->locationId(),
                'reference_id' => $order->order_number,
                'line_items' => $lineItems,
            ],
            'checkout_options' => [
                'redirect_url' => $redirectUrl,
                'ask_for_shipping_address' => false,
            ],
        ];

        try {
            $response = $this->squareClient->post('/v2/online-checkout/payment-links', $payload);
            $checkoutUrl = (string) data_get($response, 'payment_link.url', '');
            $squareOrderId = (string) data_get($response, 'payment_link.order_id', '');

            if ($checkoutUrl === '') {
                throw new \Exception('Square did not return a valid payment link URL.');
            }

            $order->update([
                'square_checkout_url' => $checkoutUrl,
                'square_order_id' => $squareOrderId !== '' ? $squareOrderId : null,
            ]);

            return $checkoutUrl;
        } catch (\Throwable $ex) {
            Log::error('store.square.checkout_failed', [
                'order_uuid' => $order->uuid,
                'error' => $ex->getMessage(),
            ]);

            throw new \Exception('Unable to initialize Square checkout. Please try again.');
        }
    }

    /**
     * Mark order paid upon successful Square webhook or confirmation callback.
     */
    public function markOrderPaid(StoreOrder $order, string $squarePaymentId, ?string $squareOrderId = null): StoreOrder
    {
        return DB::transaction(function () use ($order, $squarePaymentId, $squareOrderId) {
            // Re-lock order row
            $lockedOrder = StoreOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->payment_status === StorePaymentStatus::Paid) {
                return $lockedOrder;
            }

            $wasCleanedUp = ($lockedOrder->payment_status === StorePaymentStatus::Failed);

            if ($wasCleanedUp) {
                foreach ($lockedOrder->items as $item) {
                    if ($item->variant_id) {
                        $variant = \App\Store\Models\StoreProductVariant::where('id', $item->variant_id)->lockForUpdate()->first();
                        if ($variant) {
                            $prevQty = $variant->inventory_quantity;
                            $newQty = max(0, $prevQty - $item->quantity);
                            $variant->update(['inventory_quantity' => $newQty]);

                            \App\Store\Models\StoreInventoryAdjustment::create([
                                'product_id' => $item->product_id,
                                'variant_id' => $variant->id,
                                'previous_quantity' => $prevQty,
                                'new_quantity' => $newQty,
                                'adjustment' => -$item->quantity,
                                'reason' => "Late Payment Capture Stock Re-reservation #{$lockedOrder->order_number}",
                            ]);
                        }
                    } else {
                        $product = \App\Store\Models\StoreProduct::where('id', $item->product_id)->lockForUpdate()->first();
                        if ($product) {
                            $prevQty = $product->inventory_quantity;
                            $newQty = max(0, $prevQty - $item->quantity);
                            $product->update(['inventory_quantity' => $newQty]);

                            \App\Store\Models\StoreInventoryAdjustment::create([
                                'product_id' => $product->id,
                                'previous_quantity' => $prevQty,
                                'new_quantity' => $newQty,
                                'adjustment' => -$item->quantity,
                                'reason' => "Late Payment Capture Stock Re-reservation #{$lockedOrder->order_number}",
                            ]);
                        }
                    }
                }
            }

            $lockedOrder->update([
                'payment_status' => StorePaymentStatus::Paid,
                'fulfillment_status' => StoreFulfillmentStatus::Preparing,
                'square_payment_id' => $squarePaymentId,
                'square_order_id' => $squareOrderId ?? $lockedOrder->square_order_id,
                'paid_at' => now(),
            ]);

            $this->auditLogger->log(
                action: 'store.order.paid',
                target: $lockedOrder,
                description: "Store Order #{$lockedOrder->order_number} payment confirmed via Square." . ($wasCleanedUp ? ' (Re-reserved stock after late capture)' : ''),
                payload: ['square_payment_id' => $squarePaymentId, 'total_cents' => $lockedOrder->total_cents, 'late_capture' => $wasCleanedUp]
            );

            return $lockedOrder;
        });
    }

    /**
     * Issue refund via Square for a paid Store order.
     */
    public function refundOrder(StoreOrder $order, ?string $reason = null, ?int $adminUserId = null): StoreOrder
    {
        return DB::transaction(function () use ($order, $reason, $adminUserId) {
            $lockedOrder = StoreOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->payment_status !== StorePaymentStatus::Paid) {
                throw new \Exception('Only paid orders can be refunded.');
            }

            if ($this->squareClient->enabled() && $lockedOrder->square_payment_id) {
                $payload = [
                    'idempotency_key' => 'store_ref_' . $lockedOrder->uuid . '_' . time(),
                    'amount_money' => [
                        'amount' => $lockedOrder->total_cents,
                        'currency' => $lockedOrder->currency,
                    ],
                    'payment_id' => $lockedOrder->square_payment_id,
                    'reason' => $reason ?? 'Customer requested refund.',
                ];

                $this->squareClient->post('/v2/refunds', $payload);
            }

            // Restore inventory stock for refunded line items
            foreach ($lockedOrder->items as $item) {
                if ($item->variant_id) {
                    $variant = \App\Store\Models\StoreProductVariant::where('id', $item->variant_id)->lockForUpdate()->first();
                    if ($variant) {
                        $prevQty = $variant->inventory_quantity;
                        $variant->increment('inventory_quantity', $item->quantity);

                        \App\Store\Models\StoreInventoryAdjustment::create([
                            'product_id' => $item->product_id,
                            'variant_id' => $variant->id,
                            'previous_quantity' => $prevQty,
                            'new_quantity' => $prevQty + $item->quantity,
                            'adjustment' => $item->quantity,
                            'reason' => "Order Refund Restock #{$lockedOrder->order_number}",
                            'user_id' => $adminUserId,
                        ]);
                    }
                } else {
                    $product = \App\Store\Models\StoreProduct::where('id', $item->product_id)->lockForUpdate()->first();
                    if ($product) {
                        $prevQty = $product->inventory_quantity;
                        $product->increment('inventory_quantity', $item->quantity);

                        \App\Store\Models\StoreInventoryAdjustment::create([
                            'product_id' => $product->id,
                            'previous_quantity' => $prevQty,
                            'new_quantity' => $prevQty + $item->quantity,
                            'adjustment' => $item->quantity,
                            'reason' => "Order Refund Restock #{$lockedOrder->order_number}",
                            'user_id' => $adminUserId,
                        ]);
                    }
                }
            }

            $lockedOrder->update([
                'payment_status' => StorePaymentStatus::Refunded,
                'fulfillment_status' => StoreFulfillmentStatus::Cancelled,
            ]);

            $this->auditLogger->log(
                action: 'store.order.refunded',
                target: $lockedOrder,
                description: "Refunded Store Order #{$lockedOrder->order_number}",
                payload: ['reason' => $reason, 'restocked' => true],
                userId: $adminUserId
            );

            return $lockedOrder;
        });
    }
}
