<?php

namespace App\Store\Policies;

use App\Models\User;
use App\Store\Models\StoreInventoryAdjustment;
use App\Store\Support\StorePermissions;

class StoreInventoryPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(StorePermissions::INVENTORY_VIEW);
    }

    public function view(User $user, StoreInventoryAdjustment $adjustment): bool
    {
        return $user->hasPermissionTo(StorePermissions::INVENTORY_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(StorePermissions::INVENTORY_UPDATE);
    }

    public function update(User $user, StoreInventoryAdjustment $adjustment): bool
    {
        return $user->hasPermissionTo(StorePermissions::INVENTORY_UPDATE);
    }
}
