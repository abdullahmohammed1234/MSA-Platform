<?php

namespace App\Ems\Events;

use App\Ems\Models\Registration;

class RegistrationCreated extends EmsDomainEvent
{
    public function action(): string
    {
        return 'registration.created';
    }

    public function description(): string
    {
        /** @var Registration $registration */
        $registration = $this->subject;

        return sprintf(
            'Registration %s created for "%s".',
            $registration->reference,
            $registration->attendee_email
        );
    }

    public function payload(): array
    {
        /** @var Registration $registration */
        $registration = $this->subject;

        return array_merge($this->context, [
            'registration_uuid' => $registration->uuid,
            'reference' => $registration->reference,
            'event_id' => $registration->event_id,
            'status' => $registration->status->value,
            'type' => $registration->type->value,
        ]);
    }
}
