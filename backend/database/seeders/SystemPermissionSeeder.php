<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SystemPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissionsData = [
            ['name' => 'Manage Queues', 'slug' => 'manage_queues', 'module' => 'System', 'description' => 'Clean, flush, and control queue workers.'],
            ['name' => 'View Queue Status', 'slug' => 'view_queue_status', 'module' => 'System', 'description' => 'Monitor queue partition active and pending jobs.'],
            ['name' => 'Retry Failed Jobs', 'slug' => 'retry_failed_jobs', 'module' => 'System', 'description' => 'Re-run background jobs that failed.'],
            ['name' => 'Manage Scheduler', 'slug' => 'manage_scheduler', 'module' => 'System', 'description' => 'List and run scheduled cron tasks.'],
        ];

        foreach ($permissionsData as $permData) {
            Permission::firstOrCreate(
                ['slug' => $permData['slug']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $permData['name'],
                    'module' => $permData['module'],
                    'description' => $permData['description'],
                ]
            );
        }

        // Sync to Super Admin and Admin roles
        $superAdmin = Role::where('slug', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->permissions()->syncWithoutDetaching(
                Permission::whereIn('slug', array_column($permissionsData, 'slug'))->pluck('id')->toArray()
            );
        }

        $admin = Role::where('slug', 'admin')->first();
        if ($admin) {
            $admin->permissions()->syncWithoutDetaching(
                Permission::whereIn('slug', array_column($permissionsData, 'slug'))->pluck('id')->toArray()
            );
        }
    }
}
