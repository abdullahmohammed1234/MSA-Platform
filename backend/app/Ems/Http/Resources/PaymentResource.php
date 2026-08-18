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
            'source_channel' => $this->source_channel,
            'source_channel_label' => \App\Ems\Enums\PaymentSourceChannel::tryFrom((string) $this->source_channel)?->label() ?? $this->source_channel,
            'provider_payment_id' => $this->provider_payment_id,
            'provider_order_id' => $this->provider_order_id,
            'provider_checkout_id' => $this->provider_checkout_id,
            'terminal_checkout_id' => $this->terminal_checkout_id,
            'checkout_url' => $this->checkout_url,
            'checkout_expires_at' => $this->checkout_expires_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'failure_reason' => $this->failure_reason,
            'refunds' => $this->whenLoaded('squareRefunds', fn () => $this->squareRefunds->map(fn ($refund) => [
                'uuid' => $refund->uuid,
                'status' => $refund->status->value,
                'status_label' => $refund->status->label(),
                'amount' => (float) $refund->amount,
                'currency' => $refund->currency,
                'provider_refund_id' => $refund->provider_refund_id,
                'reason' => $refund->reason,
            ])),
            'order' => $this->whenLoaded('order', fn () => $this->order ? [
                'uuid' => $this->order->uuid,
                'reference' => $this->order->reference,
                'status' => $this->order->status->value,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
