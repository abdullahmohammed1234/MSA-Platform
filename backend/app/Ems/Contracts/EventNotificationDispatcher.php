<?php

namespace App\Ems\Contracts;

use App\Ems\Models\Event;
use App\Ems\Models\EventNotification;
use App\Ems\Models\Registration;
use Illuminate\Support\Carbon;

/**
 * The seam Phase 5 (communications) plugs into.
 *
 * Nothing implements this in Phase 1. Queueing, templating and campaign
 * scheduling all belong behind this boundary so the event lifecycle never
 * needs to know how a message is delivered.
 */
interface EventNotificationDispatcher
{
    /**
     * Queue a message to a single registration.
     *
     * @param  array<string, mixed>  $payload
     */
    public function notifyRegistration(
        Registration $registration,
        string $type,
        array $payload = [],
        ?Carbon $scheduledAt = null
    ): EventNotification;

    /**
     * Queue a message to every eligible registration on an event.
     *
     * @param  array<string, mixed>  $payload
     * @return int  the number of notifications queued
     */
    public function broadcastToEvent(Event $event, string $type, array $payload = []): int;
}
