<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Http\Resources\OrderResource;
use App\Ems\Models\Order;
use App\Ems\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class OrderController extends EmsController
{
    /**
     * GET /api/v1/ems/orders/{order}
     */
    public function show(Order $order): JsonResponse
    {
        $order->loadMissing(['event', 'items', 'registrations', 'latestPayment']);
        $this->authorize('view', $order->event);

        return ApiResponse::success(
            new OrderResource($order),
            'Order retrieved successfully.'
        );
    }
}
