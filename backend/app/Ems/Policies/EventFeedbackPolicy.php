<?php

namespace App\Ems\Policies;

use App\Ems\Models\EventFeedback;
use App\Ems\Support\EmsPermissions;
use App\Models\User;

class EventFeedbackPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(EmsPermissions::FEEDBACK_VIEW);
    }

    public function view(User $user, EventFeedback $feedback): bool
    {
        return $user->hasPermission(EmsPermissions::FEEDBACK_VIEW);
    }

    public function create(User $user): bool
    {
        // Any authenticated user (attendee) can submit feedback for an event
        return true;
    }
}
