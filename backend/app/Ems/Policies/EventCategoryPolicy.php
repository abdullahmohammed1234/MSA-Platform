<?php

namespace App\Ems\Policies;

use App\Ems\Models\EventCategory;
use App\Ems\Support\EmsPermissions;
use App\Models\User;

/**
 * Authorization for the event category taxonomy.
 *
 * Categories are programme-wide, so there is no per-record scoping — only the
 * granular permission matters.
 */
class EventCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(EmsPermissions::CATEGORIES_VIEW);
    }

    public function view(User $user, EventCategory $category): bool
    {
        return $user->hasPermission(EmsPermissions::CATEGORIES_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(EmsPermissions::CATEGORIES_CREATE);
    }

    public function update(User $user, EventCategory $category): bool
    {
        return $user->hasPermission(EmsPermissions::CATEGORIES_UPDATE);
    }

    public function delete(User $user, EventCategory $category): bool
    {
        return $user->hasPermission(EmsPermissions::CATEGORIES_DELETE);
    }
}
