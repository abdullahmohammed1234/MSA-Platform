<?php

namespace App\Store\Services;

use App\Models\User;
use App\Services\Security\AuditLogger;
use App\Store\Enums\StoreFulfillmentStatus;
use App\Store\Models\StoreOrder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class StoreOrderService
{
    protected AuditLogger $auditLogger;
    protected StorePaymentService $paymentService;

    public function __construct(AuditLogger $auditLogger, StorePaymentService $paymentService)
    {
        $this->auditLogger = $auditLogger;
        $this->paymentService = $paymentService;
    }

    /**
     * Customer order history.
     */
    public function getUserOrders(User $user, int $perPage = 10): LengthAwarePaginator
    {
        return StoreOrder::query()
            ->with(['items.product.images'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(min(max($perPage, 1), 50));
    }

    /**
     * Admin order listing with filters.
     */
    public function listAdminOrders(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = StoreOrder::query()->with(['items.product', 'items.variant']);

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('square_payment_id', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['payment_status']) && $filters['payment_status'] !== 'all') {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (!empty($filters['fulfillment_status']) && $filters['fulfillment_status'] !== 'all') {
            $query->where('fulfillment_status', $filters['fulfillment_status']);
        }

        return $query->orderBy('created_at', 'desc')->paginate(min(max($perPage, 1), 100));
    }

    /**
     * Update order fulfillment status.
     */
    public function updateFulfillmentStatus(StoreOrder $order, StoreFulfillmentStatus $newStatus, ?int $adminUserId = null): StoreOrder
    {
        return DB::transaction(function () use ($order, $newStatus, $adminUserId) {
            $prevStatus = $order->fulfillment_status;

            $updateData = [
                'fulfillment_status' => $newStatus,
            ];

            if ($newStatus === StoreFulfillmentStatus::Completed) {
                $updateData['fulfilled_at'] = now();
            }

            $order->update($updateData);

            $this->auditLogger->log(
                action: 'store.order.fulfillment_updated',
                target: $order,
                description: "Updated fulfillment status of Order #{$order->order_number} to {$newStatus->value}",
                payload: ['previous' => $prevStatus->value, 'new' => $newStatus->value],
                userId: $adminUserId
            );

            return $order->fresh(['items']);
        });
    }
}
