<?php

namespace App\Ems\Services\Operations;

use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\TicketStatus;
use App\Ems\Exceptions\CheckInException;
use App\Ems\Models\CheckIn;
use App\Ems\Models\Event;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;

/**
 * Validates a ticket for a specific event without redeeming it.
 */
class TicketValidationService
{
    /**
     * @return array{
     *     ok: bool,
     *     code: string,
     *     message: string,
     *     ticket: Ticket|null,
     *     registration: Registration|null,
     *     check_in: CheckIn|null
     * }
     */
    public function validate(Event $event, string $rawCode): array
    {
        $code = $this->extractCode($rawCode);

        if ($code === '') {
            throw CheckInException::invalidQr();
        }

        $ticket = Ticket::query()
            ->where('code', $code)
            ->with(['event', 'registration.payments', 'checkIn.checkedInBy'])
            ->first();

        if ($ticket === null) {
            throw CheckInException::ticketNotFound();
        }

        if ((int) $ticket->event_id !== (int) $event->id) {
            throw CheckInException::wrongEvent($ticket->event?->name ?? '');
        }

        $registration = $ticket->registration;

        if ($registration === null) {
            throw CheckInException::ticketNotFound();
        }

        if ($registration->status === RegistrationStatus::Cancelled) {
            throw CheckInException::cancelledRegistration();
        }

        if (
            $registration->status === RegistrationStatus::Refunded
            || $ticket->status === TicketStatus::Revoked
        ) {
            throw CheckInException::refundedTicket();
        }

        if ($registration->status === RegistrationStatus::Waitlisted) {
            throw CheckInException::waitlisted();
        }

        if (
            $registration->status === RegistrationStatus::AwaitingPayment
            || $this->paymentStillRequired($registration)
        ) {
            throw CheckInException::paymentRequired();
        }

        if ($ticket->status === TicketStatus::Redeemed || $ticket->checkIn !== null) {
            $checkIn = $ticket->checkIn;

            throw CheckInException::alreadyCheckedIn([
                'checked_in_at' => $checkIn?->checked_in_at?->toIso8601String(),
                'staff_name' => $checkIn?->checkedInBy?->name,
                'check_in_uuid' => $checkIn?->uuid,
                'method' => $checkIn?->method?->value,
            ]);
        }

        if ($ticket->status !== TicketStatus::Issued) {
            throw CheckInException::inactiveTicket($ticket->status->label());
        }

        if ($registration->status !== RegistrationStatus::Confirmed
            && $registration->status !== RegistrationStatus::Pending
        ) {
            throw CheckInException::inactiveTicket($registration->status->label());
        }

        return [
            'ok' => true,
            'code' => 'valid',
            'message' => 'Ticket is valid.',
            'ticket' => $ticket,
            'registration' => $registration,
            'check_in' => null,
        ];
    }

    public function extractCode(string $raw): string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return '';
        }

        // Full QR URL → last path segment
        if (str_contains($raw, '/')) {
            $path = parse_url($raw, PHP_URL_PATH) ?: $raw;
            $segments = array_values(array_filter(explode('/', $path)));
            $raw = end($segments) ?: $raw;
        }

        // Strip query/fragment leftovers
        $raw = preg_replace('/[?#].*$/', '', $raw) ?? $raw;

        return strtoupper(trim($raw));
    }

    private function paymentStillRequired(Registration $registration): bool
    {
        if ($registration->type->value !== 'paid') {
            return false;
        }

        if ($registration->status === RegistrationStatus::Confirmed) {
            return false;
        }

        $paid = $registration->payments
            ->contains(fn ($payment) => $payment->status === PaymentStatus::Paid);

        return ! $paid;
    }
}
