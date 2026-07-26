<?php

namespace App\Ems\Services;

use App\Ems\Contracts\TicketIssuer;
use App\Ems\Models\Event;
use App\Ems\Models\Registration;
use App\Ems\Services\Ticketing\TicketCodeGenerator;
use App\Models\User;

/**
 * Free-event registration workflow (Phase 2).
 *
 * Delegates to CheckoutService so free and paid paths share capacity,
 * ticket-type and order rules without diverging implementations.
 */
class RegistrationService
{
    public function __construct(
        private readonly CheckoutService $checkout,
    ) {
    }

    /**
     * Register an attendee for a free public event.
     *
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     phone?: string|null,
     *     student_id?: string|null,
     *     notes?: string|null,
     *     quantity?: int,
     *     ticket_type_id?: string|null
     * }  $data
     */
    public function registerFree(Event $event, array $data, ?User $user = null): Registration
    {
        return $this->checkout->registerFree($event, $data, $user);
    }
}
