<?php

namespace App\Ems\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class CapacityExceededException extends EmsException
{
    public static function make(?int $remaining = null): self
    {
        $message = $remaining === 0 || $remaining === null
            ? 'This event is at full capacity.'
            : sprintf('Only %d seat(s) remain for this event.', $remaining);

        return new self(
            $message,
            ['capacity' => [$message]],
            Response::HTTP_CONFLICT
        );
    }
}
