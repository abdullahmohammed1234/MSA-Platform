<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CourseCompletedEvent
{
    use Dispatchable, SerializesModels;

    public User $user;
    public string $courseName;
    public ?string $completionDate;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, string $courseName, ?string $completionDate = null)
    {
        $this->user = $user;
        $this->courseName = $courseName;
        $this->completionDate = $completionDate;
    }
}
