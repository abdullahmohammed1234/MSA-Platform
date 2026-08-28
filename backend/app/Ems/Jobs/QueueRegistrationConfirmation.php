<?php

namespace App\Ems\Jobs;

use App\Ems\Models\Registration;
use App\Ems\Services\Notifications\EventCommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queues the registration confirmation communication bundle (async).
 */
class QueueRegistrationConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $registrationId,
        public readonly bool $includePayment = false,
    ) {
        $this->afterCommit = true;
        $this->onQueue((string) config('ems.notifications.queue', 'ems-notifications'));
    }

    public function handle(EventCommunicationService $communications): void
    {
        $registration = Registration::query()
            ->with(['event', 'tickets', 'ticketType', 'order', 'settledPayment'])
            ->find($this->registrationId);

        if ($registration === null) {
            return;
        }

        $communications->sendRegistrationBundle($registration, $this->includePayment);

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.notifications.confirmation_queued', [
                'registration_uuid' => $registration->uuid,
            ]);
    }
}
