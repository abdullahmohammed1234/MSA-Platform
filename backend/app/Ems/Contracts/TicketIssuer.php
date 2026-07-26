<?php

namespace App\Ems\Contracts;

use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use Illuminate\Support\Collection;

/**
 * The seam Phase 2 ticket issuance plugs into.
 *
 * Nothing implements this in Phase 1. It defines where "confirmed registration
 * becomes a scannable ticket" will live, so that logic never leaks into the
 * registration or payment code paths.
 */
interface TicketIssuer
{
    /**
     * Issue one ticket per seat on a confirmed registration.
     *
     * @return Collection<int, Ticket>
     */
    public function issueFor(Registration $registration): Collection;

    /**
     * Generate (or regenerate) the QR payload for a ticket.
     */
    public function generateQrPayload(Ticket $ticket): Ticket;

    /**
     * Invalidate a ticket, e.g. after a cancellation or refund.
     */
    public function revoke(Ticket $ticket, ?string $reason = null): Ticket;
}
