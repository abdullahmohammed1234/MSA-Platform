<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Permission;
use App\Models\Role;
use App\Ems\Support\EmsPermissions;
use App\Ems\Support\EmsRoles;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Add permissions
        $slugs = [
            EmsPermissions::ANALYTICS_VIEW,
            EmsPermissions::ANALYTICS_VIEW_FINANCIAL,
            EmsPermissions::REPORTS_MANAGE,
        ];

        $definitions = collect(EmsPermissions::definitions())
            ->filter(fn ($d) => in_array($d['slug'], $slugs));

        $permissions = [];
        foreach ($definitions as $definition) {
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

        // Grant to roles
        foreach (EmsRoles::permissionMatrix() as $roleSlug => $permissionSlugs) {
            $role = Role::where('slug', $roleSlug)->first();
            if (!$role) {
                continue;
            }

            $ids = [];
            foreach ($permissionSlugs as $slug) {
                if (in_array($slug, $slugs) && isset($permissions[$slug])) {
                    $ids[] = $permissions[$slug]->id;
                }
            }

            if (!empty($ids)) {
                $role->permissions()->syncWithoutDetaching($ids);
            }
        }
    }

    public function down(): void
    {
        $slugs = [
            EmsPermissions::ANALYTICS_VIEW,
            EmsPermissions::ANALYTICS_VIEW_FINANCIAL,
            EmsPermissions::REPORTS_MANAGE,
        ];

        foreach ($slugs as $slug) {
            $permission = Permission::where('slug', $slug)->first();
            if ($permission) {
                DB::table('permission_role')->where('permission_id', $permission->id)->delete();
                $permission->delete();
            }
        }
    }
};
