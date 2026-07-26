<?php

namespace App\Ems\Events;

use App\Ems\Models\EventCategory;

/**
 * Covers create/update/delete of a category; the specific verb is carried in
 * the constructor so the taxonomy does not need three near-identical classes.
 */
class EventCategoryChanged extends EmsDomainEvent
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        EventCategory $category,
        public readonly string $verb,
        ?\App\Models\User $actor = null,
        array $context = [],
    ) {
        parent::__construct($category, $actor, $context);
    }

    public function action(): string
    {
        return 'event_category.' . $this->verb;
    }

    public function description(): string
    {
        /** @var EventCategory $category */
        $category = $this->subject;

        return sprintf('Event category "%s" was %s.', $category->name, $this->verb);
    }

    public function payload(): array
    {
        /** @var EventCategory $category */
        $category = $this->subject;

        return array_merge($this->context, [
            'category_uuid' => $category->uuid,
            'slug' => $category->slug,
        ]);
    }
}
