<?php

namespace App\Ems\Http\Resources\Public;

use App\Ems\Enums\RegistrationStatus;
use App\Ems\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Confirmation payload after a successful free registration.
 *
 * @mixin Registration
 */
class PublicRegistrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'status_label' => $this->status->value === 'confirmed' ? 'Registered' : $this->status->label(),
            'type' => $this->type->value,
            'attendee_name' => $this->attendee_name,
            'attendee_email' => $this->attendee_email,
            'quantity' => $this->quantity,
            'amount_due' => (float) $this->amount_due,
            'currency' => $this->currency,
            'registered_at' => $this->registered_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),

            'ticket_type' => $this->whenLoaded('ticketType', fn () => $this->ticketType ? [
                'uuid' => $this->ticketType->uuid,
                'name' => $this->ticketType->name,
            ] : null),

            'pending_checkout' => $this->when(
                $this->status === RegistrationStatus::AwaitingPayment && $this->relationLoaded('order'),
                function () {
                    $payment = $this->order?->latestPayment;

                    return [
                        'order_uuid' => $this->order?->uuid,
                        'checkout_url' => $payment?->checkout_url,
                        'amount' => (float) ($payment?->amount ?? $this->amount_due),
                        'currency' => $payment?->currency ?? $this->currency,
                        'checkout_version' => $payment ? (int) $payment->checkout_version : null,
                        'checkout_expires_at' => $payment?->checkout_expires_at?->toIso8601String(),
                    ];
                }
            ),

            'event' => $this->whenLoaded('event', fn () => [
                'uuid' => $this->event->uuid,
                'name' => $this->event->name,
                'slug' => $this->event->slug,
                'start_at' => $this->event->start_at?->toIso8601String(),
                'location' => $this->event->location,
            ]),

            'tickets' => PublicTicketResource::collection($this->whenLoaded('tickets')),
        ];
    }
}
