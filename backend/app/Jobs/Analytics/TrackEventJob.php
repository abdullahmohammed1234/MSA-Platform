<?php

namespace App\Jobs\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class TrackEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $eventData;

    /**
     * Create a new job instance.
     */
    public function __construct(array $eventData)
    {
        $this->eventData = $eventData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $session = null;
        if (!empty($this->eventData['session_uuid'])) {
            $session = AnalyticsSession::where('uuid', $this->eventData['session_uuid'])->first();
        }

        AnalyticsEvent::create([
            'uuid' => $this->eventData['uuid'] ?? (string) Str::uuid(),
            'user_id' => $this->eventData['user_id'] ?? null,
            'session_id' => $session ? $session->id : null,
            'module' => $this->eventData['module'],
            'event_type' => $this->eventData['event_type'],
            'event_name' => $this->eventData['event_name'],
            'entity_type' => $this->eventData['entity_type'] ?? null,
            'entity_id' => $this->eventData['entity_id'] ?? null,
            'metadata' => $this->eventData['metadata'] ?? null,
            'occurred_at' => $this->eventData['occurred_at'] ?? now(),
        ]);
    }
}
