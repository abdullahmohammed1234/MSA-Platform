<?php

namespace App\Jobs\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProcessAnalyticsEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     * Set to 0 for infinite attempts (relying on retryUntil safeguard).
     */
    public $tries = 0;

    protected array $eventData;

    /**
     * Create a new job instance.
     */
    public function __construct(array $eventData)
    {
        $this->eventData = $eventData;
        $this->queue = 'low';
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        // Retry for up to 24 hours if database is locked/down
        return now()->addHours(24);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::debug("Processing raw analytics event in background: " . ($this->eventData['event_name'] ?? 'unknown'));

        $session = null;
        if (!empty($this->eventData['session_uuid'])) {
            $session = AnalyticsSession::where('uuid', $this->eventData['session_uuid'])->first();
        }

        AnalyticsEvent::create([
            'uuid' => $this->eventData['uuid'] ?? (string) Str::uuid(),
            'user_id' => $this->eventData['user_id'] ?? null,
            'session_id' => $session ? $session->id : null,
            'module' => $this->eventData['module'] ?? 'unknown',
            'event_type' => $this->eventData['event_type'] ?? 'custom',
            'event_name' => $this->eventData['event_name'] ?? 'custom_event',
            'entity_type' => $this->eventData['entity_type'] ?? null,
            'entity_id' => $this->eventData['entity_id'] ?? null,
            'metadata' => $this->eventData['metadata'] ?? null,
            'occurred_at' => $this->eventData['occurred_at'] ?? now(),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("Failed to process analytics event: " . $exception->getMessage());
    }
}
