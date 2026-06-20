<?php

namespace App\Policies;

use App\Models\CertificateAward;
use App\Models\User;

class CertificateAwardPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CertificateAward $award): bool
    {
        return $user->id === $award->user_id || $user->hasPermission('manage_certificates');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_certificates');
    }

    public function delete(User $user, CertificateAward $award): bool
    {
        return $user->hasPermission('manage_certificates');
    }
}
