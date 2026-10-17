<?php

namespace App\Support;

use App\Models\User;

/**
 * Remembers a user's roles, permission slugs and explicit application grants for the
 * duration of one HTTP request, so repeated hasRole/hasPermission/canAccess checks cost
 * one lookup each instead of one query per check.
 *
 * It is inactive outside an HTTP request (queue jobs, console, direct calls in tests),
 * entries are keyed by user id and never outlive the request, and any write to the RBAC
 * tables flushes everything so a change made earlier in the same request is honoured.
 */
final class AuthorizationMemo
{
    private const PRIVILEGED_ROLES = ['super-admin', 'admin'];

    private const RBAC_WRITE = '/^\s*(insert|update|delete|replace)\b.*\b(role_user|permission_role|permission_user|application_access|roles|permissions)\b/is';

    private int $depth = 0;

    /** @var array<int, array<string, true>> lower-cased role slugs and names */
    private array $roles = [];

    /** @var array<int, array<string, true>> lower-cased permission slugs, direct and inherited */
    private array $permissions = [];

    /** @var array<int, array<string, true>> */
    private array $applications = [];

    public function begin(): void
    {
        $this->depth++;
    }

    public function end(): void
    {
        $this->depth = max(0, $this->depth - 1);
        if ($this->depth === 0) {
            $this->flush();
        }
    }

    public function active(): bool
    {
        return $this->depth > 0;
    }

    public function flush(): void
    {
        $this->roles = [];
        $this->permissions = [];
        $this->applications = [];
    }

    public function observeStatement(string $sql): void
    {
        if (($this->roles !== [] || $this->permissions !== [] || $this->applications !== [])
            && preg_match(self::RBAC_WRITE, $sql) === 1) {
            $this->flush();
        }
    }

    /**
     * Role names are compared case-insensitively, matching the MySQL/MariaDB collation the
     * per-check queries relied on in production.
     *
     * @param  list<string>  $roles
     */
    public function hasAnyRole(User $user, array $roles): bool
    {
        $held = $this->rolesFor($user);
        foreach ($roles as $role) {
            if (isset($held[mb_strtolower($role)])) {
                return true;
            }
        }

        return false;
    }

    public function hasPermission(User $user, string $permission): bool
    {
        if ($this->hasAnyRole($user, self::PRIVILEGED_ROLES)) {
            return true;
        }

        if (! isset($this->permissions[$user->id])) {
            $slugs = $user->permissions()->pluck('slug');
            foreach ($user->roles()->with('permissions:id,slug')->get(['roles.id']) as $role) {
                $slugs = $slugs->merge($role->permissions->pluck('slug'));
            }
            $this->permissions[$user->id] = $this->keyed($slugs->all());
        }

        return isset($this->permissions[$user->id][mb_strtolower($permission)]);
    }

    /**
     * @param  list<string>  $applications
     */
    public function hasApplicationGrant(User $user, array $applications): bool
    {
        if (! isset($this->applications[$user->id])) {
            $this->applications[$user->id] = array_fill_keys(
                $user->applicationAccess()->pluck('application')->all(),
                true
            );
        }

        foreach ($applications as $application) {
            if (isset($this->applications[$user->id][$application])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, true>
     */
    private function rolesFor(User $user): array
    {
        if (! isset($this->roles[$user->id])) {
            $names = [];
            foreach ($user->roles()->get(['roles.slug', 'roles.name']) as $role) {
                $names[] = (string) $role->slug;
                $names[] = (string) $role->name;
            }
            $this->roles[$user->id] = $this->keyed($names);
        }

        return $this->roles[$user->id];
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<string, true>
     */
    private function keyed(array $values): array
    {
        $keyed = [];
        foreach ($values as $value) {
            if (is_string($value) && $value !== '') {
                $keyed[mb_strtolower($value)] = true;
            }
        }

        return $keyed;
    }
}
