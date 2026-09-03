<?php

namespace App\Store\Http\Controllers\Admin;

use App\Ems\Support\ApiResponse;
use App\Http\Controllers\Controller;
use App\Store\Enums\StoreFulfillmentStatus;
use App\Store\Http\Resources\StoreOrderResource;
use App\Store\Models\StoreOrder;
use App\Store\Services\StoreOrderService;
use App\Store\Services\StorePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStoreOrderController extends Controller
{
    protected StoreOrderService $orderService;
    protected StorePaymentService $paymentService;

    public function __construct(StoreOrderService $orderService, StorePaymentService $paymentService)
    {
        $this->orderService = $orderService;
        $this->paymentService = $paymentService;
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StoreOrder::class);

        $paginator = $this->orderService->listAdminOrders(
            $request->only(['search', 'payment_status', 'fulfillment_status']),
            (int) $request->query('per_page', 15)
        );

        return ApiResponse::paginated(
            $paginator,
            'Store orders retrieved successfully.',
            StoreOrderResource::class
        );
    }

    public function show(StoreOrder $order): JsonResponse
    {
        $this->authorize('view', $order);

        $order->load(['items.product', 'items.variant']);

        return ApiResponse::success(
            new StoreOrderResource($order),
            'Order details retrieved successfully.'
        );
    }

    public function updateFulfillment(Request $request, StoreOrder $order): JsonResponse
    {
        $this->authorize('update', $order);

        $request->validate([
            'fulfillment_status' => 'required|string|in:' . implode(',', array_column(StoreFulfillmentStatus::cases(), 'value')),
        ]);

        $newStatus = StoreFulfillmentStatus::from($request->input('fulfillment_status'));

        $updated = $this->orderService->updateFulfillmentStatus(
            $order,
            $newStatus,
            $request->user()?->id
        );

        return ApiResponse::success(
            new StoreOrderResource($updated),
            "Fulfillment status updated to {$newStatus->label()}."
        );
    }

    public function refund(Request $request, StoreOrder $order): JsonResponse
    {
        $this->authorize('refund', $order);

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $refundedOrder = $this->paymentService->refundOrder(
            $order,
            $request->input('reason'),
            $request->user()?->id
        );

        return ApiResponse::success(
            new StoreOrderResource($refundedOrder),
            'Store order refunded successfully.'
        );
    }
}
