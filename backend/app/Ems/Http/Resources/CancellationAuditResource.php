<?php

namespace App\Ems\Http\Resources;

use App\Ems\Models\CancellationAudit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CancellationAudit
 */
class CancellationAuditResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'registration_uuid' => $this->registration?->uuid,
            'registration_reference' => $this->registration?->reference,
            'event_uuid' => $this->event?->uuid,
            'event_name' => $this->event?->name,
            'ticket_type_name' => $this->ticket_type_name,
            'attendee_name' => $this->attendee_name,
            'attendee_email' => $this->attendee_email,
            'initiator_type' => $this->initiator_type,
            'initiator_name' => $this->user?->name ?? ($this->initiator_type === 'guest' ? 'Guest' : null),
            'initiator_email' => $this->user?->email,
            'reason' => $this->reason,
            'refund_requested' => $this->refund_requested,
            'refund_amount' => $this->refund_amount !== null ? (float) $this->refund_amount : null,
            'refund_status' => $this->refund_status->value,
            'refund_status_label' => $this->refund_status->label(),
            'provider_refund_id' => $this->provider_refund_id,
            'square_refund_uuid' => $this->squareRefund?->uuid,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
        ];
    }
}

