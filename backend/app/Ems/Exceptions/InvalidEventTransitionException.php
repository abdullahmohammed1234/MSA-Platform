<?php

namespace App\Ems\Exceptions;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\EventTransition;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when a lifecycle transition is not legal from the event's current
 * state. Always a 409 — the request was well formed, the resource just is not
 * in a state that permits it.
 */
class InvalidEventTransitionException extends EmsException
{
    public static function forTransition(EventStatus $from, EventTransition $transition): self
    {
        $allowed = array_map(
            fn (EventTransition $t): string => $t->value,
            EventTransition::availableFrom($from)
        );

        return new self(
            sprintf(
                'Cannot %s an event that is %s.',
                str_replace('_', ' ', $transition->value),
                $from->label()
            ),
            [
                'status' => [
                    sprintf(
                        'The event is currently "%s". Allowed transitions from this state: %s.',
                        $from->value,
                        $allowed === [] ? 'none' : implode(', ', $allowed)
                    ),
                ],
            ],
            Response::HTTP_CONFLICT
        );
    }
}
