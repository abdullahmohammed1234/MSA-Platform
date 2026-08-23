<?php

namespace App\Ems\Jobs;

use App\Ems\Models\TicketType;
use App\Ems\Services\Square\SquareCatalogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued EMS → Square Catalog sync for one ticket type.
 *
 * Unique per ticket type so overlapping dispatches coalesce. The job always
 * loads current ticket state (not a frozen snapshot), so a delayed worker
 * pushes the latest EMS catalog fields rather than a stale mutation.
 */
class SyncTicketTypeToSquareJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** Seconds to keep the unique lock after the job finishes. */
    public int $uniqueFor = 120;

    public function __construct(
        public readonly int $ticketTypeId,
        public readonly bool $archive = false,
    ) {
        $this->onQueue((string) config('ems.payments.queue', 'ems-payments'));
    }

    public function uniqueId(): string
    {
        return 'ems-sq-cat-' . $this->ticketTypeId;
    }

    public function handle(SquareCatalogService $catalog): void
    {
        $ticketType = TicketType::query()->with('event')->find($this->ticketTypeId);
        if ($ticketType === null) {
            return;
        }

        // Never force-archive an active ticket: a stale unique job that was
        // queued during disable must not overwrite a later reactivation.
        $archive = $this->archive && ! $ticketType->is_active;

        try {
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.square.catalog.job_start', [
                    'ticket_type_id' => $this->ticketTypeId,
                    'ticket_type_uuid' => $ticketType->uuid,
                    'event_uuid' => $ticketType->event?->uuid,
                    'archive' => $archive,
                    'attempt' => $this->attempts(),
                ]);

            $catalog->syncTicketType($ticketType, $archive);
        } catch (\Throwable $e) {
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->error('ems.square.catalog.job_failed', [
                    'ticket_type_id' => $this->ticketTypeId,
                    'ticket_type_uuid' => $ticketType->uuid,
                    'event_uuid' => $ticketType->event?->uuid,
                    'attempt' => $this->attempts(),
                    'error' => $e->getMessage(),
                ]);

            throw $e;
        }
    }
}
