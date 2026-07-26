<?php

namespace App\Ems\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class InvalidPaymentTransitionException extends EmsException
{
    public static function make(string $from, string $to): self
    {
        return new self(
            "Cannot transition payment from {$from} to {$to}.",
            ['status' => ["Invalid payment transition: {$from} → {$to}."]],
            Response::HTTP_CONFLICT
        );
    }
}
