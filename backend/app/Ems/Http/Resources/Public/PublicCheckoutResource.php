<?php

namespace App\Ems\Http\Resources\Public;

use App\Ems\Http\Resources\OrderResource;
use App\Ems\Http\Resources\PaymentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicCheckoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{order: mixed, registration: mixed, checkout_url: ?string, payment: mixed} $data */
        $data = $this->resource;

        return [
            'checkout_url' => $data['checkout_url'],
            'requires_payment' => $data['checkout_url'] !== null,
            'order' => new OrderResource($data['order']->loadMissing(['items', 'event', 'latestPayment'])),
            'registration' => new PublicRegistrationResource(
                $data['registration']->loadMissing(['tickets.event.category', 'event', 'ticketType'])
            ),
            'payment' => $data['payment']
                ? new PaymentResource($data['payment'])
                : null,
        ];
    }
}
