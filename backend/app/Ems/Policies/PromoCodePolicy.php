<?php

namespace App\Ems\Policies;

use App\Ems\Models\PromoCode;
use App\Ems\Support\EmsPermissions;
use App\Models\User;

class PromoCodePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(EmsPermissions::PROMO_CODES_VIEW);
    }

    public function view(User $user, PromoCode $promoCode): bool
    {
        return $user->hasPermission(EmsPermissions::PROMO_CODES_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(EmsPermissions::PROMO_CODES_MANAGE);
    }

    public function update(User $user, PromoCode $promoCode): bool
    {
        return $user->hasPermission(EmsPermissions::PROMO_CODES_MANAGE);
    }

    public function delete(User $user, PromoCode $promoCode): bool
    {
        return $user->hasPermission(EmsPermissions::PROMO_CODES_MANAGE);
    }
}
