<?php

namespace App\Ems\Events;

use App\Ems\Models\Event;

class EventUpdated extends EmsDomainEvent
{
    public function action(): string
    {
        return 'event.updated';
    }

    public function description(): string
    {
        /** @var Event $event */
        $event = $this->subject;

        return sprintf('Event "%s" was updated.', $event->name);
    }

    public function payload(): array
    {
        /** @var Event $event */
        $event = $this->subject;

        return array_merge($this->context, [
            'event_uuid' => $event->uuid,
        ]);
    }
}
