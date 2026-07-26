<?php

namespace App\Ems\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Ems\Models\EventNotification */
class EventNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'channel' => $this->channel?->value ?? $this->channel,
            'status' => $this->status?->value ?? $this->status,
            'queue_status' => $this->queue_status,
            'subject' => $this->subject,
            'recipient_email' => $this->recipient_email,
            'template_key' => $this->template_key,
            'retry_count' => $this->retry_count,
            'error' => $this->error,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'queued_at' => $this->queued_at?->toIso8601String(),
            'last_attempt_at' => $this->last_attempt_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'registration' => $this->whenLoaded('registration', fn () => [
                'uuid' => $this->registration?->uuid,
                'reference' => $this->registration?->reference,
                'attendee_name' => $this->registration?->attendee_name,
                'attendee_email' => $this->registration?->attendee_email,
            ]),
            'event_uuid' => $this->whenLoaded('event', fn () => $this->event?->uuid),
        ];
    }
}
