<?php

namespace App\Store\Services;

use App\Models\User;
use App\Store\Models\StoreCart;
use App\Store\Models\StoreCartItem;
use App\Store\Models\StoreProduct;
use App\Store\Models\StoreProductVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class StoreCartService
{
    /**
     * Resolve active cart for user or guest session.
     */
    public function resolveCart(?User $user = null, ?string $sessionId = null): StoreCart
    {
        if ($user) {
            $cart = StoreCart::firstOrCreate(
                ['user_id' => $user->id],
                ['session_id' => $sessionId, 'expires_at' => now()->addDays(7)]
            );
        } else {
            $resolvedSession = $sessionId ?: session()->getId();
            $cart = StoreCart::firstOrCreate(
                ['session_id' => $resolvedSession],
                ['expires_at' => now()->addDays(7)]
            );
        }

        return $cart->load(['items.product.images', 'items.variant']);
    }

    /**
     * Add or update product/variant in cart.
     */
    public function addItem(StoreCart $cart, int $productId, ?int $variantId = null, int $quantity = 1): StoreCart
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => ['Quantity must be at least 1.']]);
        }

        $product = StoreProduct::where('id', $productId)->where('status', 'active')->firstOrFail();
        $variant = null;

        if ($product->has_variants) {
            if (!$variantId) {
                throw ValidationException::withMessages(['variant_id' => ['Please select a variant option.']]);
            }
            $variant = StoreProductVariant::where('id', $variantId)
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->firstOrFail();

            if ($variant->inventory_quantity < $quantity) {
                throw ValidationException::withMessages(['quantity' => ["Only {$variant->inventory_quantity} units available in stock."]]);
            }
        } else {
            if ($product->inventory_quantity < $quantity) {
                throw ValidationException::withMessages(['quantity' => ["Only {$product->inventory_quantity} units available in stock."]]);
            }
        }

        $existingItem = $cart->items()
            ->where('product_id', $product->id)
            ->where('variant_id', $variant?->id)
            ->first();

        if ($existingItem) {
            $newQty = $existingItem->quantity + $quantity;
            $maxAvailable = $variant ? $variant->inventory_quantity : $product->inventory_quantity;

            if ($newQty > $maxAvailable) {
                throw ValidationException::withMessages(['quantity' => ["Cannot add to cart. Total quantity would exceed available stock ({$maxAvailable})."]]);
            }

            $existingItem->update(['quantity' => $newQty]);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'quantity' => $quantity,
            ]);
        }

        return $cart->fresh(['items.product.images', 'items.variant']);
    }

    /**
     * Update item quantity.
     */
    public function updateItemQuantity(StoreCart $cart, string $itemUuid, int $quantity): StoreCart
    {
        $item = $cart->items()->where('uuid', $itemUuid)->firstOrFail();

        if ($quantity <= 0) {
            $item->delete();
            return $cart->fresh(['items.product.images', 'items.variant']);
        }

        $product = $item->product;
        $variant = $item->variant;
        $maxAvailable = $variant ? $variant->inventory_quantity : $product->inventory_quantity;

        if ($quantity > $maxAvailable) {
            throw ValidationException::withMessages(['quantity' => ["Only {$maxAvailable} units available in stock."]]);
        }

        $item->update(['quantity' => $quantity]);

        return $cart->fresh(['items.product.images', 'items.variant']);
    }

    /**
     * Remove item from cart.
     */
    public function removeItem(StoreCart $cart, string $itemUuid): StoreCart
    {
        $cart->items()->where('uuid', $itemUuid)->delete();

        return $cart->fresh(['items.product.images', 'items.variant']);
    }

    /**
     * Clear all items in cart.
     */
    public function clearCart(StoreCart $cart): void
    {
        $cart->items()->delete();
    }
}
