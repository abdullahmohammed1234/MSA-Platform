<?php

namespace App\Ems\Events;

use App\Ems\Models\Registration;

class WalkInRegistered extends EmsDomainEvent
{
    public function action(): string
    {
        return 'registration.walk_in';
    }

    public function description(): string
    {
        /** @var Registration $registration */
        $registration = $this->subject;

        return sprintf('Walk-in registered: %s.', $registration->attendee_name);
    }

    public function payload(): array
    {
        /** @var Registration $registration */
        $registration = $this->subject;

        return array_merge($this->context, [
            'registration_uuid' => $registration->uuid,
            'event_id' => $registration->event_id,
            'attendee_email' => $registration->attendee_email,
            'status' => $registration->status->value,
        ]);
    }
}
