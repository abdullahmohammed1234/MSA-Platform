<?php

namespace App\Ems\Events;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\EventTransition;
use App\Ems\Models\Event;
use App\Models\User;

/**
 * Dispatched after a lifecycle transition has been validated and persisted.
 *
 * This is the extension point later phases hook: Phase 5 sends "registration
 * is open" campaigns from here, Phase 6 feeds attendance analytics from it.
 */
class EventStatusChanged extends EmsDomainEvent
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        Event $event,
        public readonly EventStatus $from,
        public readonly EventStatus $to,
        public readonly EventTransition $transition,
        ?User $actor = null,
        array $context = [],
    ) {
        parent::__construct($event, $actor, $context);
    }

    public function action(): string
    {
        return 'event.status_changed';
    }

    public function description(): string
    {
        /** @var Event $event */
        $event = $this->subject;

        return sprintf(
            'Event "%s" moved from %s to %s.',
            $event->name,
            $this->from->label(),
            $this->to->label()
        );
    }

    public function payload(): array
    {
        /** @var Event $event */
        $event = $this->subject;

        return array_merge($this->context, [
            'event_uuid' => $event->uuid,
            'transition' => $this->transition->value,
            'from' => $this->from->value,
            'to' => $this->to->value,
        ]);
    }
}
