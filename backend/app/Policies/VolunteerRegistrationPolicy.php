<?php

namespace App\Policies;

use App\Ems\Support\EmsPermissions;
use App\Models\User;

class VolunteerRegistrationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(EmsPermissions::VOLUNTEERS_VIEW);
    }

    public function view(User $user): bool
    {
        return $user->hasPermission(EmsPermissions::VOLUNTEERS_VIEW);
    }

    public function create(User $user): bool
    {
        return true; // Public submissions allowed
    }

    public function update(User $user): bool
    {
        return $user->hasPermission(EmsPermissions::VOLUNTEERS_UPDATE);
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission(EmsPermissions::VOLUNTEERS_DELETE);
    }

    public function restore(User $user): bool
    {
        return $user->hasPermission(EmsPermissions::VOLUNTEERS_DELETE);
    }

    public function forceDelete(User $user): bool
    {
        return $user->hasPermission(EmsPermissions::VOLUNTEERS_DELETE);
    }
}
