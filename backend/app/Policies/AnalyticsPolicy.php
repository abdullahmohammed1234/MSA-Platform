<?php

namespace App\Policies;

use App\Models\User;

class AnalyticsPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_analytics') || $user->hasPermission('view_reports');
    }

    public function view(User $user): bool
    {
        return $user->hasPermission('view_analytics') || $user->hasPermission('view_reports');
    }
}
