<?php

namespace App\Ems\Exceptions;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base class for EMS domain failures that are safe to surface to the client.
 *
 * Anything thrown as an EmsException carries a message written for an end
 * user, so the exception handler can render it verbatim without leaking
 * implementation detail.
 */
class EmsException extends RuntimeException
{
    /** @var array<string, array<int, string>> */
    protected array $errors = [];

    protected int $status = Response::HTTP_BAD_REQUEST;

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(string $message, array $errors = [], ?int $status = null)
    {
        parent::__construct($message);

        $this->errors = $errors;

        if ($status !== null) {
            $this->status = $status;
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function status(): int
    {
        return $this->status;
    }
}
