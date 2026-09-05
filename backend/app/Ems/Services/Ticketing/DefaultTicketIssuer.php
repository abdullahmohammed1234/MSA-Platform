<?php

namespace App\Ems\Services\Ticketing;

use App\Ems\Contracts\TicketIssuer;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\TicketStatus;
use App\Ems\Events\TicketIssued;
use App\Ems\Exceptions\EmsException;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 2 implementation of the TicketIssuer seam.
 *
 * Issues one ticket per seat on a confirmed (free) registration, generates a
 * unique code, and stamps a QR payload. Does not mark tickets as used —
 * redemption belongs to Phase 4.
 */
class DefaultTicketIssuer implements TicketIssuer
{
    public function __construct(
        private readonly TicketCodeGenerator $codes,
        private readonly QrCodeGenerator $qr,
    ) {
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function issueFor(Registration $registration): Collection
    {
        if (! config('ems.tickets.enabled', true)) {
            throw new EmsException(
                'Ticket issuance is currently disabled.',
                [],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        if ($registration->status !== RegistrationStatus::Confirmed) {
            throw new EmsException(
                'Tickets can only be issued for confirmed registrations.',
                ['status' => ['Registration must be confirmed before tickets are issued.']],
                Response::HTTP_CONFLICT
            );
        }

        return DB::transaction(function () use ($registration): Collection {
            $existing = $registration->tickets()->get();

            if ($existing->isNotEmpty()) {
                return $existing;
            }

            $quantity = max(1, (int) $registration->quantity);
            $tickets = collect();
            $attendees = $registration->metadata['attendees'] ?? [];

            for ($i = 0; $i < $quantity; $i++) {
                $holderName = $registration->attendee_name;
                $holderEmail = $registration->attendee_email;

                if (isset($attendees[$i]) && is_array($attendees[$i])) {
                    if (! empty($attendees[$i]['name'])) {
                        $holderName = trim((string) $attendees[$i]['name']);
                    } elseif (! empty($attendees[$i]['first_name']) || ! empty($attendees[$i]['last_name'])) {
                        $holderName = trim(($attendees[$i]['first_name'] ?? '') . ' ' . ($attendees[$i]['last_name'] ?? ''));
                    }

                    if (! empty($attendees[$i]['email'])) {
                        $holderEmail = strtolower(trim((string) $attendees[$i]['email']));
                    }
                }

                $ticket = new Ticket();
                $ticket->code = $this->codes->generate();
                $ticket->event_id = $registration->event_id;
                $ticket->registration_id = $registration->id;
                $ticket->ticket_type_id = $registration->ticket_type_id;
                $ticket->status = TicketStatus::Issued;
                $ticket->holder_name = $holderName;
                $ticket->holder_email = $holderEmail;
                $ticket->issued_at = now();
                $ticket->save();

                $this->generateQrPayload($ticket);

                TicketIssued::dispatch($ticket, $registration->user);

                $tickets->push($ticket->fresh());
            }

            return $tickets;
        });
    }

    public function generateQrPayload(Ticket $ticket): Ticket
    {
        if (! config('ems.tickets.qr_enabled', true)) {
            return $ticket;
        }

        $ticket->qr_payload = $this->qr->payloadFor($ticket);
        $ticket->qr_generated_at = now();
        $ticket->save();

        return $ticket->fresh();
    }

    public function revoke(Ticket $ticket, ?string $reason = null): Ticket
    {
        $ticket->status = TicketStatus::Revoked;
        $ticket->revoked_at = now();
        $ticket->save();

        return $ticket->fresh();
    }
}
