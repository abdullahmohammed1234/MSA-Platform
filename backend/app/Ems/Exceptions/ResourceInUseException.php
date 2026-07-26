<?php

namespace App\Ems\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when a resource cannot be removed because other records still depend
 * on it, e.g. deleting an event category that still has events attached.
 */
class ResourceInUseException extends EmsException
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(string $message, array $errors = [])
    {
        parent::__construct($message, $errors, Response::HTTP_CONFLICT);
    }
}
