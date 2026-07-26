<?php

namespace App\Ems\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class TicketNotValidException extends EmsException
{
    public static function notFound(): self
    {
        return new self(
            'Ticket not found.',
            [],
            Response::HTTP_NOT_FOUND
        );
    }

    public static function inactive(string $statusLabel): self
    {
        return new self(
            sprintf('This ticket is not active (status: %s).', $statusLabel),
            ['status' => [sprintf('Ticket status is "%s".', $statusLabel)]],
            Response::HTTP_CONFLICT
        );
    }
}
