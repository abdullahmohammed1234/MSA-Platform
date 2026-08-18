<?php

namespace App\Ems\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * Check-in / ticket validation failures with stable machine-readable codes.
 */
class CheckInException extends EmsException
{
    public function __construct(
        string $message,
        private readonly string $resultCode,
        array $errors = [],
        ?int $status = null,
        private readonly array $context = [],
    ) {
        parent::__construct($message, $errors, $status ?? Response::HTTP_CONFLICT);
    }

    public function resultCode(): string
    {
        return $this->resultCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    public static function invalidQr(): self
    {
        return new self('Invalid QR Code', 'invalid_qr', [], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public static function ticketNotFound(): self
    {
        return new self('Ticket Not Found', 'ticket_not_found', [], Response::HTTP_NOT_FOUND);
    }

    public static function wrongEvent(string $ticketEventName = ''): self
    {
        return new self(
            'Wrong Event',
            'wrong_event',
            $ticketEventName !== '' ? ['event' => ["Ticket belongs to \"{$ticketEventName}\"."]] : [],
            Response::HTTP_CONFLICT,
            ['ticket_event_name' => $ticketEventName]
        );
    }

    public static function cancelledRegistration(): self
    {
        return new self('Cancelled Registration', 'cancelled_registration');
    }

    public static function refundedTicket(): self
    {
        return new self('Ticket refunded.', 'refunded_ticket');
    }

    public static function paymentRequired(): self
    {
        return new self('Payment Required', 'payment_required');
    }

    public static function waitlisted(): self
    {
        return new self('Attendee is waitlisted', 'waitlisted');
    }

    public static function inactiveTicket(string $statusLabel): self
    {
        return new self(
            sprintf('Ticket is not active (%s)', $statusLabel),
            'inactive_ticket',
            ['status' => [$statusLabel]]
        );
    }

    /**
     * @param  array<string, mixed>  $previous
     */
    public static function alreadyCheckedIn(array $previous = []): self
    {
        return new self(
            'Already Checked In',
            'already_checked_in',
            [],
            Response::HTTP_CONFLICT,
            $previous
        );
    }
}
