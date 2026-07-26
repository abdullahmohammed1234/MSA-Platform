<?php

namespace App\Ems\Events;

use App\Ems\Models\CheckIn;

class AttendeeCheckedIn extends EmsDomainEvent
{
    public function action(): string
    {
        return 'check_in.performed';
    }

    public function description(): string
    {
        /** @var CheckIn $checkIn */
        $checkIn = $this->subject;

        return sprintf('Attendee checked in via %s.', $checkIn->method->label());
    }

    public function payload(): array
    {
        /** @var CheckIn $checkIn */
        $checkIn = $this->subject;

        return array_merge($this->context, [
            'check_in_uuid' => $checkIn->uuid,
            'event_id' => $checkIn->event_id,
            'ticket_id' => $checkIn->ticket_id,
            'registration_id' => $checkIn->registration_id,
            'method' => $checkIn->method->value,
            'checked_in_at' => $checkIn->checked_in_at?->toIso8601String(),
        ]);
    }
}
