<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnnouncementPublishedEvent
{
    use Dispatchable, SerializesModels;

    public string $announcementTitle;
    public string $announcementExcerpt;
    public string $announcementSlug;
    public string $audience; // e.g. All, Volunteers, Mentors, Coordinators

    /**
     * Create a new event instance.
     */
    public function __construct(string $announcementTitle, string $announcementExcerpt, string $announcementSlug, string $audience = 'All')
    {
        $this->announcementTitle = $announcementTitle;
        $this->announcementExcerpt = $announcementExcerpt;
        $this->announcementSlug = $announcementSlug;
        $this->audience = $audience;
    }
}
