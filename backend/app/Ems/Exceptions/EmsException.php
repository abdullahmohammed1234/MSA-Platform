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

    protected ?string $providerCode = null;

    protected ?string $providerCategory = null;

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        string $message,
        array $errors = [],
        ?int $status = null,
        ?string $providerCode = null,
        ?string $providerCategory = null
    ) {
        $code = $status ?? Response::HTTP_BAD_REQUEST;
        parent::__construct($message, $code);

        $this->errors = $errors;
        $this->status = $code;
        $this->providerCode = $providerCode;
        $this->providerCategory = $providerCategory;
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

    public function providerCode(): ?string
    {
        return $this->providerCode;
    }

    public function providerCategory(): ?string
    {
        return $this->providerCategory;
    }
}
