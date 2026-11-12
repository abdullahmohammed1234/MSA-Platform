<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Pm\Support\PmPermissions;
use App\Pm\Support\PmRoles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PmRolePermissionSeeder extends Seeder
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

        foreach (PmPermissions::definitions() as $slug => $definition) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $definition['name'],
                    'module' => PmPermissions::MODULE,
                    'description' => $definition['description'],
                ]
            );

            $permission->fill([
                'name' => $definition['name'],
                'module' => PmPermissions::MODULE,
                'description' => $definition['description'],
            ])->save();

            $permissions[$slug] = $permission;
        }

        return $permissions;
    }

    /**
     * @return array<string, Role>
     */
    private function seedRoles(): array
    {
        $roles = [];

        foreach (PmRoles::definitions() as $slug => $definition) {
            $role = Role::firstOrCreate(
                ['slug' => $slug],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                ]
            );

            $role->fill([
                'name' => $definition['name'],
                'description' => $definition['description'],
            ])->save();

            $roles[$slug] = $role;
        }

        return $roles;
    }

    /**
     * @param array<string, Role> $roles
     * @param array<string, Permission> $permissions
     */
    private function grantPermissions(array $roles, array $permissions): void
    {
        foreach (PmRoles::definitions() as $slug => $definition) {
            if (isset($roles[$slug])) {
                $permissionIds = [];
                foreach ($definition['permissions'] as $permSlug) {
                    if (isset($permissions[$permSlug])) {
                        $permissionIds[] = $permissions[$permSlug]->id;
                    }
                }
                $roles[$slug]->permissions()->syncWithoutDetaching($permissionIds);
            }
        }

        // Grant to platform super-admin
        $superAdmin = Role::where('slug', 'super-admin')->first();
        if ($superAdmin) {
            $allPmPermIds = array_map(fn ($p) => $p->id, $permissions);
            $superAdmin->permissions()->syncWithoutDetaching($allPmPermIds);
        }

        // Grant to platform admin
        $admin = Role::where('slug', 'admin')->first();
        if ($admin) {
            $adminPermSlugs = [
                PmPermissions::ACCESS,
                PmPermissions::VIEW,
                PmPermissions::PROJECTS_CREATE,
                PmPermissions::PROJECTS_MANAGE,
                PmPermissions::DEPARTMENTS_MANAGE,
                PmPermissions::TASKS_MANAGE,
            ];
            $adminPermIds = [];
            foreach ($adminPermSlugs as $slug) {
                if (isset($permissions[$slug])) {
                    $adminPermIds[] = $permissions[$slug]->id;
                }
            }
            $admin->permissions()->syncWithoutDetaching($adminPermIds);
        }
    }
}
