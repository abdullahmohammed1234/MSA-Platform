<?php

namespace App\Ems\Http\Resources;

use App\Ems\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Structured stale-capture representation for admin operations.
 *
 * @property-read Payment $resource
 */
class StaleCaptureResource extends JsonResource
{
    /**
     * @param  array<string, mixed>  $entry
     */
    public function __construct(
        Payment $payment,
        private readonly array $entry,
        private readonly ?User $resolvedBy = null,
    ) {
        parent::__construct($payment);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Payment $payment */
        $payment = $this->resource;
        $payment->loadMissing(['order.registrations', 'registration.ticketType', 'registration.tickets']);

        $order = $payment->order;
        $registration = $payment->registration ?? $order?->registrations->first();
        $event = $order?->event;
        $resolution = is_array($this->entry['resolution'] ?? null) ? $this->entry['resolution'] : [];

        return [
            'payment_uuid' => $payment->uuid,
            'payment_status' => $payment->status->value,
            'payment_status_label' => $payment->status->label(),
            'order_uuid' => $order?->uuid,
            'order_reference' => $order?->reference,
            'registration_uuid' => $registration?->uuid,
            'registration_status' => $registration?->status->value,
            'registration_status_label' => $registration?->status->label(),
            'attendee_name' => $registration?->attendee_name ?? $order?->buyer_name,
            'attendee_email' => $registration?->attendee_email ?? $order?->buyer_email,
            'event_uuid' => $event?->uuid,
            'event_name' => $event?->title,
            'event_missing' => $order !== null && $event === null,
            'checkout_amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'square_payment_id' => $this->entry['square_payment_id'] ?? null,
            'square_order_id' => $this->entry['square_order_id'] ?? null,
            'reported_at' => $this->entry['reported_at'] ?? null,
            'source' => $this->entry['source'] ?? null,
            'webhook_event_id' => $this->entry['webhook_event_id'] ?? null,
            'buyer_cancelled_at' => data_get($payment->metadata, 'buyer_cancelled_at'),
            'ticket_count' => $registration?->tickets?->count() ?? 0,
            'resolution_status' => (string) ($resolution['status'] ?? 'unresolved'),
            'resolved_at' => $resolution['resolved_at'] ?? null,
            'resolved_by_user_id' => $resolution['resolved_by_user_id'] ?? null,
            'resolved_by_name' => $this->resolvedBy?->name,
            'resolution_reason' => $resolution['reason'] ?? null,
            'square_refund_uuid' => $resolution['square_refund_uuid'] ?? null,
            'amount_refunded' => isset($resolution['amount_refunded'])
                ? (float) $resolution['amount_refunded']
                : null,
            'remaining_refundable_amount' => isset($resolution['remaining_refundable_amount'])
                ? (float) $resolution['remaining_refundable_amount']
                : null,
        ];
    }
}
