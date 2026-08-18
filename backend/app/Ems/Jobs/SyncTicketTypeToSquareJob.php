<?php

namespace App\Ems\Jobs;

use App\Ems\Models\TicketType;
use App\Ems\Services\Square\SquareCatalogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncTicketTypeToSquareJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $ticketTypeId,
        public readonly bool $archive = false,
    ) {
        $this->onQueue((string) config('ems.payments.queue', 'ems-payments'));
    }

    public function handle(SquareCatalogService $catalog): void
    {
        $ticketType = TicketType::query()->with('event')->find($this->ticketTypeId);
        if ($ticketType === null) {
            return;
        }

        try {
            $catalog->syncTicketType($ticketType, $this->archive);
        } catch (\Throwable $e) {
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->error('ems.square.catalog.job_failed', [
                    'ticket_type_id' => $this->ticketTypeId,
                    'error' => $e->getMessage(),
                ]);

            throw $e;
        }
    }
}
