<?php

namespace App\Store\Http\Controllers\Public;

use App\Ems\Support\ApiResponse;
use App\Http\Controllers\Controller;
use App\Store\Http\Requests\StoreCheckoutRequest;
use App\Store\Http\Resources\StoreOrderResource;
use App\Store\Services\StoreCartService;
use App\Store\Services\StoreCheckoutService;
use Illuminate\Http\JsonResponse;

class StoreCheckoutController extends Controller
{
    protected StoreCartService $cartService;
    protected StoreCheckoutService $checkoutService;

    public function __construct(StoreCartService $cartService, StoreCheckoutService $checkoutService)
    {
        $this->cartService = $cartService;
        $this->checkoutService = $checkoutService;
    }

    public function processCheckout(StoreCheckoutRequest $request): JsonResponse
    {
        $cart = $this->cartService->resolveCart(
            $request->user(),
            $request->header('X-Session-ID')
        );

        $result = $this->checkoutService->checkout(
            $cart,
            $request->validated(),
            $request->user(),
            $request->input('redirect_url', url('/store/checkout/success'))
        );

        return ApiResponse::created([
            'order' => new StoreOrderResource($result['order']),
            'checkout_url' => $result['checkout_url'],
        ], 'Checkout initialized successfully.');
    }
}
