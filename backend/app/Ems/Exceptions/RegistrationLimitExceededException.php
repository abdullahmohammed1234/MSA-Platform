<?php

namespace App\Ems\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class RegistrationLimitExceededException extends EmsException
{
    public static function perOrder(int $max): self
    {
        return new self(
            "You may purchase at most {$max} ticket(s) per order.",
            ['quantity' => ["Maximum {$max} tickets per order."]],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    public static function perAttendee(int $max): self
    {
        return new self(
            "You may register for at most {$max} ticket(s) for this event.",
            ['email' => ["Maximum {$max} registrations per attendee."]],
            Response::HTTP_CONFLICT
        );
    }

    public static function deadlinePassed(): self
    {
        return new self(
            'The registration deadline for this event has passed.',
            ['event' => ['Registration deadline has passed.']],
            Response::HTTP_CONFLICT
        );
    }
}
