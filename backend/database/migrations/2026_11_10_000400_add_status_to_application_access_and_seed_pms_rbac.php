<?php

use App\Models\Permission;
use App\Models\Role;
use App\Pm\Support\PmPermissions;
use App\Pm\Support\PmRoles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add status column to application_access table for suspend/restore support
        Schema::table('application_access', function (Blueprint $table) {
            if (!Schema::hasColumn('application_access', 'status')) {
                $table->string('status', 30)->default('active')->after('application');
                $table->index(['user_id', 'application', 'status'], 'idx_app_access_user_status');
            }
        });

        // 2. Seed all granular PMS permissions
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

        // 3. Seed/update the 6 canonical PMS roles and map permissions
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

            $permIds = [];
            foreach ($definition['permissions'] as $permSlug) {
                if (isset($permissions[$permSlug])) {
                    $permIds[] = $permissions[$permSlug]->id;
                }
            }

            if (!empty($permIds)) {
                $role->permissions()->syncWithoutDetaching($permIds);
            }
        }

        // 4. Also grant appropriate PMS permissions to platform 'admin' role
        $admin = Role::where('slug', 'admin')->first();
        if ($admin) {
            $adminPermSlugs = [
                PmPermissions::ACCESS,
                PmPermissions::VIEW,
                PmPermissions::PROJECTS_VIEW,
                PmPermissions::PROJECTS_CREATE,
                PmPermissions::PROJECTS_UPDATE,
                PmPermissions::PROJECTS_MANAGE,
                PmPermissions::DEPARTMENTS_VIEW,
                PmPermissions::TASKS_VIEW,
                PmPermissions::TASKS_CREATE,
                PmPermissions::TASKS_UPDATE,
                PmPermissions::TASKS_ASSIGN,
                PmPermissions::TASKS_MANAGE,
                PmPermissions::MEMBERS_VIEW,
                PmPermissions::INVITATIONS_CREATE,
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_access', function (Blueprint $table) {
            if (Schema::hasColumn('application_access', 'status')) {
                $table->dropIndex('idx_app_access_user_status');
                $table->dropColumn('status');
            }
        });
    }
};
