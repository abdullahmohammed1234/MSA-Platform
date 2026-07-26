<?php

namespace App\Ems\Policies;

use App\Ems\Models\EventSeries;
use App\Ems\Support\EmsPermissions;
use App\Models\User;

class EventSeriesPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(EmsPermissions::SERIES_VIEW);
    }

    public function view(User $user, EventSeries $series): bool
    {
        return $user->hasPermission(EmsPermissions::SERIES_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(EmsPermissions::SERIES_MANAGE);
    }

    public function update(User $user, EventSeries $series): bool
    {
        return $user->hasPermission(EmsPermissions::SERIES_MANAGE);
    }

    public function delete(User $user, EventSeries $series): bool
    {
        return $user->hasPermission(EmsPermissions::SERIES_MANAGE);
    }
}
