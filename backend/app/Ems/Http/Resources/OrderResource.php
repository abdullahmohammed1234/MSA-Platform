<?php

namespace App\Ems\Http\Resources;

use App\Ems\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'total_amount' => (float) $this->total_amount,
            'currency' => $this->currency,
            'buyer_name' => $this->buyer_name,
            'buyer_email' => $this->buyer_email,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'event' => $this->whenLoaded('event', fn () => [
                'uuid' => $this->event->uuid,
                'name' => $this->event->name,
                'slug' => $this->event->slug,
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'uuid' => $item->uuid,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
                'currency' => $item->currency,
            ])),
            'registrations' => $this->whenLoaded('registrations', fn () => $this->registrations->map(fn ($reg) => [
                'uuid' => $reg->uuid,
                'reference' => $reg->reference,
                'status' => $reg->status->value,
                'status_label' => $reg->status->label(),
            ])),
            'payment' => $this->whenLoaded('latestPayment', function () {
                $payment = $this->latestPayment;
                if ($payment === null) {
                    return null;
                }

                return [
                    'uuid' => $payment->uuid,
                    'status' => $payment->status->value,
                    'status_label' => $payment->status->label(),
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency,
                    'provider' => $payment->provider->value,
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                ];
            }),
        ];
    }
}
