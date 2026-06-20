<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\User;

class CertificatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_certificates') || $user->hasRole('volunteer') || $user->hasRole('mentor');
    }

    public function view(User $user, Certificate $certificate): bool
    {
        return $user->id === $certificate->user_id || $user->hasPermission('manage_certificates');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_certificates');
    }

    public function delete(User $user, Certificate $certificate): bool
    {
        return $user->hasPermission('manage_certificates');
    }
}
