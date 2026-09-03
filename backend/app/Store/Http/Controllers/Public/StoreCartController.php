<?php

namespace App\Store\Http\Controllers\Public;

use App\Ems\Support\ApiResponse;
use App\Http\Controllers\Controller;
use App\Store\Http\Resources\StoreCartResource;
use App\Store\Services\StoreCartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreCartController extends Controller
{
    protected StoreCartService $cartService;

    public function __construct(StoreCartService $cartService)
    {
        $this->cartService = $cartService;
    }

    public function show(Request $request): JsonResponse
    {
        $cart = $this->cartService->resolveCart(
            $request->user(),
            $request->header('X-Session-ID')
        );

        return ApiResponse::success(
            new StoreCartResource($cart),
            'Cart retrieved successfully.'
        );
    }

    public function addItem(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|integer|exists:store_products,id',
            'variant_id' => 'nullable|integer|exists:store_product_variants,id',
            'quantity' => 'nullable|integer|min:1',
        ]);

        $cart = $this->cartService->resolveCart(
            $request->user(),
            $request->header('X-Session-ID')
        );

        $updatedCart = $this->cartService->addItem(
            $cart,
            (int) $request->input('product_id'),
            $request->input('variant_id') ? (int) $request->input('variant_id') : null,
            (int) $request->input('quantity', 1)
        );

        return ApiResponse::success(
            new StoreCartResource($updatedCart),
            'Item added to cart.'
        );
    }

    public function updateItem(Request $request, string $itemUuid): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        $cart = $this->cartService->resolveCart(
            $request->user(),
            $request->header('X-Session-ID')
        );

        $updatedCart = $this->cartService->updateItemQuantity(
            $cart,
            $itemUuid,
            (int) $request->input('quantity')
        );

        return ApiResponse::success(
            new StoreCartResource($updatedCart),
            'Cart item updated.'
        );
    }

    public function removeItem(Request $request, string $itemUuid): JsonResponse
    {
        $cart = $this->cartService->resolveCart(
            $request->user(),
            $request->header('X-Session-ID')
        );

        $updatedCart = $this->cartService->removeItem($cart, $itemUuid);

        return ApiResponse::success(
            new StoreCartResource($updatedCart),
            'Item removed from cart.'
        );
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $this->cartService->resolveCart(
            $request->user(),
            $request->header('X-Session-ID')
        );

        $this->cartService->clearCart($cart);

        return ApiResponse::success(null, 'Cart cleared.');
    }
}
