<?php

namespace App\Ems\Policies;

use App\Ems\Enums\EventTransition;
use App\Ems\Models\Event;
use App\Ems\Support\EmsPermissions;
use App\Models\User;

/**
 * Authorization for EMS events.
 *
 * Two questions are asked of every request, and both must pass:
 *
 *   1. Does the user hold the granular permission for this action?
 *   2. Is this event within the user's scope?
 *
 * Scope is itself permission-driven: holders of events.view_all see the whole
 * programme, everyone else is limited to events they own, created,
 * co-organize or are staffed on. No policy method inspects a role name.
 *
 * Note the platform grants super-admin and admin every ability through
 * Gate::before in AppServiceProvider, so those two roles short-circuit these
 * checks — that is existing, intentional platform behaviour.
 */
class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(EmsPermissions::EVENTS_VIEW);
    }

    public function view(User $user, Event $event): bool
    {
        return $user->hasPermission(EmsPermissions::EVENTS_VIEW)
            && $this->inScope($user, $event);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(EmsPermissions::EVENTS_CREATE);
    }

    public function update(User $user, Event $event): bool
    {
        return $user->hasPermission(EmsPermissions::EVENTS_UPDATE)
            && $this->inScope($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->hasPermission(EmsPermissions::EVENTS_DELETE)
            && $this->inScope($user, $event);
    }

    public function viewAttendees(User $user, Event $event): bool
    {
        return $user->hasPermission(EmsPermissions::REGISTRATIONS_VIEW)
            && $this->inScope($user, $event);
    }

    public function createRegistration(User $user, Event $event): bool
    {
        return $user->hasPermission(EmsPermissions::REGISTRATIONS_CREATE)
            && $this->inScope($user, $event);
    }

    public function viewOperations(User $user, Event $event): bool
    {
        return (
            $user->hasPermission(EmsPermissions::REGISTRATIONS_VIEW)
            || $user->hasPermission(EmsPermissions::CHECK_INS_VIEW)
        ) && $this->inScope($user, $event);
    }

    public function performCheckIn(User $user, Event $event): bool
    {
        return $user->hasPermission(EmsPermissions::CHECK_INS_PERFORM)
            && $this->inScope($user, $event);
    }

    public function undoCheckIn(User $user, Event $event): bool
    {
        return $user->hasPermission(EmsPermissions::CHECK_INS_UNDO)
            && $this->inScope($user, $event);
    }

    public function overrideCheckIn(User $user, Event $event): bool
    {
        return $user->hasPermission(EmsPermissions::CHECK_INS_OVERRIDE)
            && $this->inScope($user, $event);
    }

    public function importAttendees(User $user, Event $event): bool
    {
        return $user->hasPermission(EmsPermissions::IMPORTS_CREATE)
            && $this->inScope($user, $event);
    }

    public function viewImports(User $user, Event $event): bool
    {
        return $user->hasPermission(EmsPermissions::IMPORTS_VIEW)
            && $this->inScope($user, $event);
    }

    public function viewNotifications(User $user, Event $event): bool
    {
        return $user->hasPermission(EmsPermissions::NOTIFICATIONS_VIEW)
            && $this->inScope($user, $event);
    }

    public function sendNotifications(User $user, Event $event): bool
    {
        return $user->hasPermission(EmsPermissions::NOTIFICATIONS_SEND)
            && $this->inScope($user, $event);
    }

    public function manageNotifications(User $user, Event $event): bool
    {
        return $user->hasPermission(EmsPermissions::NOTIFICATIONS_MANAGE)
            && $this->inScope($user, $event);
    }

    /**
     * Each lifecycle transition carries its own permission, so an organizer
     * can be allowed to publish but not to archive.
     */
    public function transition(User $user, Event $event, EventTransition $transition): bool
    {
        return $user->hasPermission($transition->permission())
            && $this->inScope($user, $event);
    }

    /**
     * Whether the event falls inside the user's visibility scope.
     */
    private function inScope(User $user, Event $event): bool
    {
        if ($user->hasPermission(EmsPermissions::EVENTS_VIEW_ALL)) {
            return true;
        }

        return $event->hasTeamMember($user);
    }
}
