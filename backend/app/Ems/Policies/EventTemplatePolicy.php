<?php

namespace App\Ems\Policies;

use App\Ems\Models\EventTemplate;
use App\Ems\Support\EmsPermissions;
use App\Models\User;

class EventTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(EmsPermissions::EVENT_TEMPLATES_VIEW);
    }

    public function view(User $user, EventTemplate $template): bool
    {
        return $user->hasPermission(EmsPermissions::EVENT_TEMPLATES_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(EmsPermissions::EVENT_TEMPLATES_MANAGE);
    }

    public function update(User $user, EventTemplate $template): bool
    {
        return $user->hasPermission(EmsPermissions::EVENT_TEMPLATES_MANAGE);
    }

    public function delete(User $user, EventTemplate $template): bool
    {
        return $user->hasPermission(EmsPermissions::EVENT_TEMPLATES_MANAGE);
    }

    public function duplicate(User $user, EventTemplate $template): bool
    {
        return $user->hasPermission(EmsPermissions::EVENT_TEMPLATES_MANAGE);
    }

    public function setDefault(User $user, EventTemplate $template): bool
    {
        return $user->hasPermission(EmsPermissions::EVENT_TEMPLATES_MANAGE);
    }
}
