<?php

namespace App\Ems\Events;

use App\Ems\Models\Event;

class EventCreated extends EmsDomainEvent
{
    public function action(): string
    {
        return 'event.created';
    }

    public function description(): string
    {
        /** @var Event $event */
        $event = $this->subject;

        return sprintf('Event "%s" was created.', $event->name);
    }

    public function payload(): array
    {
        /** @var Event $event */
        $event = $this->subject;

        return array_merge($this->context, [
            'event_uuid' => $event->uuid,
            'slug' => $event->slug,
            'status' => $event->status->value,
        ]);
    }
}
