<?php

namespace App\Store\Policies;

use App\Models\User;
use App\Store\Models\StoreProduct;
use App\Store\Support\StorePermissions;

class StoreProductPolicy
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
        return $user->hasPermissionTo(StorePermissions::PRODUCTS_VIEW);
    }

    public function view(User $user, StoreProduct $product): bool
    {
        return $user->hasPermissionTo(StorePermissions::PRODUCTS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(StorePermissions::PRODUCTS_CREATE);
    }

    public function update(User $user, StoreProduct $product): bool
    {
        return $user->hasPermissionTo(StorePermissions::PRODUCTS_UPDATE);
    }

    public function delete(User $user, StoreProduct $product): bool
    {
        return $user->hasPermissionTo(StorePermissions::PRODUCTS_DELETE);
    }
}
