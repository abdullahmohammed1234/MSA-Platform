<?php

namespace App\Ems\Services;

use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\WaitlistStatus;
use App\Ems\Models\Event;
use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Models\WaitlistEntry;
use Illuminate\Support\Facades\DB;

/**
 * Lightweight organizer payment / capacity summary (Phase 6 owns deep analytics).
 */
class EventPaymentSummaryService
{
    /**
     * @return array<string, mixed>
     */
    public function summarize(Event $event): array
    {
        $paidOrders = Order::query()
            ->where('event_id', $event->id)
            ->where('status', OrderStatus::Completed->value)
            ->count();

        $pendingPayments = Payment::query()
            ->whereHas('order', fn ($q) => $q->where('event_id', $event->id))
            ->whereIn('status', [
                PaymentStatus::Pending->value,
                PaymentStatus::Processing->value,
                PaymentStatus::Authorized->value,
            ])
            ->count();

        $failedPayments = Payment::query()
            ->whereHas('order', fn ($q) => $q->where('event_id', $event->id))
            ->whereIn('status', [
                PaymentStatus::Failed->value,
                PaymentStatus::Cancelled->value,
            ])
            ->count();

        $revenue = (float) Payment::query()
            ->whereHas('order', fn ($q) => $q->where('event_id', $event->id))
            ->where('status', PaymentStatus::Paid->value)
            ->sum('amount');

        $waitlistCount = WaitlistEntry::query()
            ->where('event_id', $event->id)
            ->where('status', WaitlistStatus::Waiting->value)
            ->count();

        $ticketTypes = $event->ticketTypes()
            ->ordered()
            ->get()
            ->map(fn ($type) => [
                'uuid' => $type->uuid,
                'name' => $type->name,
                'price' => (float) $type->price,
                'currency' => $type->currency,
                'quantity' => $type->quantity,
                'quantity_sold' => $type->quantity_sold,
                'remaining' => $type->remainingQuantity(),
                'is_active' => $type->is_active,
                'is_sold_out' => $type->isSoldOut(),
            ]);

        return [
            'capacity' => $event->capacity,
            'remaining_capacity' => $event->remainingCapacity(),
            'tickets_sold' => (int) $event->ticketTypes()->sum('quantity_sold'),
            'paid_orders' => $paidOrders,
            'pending_payments' => $pendingPayments,
            'failed_payments' => $failedPayments,
            'revenue' => round($revenue, 2),
            'currency' => (string) config('ems.defaults.currency', 'CAD'),
            'waitlist_enabled' => (bool) $event->waitlist_enabled,
            'waitlist_count' => $waitlistCount,
            'ticket_types' => $ticketTypes,
        ];
    }
}
