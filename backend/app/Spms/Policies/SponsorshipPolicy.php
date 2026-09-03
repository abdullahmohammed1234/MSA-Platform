<?php

namespace App\Spms\Policies;

use App\Models\User;

class SponsorshipPolicy
{
    private const PRIVILEGED_ROLES = ['super-admin', 'admin', 'spms-administrator'];

    public function viewAny(User $user): bool
    {
        if ($user->hasAnyRole(self::PRIVILEGED_ROLES) || $user->hasAnyRole(['spms-staff', 'spms-viewer'])) {
            return true;
        }

        return $user->hasPermissionTo('sponsorship.view');
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        if ($user->hasAnyRole(self::PRIVILEGED_ROLES) || $user->hasRole('spms-staff')) {
            return true;
        }

        return $user->hasPermissionTo('sponsorship.create') || $user->hasPermissionTo('sponsorship.manage');
    }

    public function update(User $user): bool
    {
        if ($user->hasAnyRole(self::PRIVILEGED_ROLES) || $user->hasRole('spms-staff')) {
            return true;
        }

        return $user->hasPermissionTo('sponsorship.edit') || $user->hasPermissionTo('sponsorship.manage');
    }

    public function delete(User $user): bool
    {
        if ($user->hasAnyRole(self::PRIVILEGED_ROLES)) {
            return true;
        }

        return $user->hasPermissionTo('sponsorship.manage');
    }

    public function manageAgreements(User $user): bool
    {
        if ($user->hasAnyRole(self::PRIVILEGED_ROLES)) {
            return true;
        }

        return $user->hasPermissionTo('sponsorship.agreements') || $user->hasPermissionTo('sponsorship.manage');
    }

    public function managePayments(User $user): bool
    {
        if ($user->hasAnyRole(self::PRIVILEGED_ROLES)) {
            return true;
        }

        return $user->hasPermissionTo('sponsorship.payments') || $user->hasPermissionTo('sponsorship.manage');
    }

    public function manageFulfillment(User $user): bool
    {
        if ($user->hasAnyRole(self::PRIVILEGED_ROLES) || $user->hasRole('spms-staff')) {
            return true;
        }

        return $user->hasPermissionTo('sponsorship.fulfillment') || $user->hasPermissionTo('sponsorship.manage');
    }

    public function export(User $user): bool
    {
        if ($user->hasAnyRole(self::PRIVILEGED_ROLES)) {
            return true;
        }

        return $user->hasPermissionTo('sponsorship.export');
    }
}
