<?php

namespace App\Ems\Events;

use App\Ems\Models\Event;

class EventDeleted extends EmsDomainEvent
{
    public function action(): string
    {
        return 'event.deleted';
    }

    public function description(): string
    {
        /** @var Event $event */
        $event = $this->subject;

        return sprintf('Event "%s" was deleted.', $event->name);
    }

    public function payload(): array
    {
        /** @var Event $event */
        $event = $this->subject;

        return array_merge($this->context, [
            'event_uuid' => $event->uuid,
            'status_at_deletion' => $event->status->value,
        ]);
    }
}
