<?php

namespace App\Ems\Events;

use App\Ems\Models\Ticket;

class TicketIssued extends EmsDomainEvent
{
    public function action(): string
    {
        return 'ticket.issued';
    }

    public function description(): string
    {
        /** @var Ticket $ticket */
        $ticket = $this->subject;

        return sprintf('Ticket %s was issued.', $ticket->code);
    }

    public function payload(): array
    {
        /** @var Ticket $ticket */
        $ticket = $this->subject;

        return array_merge($this->context, [
            'ticket_uuid' => $ticket->uuid,
            'code' => $ticket->code,
            'event_id' => $ticket->event_id,
            'registration_id' => $ticket->registration_id,
            'status' => $ticket->status->value,
        ]);
    }
}
