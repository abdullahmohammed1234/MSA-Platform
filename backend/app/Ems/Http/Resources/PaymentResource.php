<?php

namespace App\Ems\Http\Resources;

use App\Ems\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'amount' => (float) $this->amount,
            'amount_refunded' => (float) $this->amount_refunded,
            'currency' => $this->currency,
            'provider' => $this->provider->value,
            'provider_label' => $this->provider->label(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'failure_reason' => $this->failure_reason,
            'order' => $this->whenLoaded('order', fn () => $this->order ? [
                'uuid' => $this->order->uuid,
                'reference' => $this->order->reference,
                'status' => $this->order->status->value,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
