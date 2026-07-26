<?php

namespace App\Ems\Events;

use App\Ems\Models\CheckInAudit;

class CheckInUndone extends EmsDomainEvent
{
    public function action(): string
    {
        return 'check_in.undone';
    }

    public function description(): string
    {
        return 'Check-in was undone.';
    }

    public function payload(): array
    {
        /** @var CheckInAudit $audit */
        $audit = $this->subject;

        return array_merge($this->context, [
            'audit_uuid' => $audit->uuid,
            'event_id' => $audit->event_id,
            'ticket_id' => $audit->ticket_id,
            'registration_id' => $audit->registration_id,
            'reason' => $audit->context['reason'] ?? null,
        ]);
    }
}
