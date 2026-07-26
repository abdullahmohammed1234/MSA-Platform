<?php

namespace App\Ems\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class TicketUnavailableException extends EmsException
{
    public static function soldOut(): self
    {
        return new self(
            'This ticket type is sold out.',
            ['ticket_type_id' => ['Sold out.']],
            Response::HTTP_CONFLICT
        );
    }

    public static function notOnSale(): self
    {
        return new self(
            'This ticket type is not currently on sale.',
            ['ticket_type_id' => ['Sales are closed for this ticket.']],
            Response::HTTP_CONFLICT
        );
    }

    public static function insufficient(int $remaining): self
    {
        return new self(
            $remaining <= 0
                ? 'This ticket type is sold out.'
                : "Only {$remaining} ticket(s) remaining for this type.",
            ['quantity' => ["Only {$remaining} remaining."]],
            Response::HTTP_CONFLICT
        );
    }
}
