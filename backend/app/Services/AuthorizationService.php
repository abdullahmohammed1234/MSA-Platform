<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuthorizationService
{
    /**
     * Assign a role to a user.
     */
    public function assignRoleToUser(User $user, string $roleSlug, ?int $actorId = null): void
    {
        DB::transaction(function () use ($user, $roleSlug, $actorId) {
            $user->assignRole($roleSlug);

            AuditLog::create([
                'user_id' => $actorId,
                'action' => 'role_assigned',
                'target_type' => User::class,
                'target_id' => $user->id,
                'description' => "Assigned role '{$roleSlug}' to user '{$user->email}'.",
                'payload' => ['role' => $roleSlug],
            ]);
        });
    }

    /**
     * Remove a role from a user.
     */
    public function removeRoleFromUser(User $user, string $roleSlug, ?int $actorId = null): void
    {
        DB::transaction(function () use ($user, $roleSlug, $actorId) {
            $user->removeRole($roleSlug);

            AuditLog::create([
                'user_id' => $actorId,
                'action' => 'role_removed',
                'target_type' => User::class,
                'target_id' => $user->id,
                'description' => "Removed role '{$roleSlug}' from user '{$user->email}'.",
                'payload' => ['role' => $roleSlug],
            ]);
        });
    }

    /**
     * Sync user roles.
     */
    public function syncUserRoles(User $user, array $roleSlugs, ?int $actorId = null): void
    {
        DB::transaction(function () use ($user, $roleSlugs, $actorId) {
            $oldRoles = $user->roles()->pluck('slug')->toArray();
            $user->syncRoles($roleSlugs);
            $newRoles = $user->roles()->pluck('slug')->toArray();

            AuditLog::create([
                'user_id' => $actorId,
                'action' => 'roles_synced',
                'target_type' => User::class,
                'target_id' => $user->id,
                'description' => "Synced roles for user '{$user->email}'.",
                'payload' => [
                    'before' => $oldRoles,
                    'after' => $newRoles,
                ],
            ]);
        });
    }

    /**
     * Give a direct permission to a user.
     */
    public function givePermissionToUser(User $user, string $permissionSlug, ?int $actorId = null): void
    {
        DB::transaction(function () use ($user, $permissionSlug, $actorId) {
            $user->givePermission($permissionSlug);

            AuditLog::create([
                'user_id' => $actorId,
                'action' => 'permission_granted',
                'target_type' => User::class,
                'target_id' => $user->id,
                'description' => "Granted direct permission '{$permissionSlug}' to user '{$user->email}'.",
                'payload' => ['permission' => $permissionSlug],
            ]);
        });
    }

    /**
     * Revoke a direct permission from a user.
     */
    public function revokePermissionFromUser(User $user, string $permissionSlug, ?int $actorId = null): void
    {
        DB::transaction(function () use ($user, $permissionSlug, $actorId) {
            $user->revokePermission($permissionSlug);

            AuditLog::create([
                'user_id' => $actorId,
                'action' => 'permission_revoked',
                'target_type' => User::class,
                'target_id' => $user->id,
                'description' => "Revoked direct permission '{$permissionSlug}' from user '{$user->email}'.",
                'payload' => ['permission' => $permissionSlug],
            ]);
        });
    }

    /**
     * Sync permissions of a Role.
     */
    public function syncRolePermissions(Role $role, array $permissionSlugs, ?int $actorId = null): void
    {
        DB::transaction(function () use ($role, $permissionSlugs, $actorId) {
            $permissionIds = Permission::whereIn('slug', $permissionSlugs)->pluck('id')->toArray();
            $oldPermissions = $role->permissions()->pluck('slug')->toArray();
            $role->permissions()->sync($permissionIds);
            $newPermissions = $role->permissions()->pluck('slug')->toArray();

            AuditLog::create([
                'user_id' => $actorId,
                'action' => 'role_permissions_synced',
                'target_type' => Role::class,
                'target_id' => $role->id,
                'description' => "Synced permissions for role '{$role->name}'.",
                'payload' => [
                    'before' => $oldPermissions,
                    'after' => $newPermissions,
                ],
            ]);
        });
    }
}
