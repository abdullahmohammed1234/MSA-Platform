<?php

namespace App\Ems\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class DuplicateRegistrationException extends EmsException
{
    public static function forEmail(string $email): self
    {
        return new self(
            'You are already registered for this event.',
            [
                'email' => [
                    sprintf('A registration already exists for %s on this event.', $email),
                ],
            ],
            Response::HTTP_CONFLICT
        );
    }
}
