<?php

namespace App\Ems\Http\Resources\Public;

use App\Ems\Models\Ticket;
use App\Ems\Services\Ticketing\QrCodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Attendee-facing ticket payload. Never exposes internal numeric IDs or
 * unrelated registration metadata.
 *
 * @mixin Ticket
 */
class PublicTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $includeQrImage = (bool) $request->boolean('include_qr_image', true);
        $qr = app(QrCodeGenerator::class);

        return [
            'code' => $this->code,
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'holder_name' => $this->holder_name,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'qr_payload' => $this->qr_payload,
            'qr_image' => $this->when(
                $includeQrImage && config('ems.tickets.qr_enabled', true),
                fn () => $qr->dataUri($this->resource)
            ),

            'event' => $this->whenLoaded('event', fn () => [
                'uuid' => $this->event->uuid,
                'name' => $this->event->name,
                'slug' => $this->event->slug,
                'location' => $this->event->location,
                'start_at' => $this->event->start_at?->toIso8601String(),
                'end_at' => $this->event->end_at?->toIso8601String(),
                'timezone' => $this->event->timezone,
                'category' => $this->event->relationLoaded('category') && $this->event->category
                    ? [
                        'name' => $this->event->category->name,
                        'slug' => $this->event->category->slug,
                        'color' => $this->event->category->color,
                    ]
                    : null,
            ]),

            'registration' => $this->whenLoaded('registration', fn () => [
                'reference' => $this->registration->reference,
                'status' => $this->registration->status->value,
                'status_label' => $this->registration->status->value === 'confirmed'
                    ? 'Registered'
                    : $this->registration->status->label(),
                'registered_at' => $this->registration->registered_at?->toIso8601String(),
            ]),
        ];
    }
}
