<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Spms\Support\SpmsPermissions;
use App\Spms\Support\SpmsRoles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SpmsRolePermissionSeeder extends Seeder
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

        foreach (SpmsPermissions::definitions() as $slug => $definition) {
            $permissions[$slug] = Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $definition['name'],
                    'module' => SpmsPermissions::MODULE,
                    'description' => $definition['description'],
                ]
            );

            // Keep metadata updated
            $permissions[$slug]->fill([
                'name' => $definition['name'],
                'module' => SpmsPermissions::MODULE,
                'description' => $definition['description'],
            ])->save();
        }

        return $permissions;
    }

    /**
     * @return array<string, Role>
     */
    private function seedRoles(): array
    {
        $roles = [];

        foreach (SpmsRoles::definitions() as $definition) {
            $roles[$definition['slug']] = Role::firstOrCreate(
                ['slug' => $definition['slug']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                ]
            );
        }

        $superAdmin = Role::where('slug', SpmsRoles::SUPER_ADMIN)->first();
        if ($superAdmin) {
            $roles[SpmsRoles::SUPER_ADMIN] = $superAdmin;
        }

        $admin = Role::where('slug', 'admin')->first();
        if ($admin) {
            $roles['admin'] = $admin;
        }

        return $roles;
    }

    /**
     * @param array<string, Role> $roles
     * @param array<string, Permission> $permissions
     */
    private function grantPermissions(array $roles, array $permissions): void
    {
        foreach (SpmsRoles::permissionMatrix() as $roleSlug => $permissionSlugs) {
            if (!isset($roles[$roleSlug])) {
                continue;
            }

            $ids = [];
            foreach ($permissionSlugs as $slug) {
                if (isset($permissions[$slug])) {
                    $ids[] = $permissions[$slug]->id;
                }
            }

            if (!empty($ids)) {
                $roles[$roleSlug]->permissions()->syncWithoutDetaching($ids);
            }
        }

        // Grant all SPMS permissions to general Admin role as well
        if (isset($roles['admin'])) {
            $adminPermissionIds = array_map(fn($p) => $p->id, array_values($permissions));
            $roles['admin']->permissions()->syncWithoutDetaching($adminPermissionIds);
        }
    }
}
