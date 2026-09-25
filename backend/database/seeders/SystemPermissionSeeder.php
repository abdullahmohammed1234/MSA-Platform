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
            ['name' => 'View Platform Operations', 'slug' => 'platform.view', 'module' => 'Platform', 'description' => 'Access Platform Operations dashboard and metrics.'],
            ['name' => 'View System Health', 'slug' => 'platform.health', 'module' => 'Platform', 'description' => 'View system availability and health histories.'],
            ['name' => 'View Platform Audit', 'slug' => 'platform.audit', 'module' => 'Platform', 'description' => 'Search and filter platform audit logs.'],
            ['name' => 'Manage Platform Alerts', 'slug' => 'platform.alerts', 'module' => 'Platform', 'description' => 'View, acknowledge, and resolve system alerts.'],
            ['name' => 'Execute Platform Operations', 'slug' => 'platform.operations', 'module' => 'Platform', 'description' => 'Execute administrative maintenance actions.'],

            // VMS Permissions
            ['name' => 'View VMS', 'slug' => 'vms.view', 'module' => 'VMS', 'description' => 'View VMS opportunities and rosters.'],
            ['name' => 'Create VMS Opportunities', 'slug' => 'vms.create', 'module' => 'VMS', 'description' => 'Create volunteer opportunity configurations.'],
            ['name' => 'Update VMS Opportunities', 'slug' => 'vms.update', 'module' => 'VMS', 'description' => 'Update volunteer opportunities.'],
            ['name' => 'Delete VMS Opportunities', 'slug' => 'vms.delete', 'module' => 'VMS', 'description' => 'Delete volunteer opportunities.'],
            ['name' => 'Full VMS Management', 'slug' => 'vms.manage', 'module' => 'VMS', 'description' => 'Full operational administrative access to VMS.'],
            ['name' => 'Manage VMS Teams', 'slug' => 'vms.manage_teams', 'module' => 'VMS', 'description' => 'Manage VMS teams.'],
            ['name' => 'Manage VMS Shifts', 'slug' => 'vms.manage_shifts', 'module' => 'VMS', 'description' => 'Manage VMS shifts.'],
            ['name' => 'Manage VMS Signups', 'slug' => 'vms.manage_signups', 'module' => 'VMS', 'description' => 'Manage volunteer signups and waitlists.'],
            ['name' => 'Export VMS Roster', 'slug' => 'vms.export', 'module' => 'VMS', 'description' => 'Export volunteer rosters.'],
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
