<?php

namespace App\Store\Http\Controllers\Public;

use App\Ems\Support\ApiResponse;
use App\Http\Controllers\Controller;
use App\Store\Http\Resources\StoreOrderResource;
use App\Store\Models\StoreOrder;
use App\Store\Services\StoreOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreCustomerOrderController extends Controller
{
    protected StoreOrderService $orderService;

    public function __construct(StoreOrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->orderService->getUserOrders(
            $request->user(),
            (int) $request->query('per_page', 10)
        );

        return ApiResponse::paginated(
            $paginator,
            'My orders retrieved successfully.',
            StoreOrderResource::class
        );
    }

    public function show(Request $request, StoreOrder $order): JsonResponse
    {
        $this->authorize('view', $order);

        $order->load(['items.product', 'items.variant']);

        return ApiResponse::success(
            new StoreOrderResource($order),
            'Order details retrieved successfully.'
        );
    }
}
