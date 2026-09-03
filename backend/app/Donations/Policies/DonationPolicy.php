<?php

namespace App\Donations\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DonationPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('donations.view') || $user->hasRole('dms-administrator') || $user->hasRole('dms-staff');
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function manage(User $user): bool
    {
        return $user->hasPermissionTo('donations.manage') || $user->hasRole('dms-administrator');
    }

    public function refund(User $user): bool
    {
        return $user->hasPermissionTo('donations.refund') || $user->hasRole('dms-administrator');
    }

    public function viewReports(User $user): bool
    {
        return $user->hasPermissionTo('donations.reports') || $user->hasRole('dms-administrator');
    }

    public function export(User $user): bool
    {
        return $user->hasPermissionTo('donations.export') || $user->hasRole('dms-administrator');
    }
}
