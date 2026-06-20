<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Define Roles
        $rolesData = [
            [
                'name' => 'Super Admin',
                'slug' => 'super-admin',
                'description' => 'Full platform control and access to everything.',
            ],
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'System administrator with management capabilities but cannot manage super admins.',
            ],
            [
                'name' => 'Director',
                'slug' => 'director',
                'description' => 'Department director with analytics and event management capabilities.',
            ],
            [
                'name' => 'Dawah Coordinator',
                'slug' => 'dawah-coordinator',
                'description' => 'Manages Dawah Academy courses, lessons, mentors, and tracks volunteer progress.',
            ],
            [
                'name' => 'Mentor',
                'slug' => 'mentor',
                'description' => 'Provides guidance and tracks progress for assigned volunteers.',
            ],
            [
                'name' => 'Volunteer',
                'slug' => 'volunteer',
                'description' => 'Academy participant who can complete courses and earn certificates.',
            ],
            [
                'name' => 'Member',
                'slug' => 'member',
                'description' => 'MSA community member with access to member-only resources.',
            ],
            [
                'name' => 'Guest',
                'slug' => 'guest',
                'description' => 'Public viewer with basic read-only access.',
            ],
        ];

        $roles = [];
        foreach ($rolesData as $roleData) {
            $roles[$roleData['slug']] = Role::firstOrCreate(
                ['slug' => $roleData['slug']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $roleData['name'],
                    'description' => $roleData['description']
                ]
            );
        }

        // 2. Define Permissions grouped by module
        $permissionsData = [
            // Admin Module
            ['name' => 'Manage Users', 'slug' => 'manage_users', 'module' => 'Admin', 'description' => 'Create, edit, suspend, and view users.'],
            ['name' => 'Manage Roles', 'slug' => 'manage_roles', 'module' => 'Admin', 'description' => 'Create, edit, and delete roles.'],
            ['name' => 'Manage Permissions', 'slug' => 'manage_permissions', 'module' => 'Admin', 'description' => 'View and assign permissions.'],
            ['name' => 'Manage Settings', 'slug' => 'manage_settings', 'module' => 'Admin', 'description' => 'Manage global system configurations.'],

            // Academy Module
            ['name' => 'Manage Courses', 'slug' => 'manage_courses', 'module' => 'Academy', 'description' => 'Create, edit, publish, and delete courses.'],
            ['name' => 'Manage Modules', 'slug' => 'manage_modules', 'module' => 'Academy', 'description' => 'Create, edit, and delete modules.'],
            ['name' => 'Manage Lessons', 'slug' => 'manage_lessons', 'module' => 'Academy', 'description' => 'Manage course lessons.'],
            ['name' => 'Manage Quizzes', 'slug' => 'manage_quizzes', 'module' => 'Academy', 'description' => 'Manage quizzes and questions.'],
            ['name' => 'Manage Certificates', 'slug' => 'manage_certificates', 'module' => 'Academy', 'description' => 'Configure and issue certificates.'],
            ['name' => 'Manage Volunteers', 'slug' => 'manage_volunteers', 'module' => 'Academy', 'description' => 'Track and manage volunteers.'],
            ['name' => 'Manage Mentors', 'slug' => 'manage_mentors', 'module' => 'Academy', 'description' => 'Appoint and assign mentors.'],
            ['name' => 'Manage Learning Paths', 'slug' => 'manage_learning_paths', 'module' => 'Academy', 'description' => 'Manage learning pathways.'],
            ['name' => 'View Progress', 'slug' => 'view_progress', 'module' => 'Academy', 'description' => 'View volunteer and course progress.'],
            ['name' => 'Manage Progress', 'slug' => 'manage_progress', 'module' => 'Academy', 'description' => 'Modify volunteer course progress manually.'],
            ['name' => 'Manage Discussions', 'slug' => 'manage_discussions', 'module' => 'Academy', 'description' => 'Moderate forum threads and review reported content.'],

            // Website Module
            ['name' => 'Manage Events', 'slug' => 'manage_events', 'module' => 'Website', 'description' => 'Publish and edit public events.'],
            ['name' => 'Manage Announcements', 'slug' => 'manage_announcements', 'module' => 'Website', 'description' => 'Manage homepage announcements.'],
            ['name' => 'Manage Resources', 'slug' => 'manage_resources', 'module' => 'Website', 'description' => 'Manage public library resources.'],
            ['name' => 'Manage Homepage', 'slug' => 'manage_homepage', 'module' => 'Website', 'description' => 'Manage homepage sections and content.'],
            ['name' => 'Manage Team', 'slug' => 'manage_team', 'module' => 'Website', 'description' => 'Manage team members and display order.'],
            ['name' => 'Manage Media', 'slug' => 'manage_media', 'module' => 'Website', 'description' => 'Manage and upload reusable media assets.'],

            // Analytics Module
            ['name' => 'View Analytics', 'slug' => 'view_analytics', 'module' => 'Analytics', 'description' => 'Access dashboard analytics data.'],
            ['name' => 'View Reports', 'slug' => 'view_reports', 'module' => 'Analytics', 'description' => 'Generate and download reports.'],
            ['name' => 'Manage Analytics', 'slug' => 'manage_analytics', 'module' => 'Analytics', 'description' => 'Manage analytics configurations and aggregation schedules.'],
            ['name' => 'Export Analytics', 'slug' => 'export_analytics', 'module' => 'Analytics', 'description' => 'Export analytics data in CSV, Excel, or PDF format.'],

            // System Module
            ['name' => 'Manage Queues', 'slug' => 'manage_queues', 'module' => 'System', 'description' => 'Clean, flush, and control queue workers.'],
            ['name' => 'View Queue Status', 'slug' => 'view_queue_status', 'module' => 'System', 'description' => 'Monitor queue partition active and pending jobs.'],
            ['name' => 'Retry Failed Jobs', 'slug' => 'retry_failed_jobs', 'module' => 'System', 'description' => 'Re-run background jobs that failed.'],
            ['name' => 'Manage Scheduler', 'slug' => 'manage_scheduler', 'module' => 'System', 'description' => 'List and run scheduled cron tasks.'],

            // Security Module
            ['name' => 'View Security Dashboard', 'slug' => 'view_security', 'module' => 'Security', 'description' => 'Access system security log summaries, metrics, and incident reports.'],
            ['name' => 'Manage Security Settings', 'slug' => 'manage_security', 'module' => 'Security', 'description' => 'Hardening configs, session parameters, and lockout resets.'],
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

        // 3. Map Permissions to Roles

        // Super Admin gets all permissions
        $roles['super-admin']->permissions()->sync(
            Permission::pluck('id')->toArray()
        );

        // Admin Role mapping
        $adminPermissions = [
            'manage_users', 'manage_courses', 'manage_modules', 'manage_lessons', 'manage_quizzes',
            'manage_certificates', 'manage_volunteers', 'manage_mentors', 'manage_learning_paths',
            'view_progress', 'manage_progress', 'manage_discussions',
            'manage_events', 'manage_announcements', 'manage_resources',
            'manage_homepage', 'manage_team', 'manage_media',
            'view_analytics', 'view_reports', 'manage_analytics', 'export_analytics',
            'manage_queues', 'view_queue_status', 'retry_failed_jobs', 'manage_scheduler',
            'view_security', 'manage_security'
        ];
        $roles['admin']->permissions()->sync(
            Permission::whereIn('slug', $adminPermissions)->pluck('id')->toArray()
        );

        // Director Role mapping
        $directorPermissions = ['manage_events', 'view_analytics', 'view_reports', 'export_analytics'];
        $roles['director']->permissions()->sync(
            Permission::whereIn('slug', $directorPermissions)->pluck('id')->toArray()
        );

        // Dawah Coordinator Role mapping
        $coordinatorPermissions = [
            'manage_courses', 'manage_modules', 'manage_lessons', 'manage_quizzes',
            'manage_certificates', 'manage_volunteers', 'manage_mentors',
            'manage_learning_paths', 'view_progress', 'manage_progress', 'manage_discussions',
            'view_analytics'
        ];
        $roles['dawah-coordinator']->permissions()->sync(
            Permission::whereIn('slug', $coordinatorPermissions)->pluck('id')->toArray()
        );

        // Mentor Role mapping
        $mentorPermissions = ['manage_volunteers', 'view_progress'];
        $roles['mentor']->permissions()->sync(
            Permission::whereIn('slug', $mentorPermissions)->pluck('id')->toArray()
        );

        // 4. Seed Test Users

        // Super Admin
        $superAdminUser = User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        $superAdminUser->roles()->sync([$roles['super-admin']->id]);

        // Admin
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        $adminUser->roles()->sync([$roles['admin']->id]);

        // Dawah Coordinator
        $coordinatorUser = User::firstOrCreate(
            ['email' => 'coordinator@example.com'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Dawah Coordinator',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        $coordinatorUser->roles()->sync([$roles['dawah-coordinator']->id]);

        // Mentor
        $mentorUser = User::firstOrCreate(
            ['email' => 'mentor@example.com'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Mentor User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        $mentorUser->roles()->sync([$roles['mentor']->id]);

        // Volunteer
        $volunteerUser = User::firstOrCreate(
            ['email' => 'volunteer@example.com'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Volunteer User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        $volunteerUser->roles()->sync([$roles['volunteer']->id]);

        // 5. Seed CMS content
        $this->call(CmsSeeder::class);

        // 6. Seed Certification & Achievements
        $this->call(CertificationSeeder::class);

        // 7. Seed Discussion Categories
        $categoriesData = [
            ['name' => 'General Discussions', 'slug' => 'general'],
            ['name' => 'Theological Inquiry', 'slug' => 'theology'],
            ['name' => 'Street Outreach (Dawah) Tactics', 'slug' => 'street-dawah'],
            ['name' => 'Scholastic Resource Requests', 'slug' => 'resource-requests'],
        ];

        foreach ($categoriesData as $cat) {
            \App\Models\DiscussionCategory::firstOrCreate(
                ['slug' => $cat['slug']],
                ['name' => $cat['name']]
            );
        }
    }
}
