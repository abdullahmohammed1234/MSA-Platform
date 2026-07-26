<?php

namespace App\Ems\Events;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base class for EMS domain events.
 *
 * Services dispatch these; App\Ems\Listeners\EmsActivitySubscriber turns them
 * into audit entries and structured logs. Later phases subscribe to the same
 * events to send confirmations, warm caches or push analytics without any
 * service having to know those consumers exist.
 */
abstract class EmsDomainEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly Model $subject,
        public readonly ?User $actor = null,
        public readonly array $context = [],
    ) {
    }

    /**
     * The audit action slug, e.g. `event.created`. The activity logger
     * namespaces it further with an `ems.` prefix.
     */
    abstract public function action(): string;

    /**
     * A human-readable summary written to the audit trail.
     */
    abstract public function description(): string;

    /**
     * Extra structured detail for the log record. Sensitive keys are stripped
     * downstream by EmsActivityLogger.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->context;
    }
}
