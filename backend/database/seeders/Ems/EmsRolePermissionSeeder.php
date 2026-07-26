<?php

namespace Database\Seeders\Ems;

use App\Ems\Support\EmsPermissions;
use App\Ems\Support\EmsRoles;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the EMS roles and granular permissions into the platform RBAC tables.
 *
 * Idempotent and additive: it uses firstOrCreate for the records themselves and
 * syncWithoutDetaching for the role grants, so re-running it never revokes a
 * permission an administrator granted by hand, and it never touches roles or
 * permissions belonging to other modules.
 *
 * Safe to run in production.
 */
class EmsRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = $this->seedPermissions();
        $roles = $this->seedRoles();

        $this->grantPermissions($roles, $permissions);
    }

    /**
     * @return array<string, Permission>
     */
    private function seedPermissions(): array
    {
        $permissions = [];

        foreach (EmsPermissions::definitions() as $definition) {
            $permissions[$definition['slug']] = Permission::firstOrCreate(
                ['slug' => $definition['slug']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $definition['name'],
                    'module' => EmsPermissions::MODULE,
                    'description' => $definition['description'],
                ]
            );
        }

        return $permissions;
    }

    /**
     * @return array<string, Role>
     */
    private function seedRoles(): array
    {
        $roles = [];

        foreach (EmsRoles::definitions() as $definition) {
            $roles[$definition['slug']] = Role::firstOrCreate(
                ['slug' => $definition['slug']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                ]
            );
        }

        // super-admin is a platform role the EMS grants to but does not own,
        // so it is looked up rather than created.
        $superAdmin = Role::where('slug', EmsRoles::SUPER_ADMIN)->first();

        if ($superAdmin) {
            $roles[EmsRoles::SUPER_ADMIN] = $superAdmin;
        }

        return $roles;
    }

    /**
     * @param  array<string, Role>  $roles
     * @param  array<string, Permission>  $permissions
     */
    private function grantPermissions(array $roles, array $permissions): void
    {
        foreach (EmsRoles::permissionMatrix() as $roleSlug => $permissionSlugs) {
            if (! isset($roles[$roleSlug])) {
                continue;
            }

            $ids = [];

            foreach ($permissionSlugs as $slug) {
                if (isset($permissions[$slug])) {
                    $ids[] = $permissions[$slug]->id;
                }
            }

            if ($ids !== []) {
                $roles[$roleSlug]->permissions()->syncWithoutDetaching($ids);
            }
        }
    }
}
