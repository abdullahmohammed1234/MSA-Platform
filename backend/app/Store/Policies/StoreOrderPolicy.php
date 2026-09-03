<?php

namespace App\Store\Policies;

use App\Models\User;
use App\Store\Models\StoreOrder;
use App\Store\Support\StorePermissions;

class StoreOrderPolicy
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
        return $user->hasPermissionTo(StorePermissions::ORDERS_VIEW);
    }

    public function view(User $user, StoreOrder $order): bool
    {
        // Allow customer to view their own order
        if ($order->user_id && $order->user_id === $user->id) {
            return true;
        }

        return $user->hasPermissionTo(StorePermissions::ORDERS_VIEW);
    }

    public function update(User $user, StoreOrder $order): bool
    {
        return $user->hasPermissionTo(StorePermissions::ORDERS_UPDATE);
    }

    public function refund(User $user, StoreOrder $order): bool
    {
        return $user->hasPermissionTo(StorePermissions::ORDERS_REFUND);
    }
}
