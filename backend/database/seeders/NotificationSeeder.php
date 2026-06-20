<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Add notification permissions
        $permissionsData = [
            [
                'name' => 'Send Notifications',
                'slug' => 'send_notifications',
                'module' => 'Admin',
                'description' => 'Compose and broadcast manual notifications or announcements.',
            ],
            [
                'name' => 'Manage Notifications',
                'slug' => 'manage_notifications',
                'module' => 'Admin',
                'description' => 'View notification delivery logs, stats, and resend failed notifications.',
            ],
            [
                'name' => 'Manage Notification Templates',
                'slug' => 'manage_notification_templates',
                'module' => 'Admin',
                'description' => 'Manage design, branding, and copy of email and in-app templates.',
            ]
        ];

        $permissions = [];
        foreach ($permissionsData as $permData) {
            $permissions[$permData['slug']] = Permission::firstOrCreate(
                ['slug' => $permData['slug']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $permData['name'],
                    'module' => $permData['module'],
                    'description' => $permData['description'],
                ]
            );
        }

        // 2. Map new permissions to Roles

        // Super Admin gets all permissions
        $superAdmin = Role::where('slug', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->permissions()->syncWithoutDetaching(
                Permission::pluck('id')->toArray()
            );
        }

        // Admin gets all notification permissions
        $admin = Role::where('slug', 'admin')->first();
        if ($admin) {
            $adminPermissions = Permission::whereIn('slug', [
                'send_notifications',
                'manage_notifications',
                'manage_notification_templates'
            ])->pluck('id')->toArray();
            
            $admin->permissions()->syncWithoutDetaching($adminPermissions);
        }

        // Dawah Coordinator gets send and manage permissions
        $coordinator = Role::where('slug', 'dawah-coordinator')->first();
        if ($coordinator) {
            $coordPermissions = Permission::whereIn('slug', [
                'send_notifications',
                'manage_notifications'
            ])->pluck('id')->toArray();
            
            $coordinator->permissions()->syncWithoutDetaching($coordPermissions);
        }

        // 3. Backfill notification preferences for existing users
        $users = User::all();
        $count = 0;
        foreach ($users as $user) {
            if (!$user->notificationPreferences()->exists()) {
                $user->notificationPreferences()->create([
                    'course_completion' => true,
                    'new_announcements' => true,
                    'upcoming_training' => true,
                    'certificate_earned' => true,
                    'email_enabled' => true,
                    'in_app_enabled' => true,
                ]);
                $count++;
            }
        }

        $this->command->info("Notification permissions seeded and preferences backfilled for {$count} users.");
    }
}
