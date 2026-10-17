<?php

namespace App\Policies;

use App\Models\SupportInquiry;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SupportInquiryPolicy
{
    use HandlesAuthorization;

    private function isStaff(User $user): bool
    {
        return $user->hasPermission('manage_support') 
            || $user->hasRole('admin') 
            || $user->hasRole('super-admin')
            || $user->hasRole('director')
            || $user->hasRole('dawah-coordinator');
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SupportInquiry $inquiry): bool
    {
        return $user->id === $inquiry->user_id || $this->isStaff($user);
    }

    public function create(User $user): bool
    {
        return $user->is_active ?? true;
    }

    public function reply(User $user, SupportInquiry $inquiry): bool
    {
        if ($this->isStaff($user)) {
            return true;
        }

        // Member cannot reply to permanently closed inquiry
        return $user->id === $inquiry->user_id && $inquiry->status !== SupportInquiry::STATUS_CLOSED;
    }

    public function close(User $user, SupportInquiry $inquiry): bool
    {
        return $user->id === $inquiry->user_id || $this->isStaff($user);
    }

    public function manage(User $user): bool
    {
        return $this->isStaff($user);
    }
}
