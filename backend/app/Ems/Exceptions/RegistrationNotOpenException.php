<?php

namespace App\Ems\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class RegistrationNotOpenException extends EmsException
{
    public static function forEvent(string $statusLabel): self
    {
        return new self(
            sprintf('Registration is not open for this event (current status: %s).', $statusLabel),
            ['registration' => ['This event is not currently accepting registrations.']],
            Response::HTTP_CONFLICT
        );
    }
}
