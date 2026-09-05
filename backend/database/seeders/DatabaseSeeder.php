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
                'name' => 'Store Administrator',
                'slug' => 'store-administrator',
                'description' => 'Manages MSA Store merchandise catalogue, inventory, orders, and fulfillment.',
            ],
            [
                'name' => 'Store Staff',
                'slug' => 'store-staff',
                'description' => 'Manages order processing, inventory checks, and customer order status updates.',
            ],
            [
                'name' => 'Donations Administrator',
                'slug' => 'dms-administrator',
                'description' => 'Full control over Donations Management System, donor records, refunds, and reports.',
            ],
            [
                'name' => 'Donations Staff',
                'slug' => 'dms-staff',
                'description' => 'Manages donation records, donor lookup, and status tracking.',
            ],
            [
                'name' => 'Sponsorship Administrator',
                'slug' => 'spms-administrator',
                'description' => 'Full administrative management of corporate partnerships, agreements, commitments, and financial reporting.',
            ],
            [
                'name' => 'Sponsorship Staff',
                'slug' => 'spms-staff',
                'description' => 'Manages partner communications, organization contacts, follow-up logs, and deliverable fulfillment.',
            ],
            [
                'name' => 'Sponsorship Viewer',
                'slug' => 'spms-viewer',
                'description' => 'Read-only access to SPMS dashboard, partner rosters, and sponsorship fulfillment progress.',
            ],
            [
                'name' => 'Library Administrator',
                'slug' => 'library-admin',
                'description' => 'Full control over Library Management System (MLibMS), cataloging, physical copy inventory, member loans, and overrides.',
            ],
            [
                'name' => 'Library Staff',
                'slug' => 'library-staff',
                'description' => 'Manages library cataloging, copy barcode management, and circulation member overrides.',
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
            ['name' => 'Manage Students', 'slug' => 'manage_students', 'module' => 'Academy', 'description' => 'Manage Academy student roster, enrollment administration, and student access.'],
            ['name' => 'Manage Learning Paths', 'slug' => 'manage_learning_paths', 'module' => 'Academy', 'description' => 'Manage learning pathways.'],
            ['name' => 'View Progress', 'slug' => 'view_progress', 'module' => 'Academy', 'description' => 'View volunteer and course progress.'],
            ['name' => 'Manage Progress', 'slug' => 'manage_progress', 'module' => 'Academy', 'description' => 'Modify volunteer course progress manually.'],
            ['name' => 'Manage Discussions', 'slug' => 'manage_discussions', 'module' => 'Academy', 'description' => 'Moderate forum threads and review reported content.'],

            // Website Module
            // Website Module — manage_events is RETIRED as event ownership (Phase 9).
            // EMS owns events via events.*. This slug remains only for CMS media-upload OR-chain compatibility.
            ['name' => 'CMS Upload Access (legacy manage_events)', 'slug' => 'manage_events', 'module' => 'Website', 'description' => 'RETIRED as CMS event ownership (Phase 9). Does not grant EMS administration. Retained only as a legacy OR-chain slug for CMS contextual media uploads. EMS uses events.* permissions.'],
            ['name' => 'Manage Announcements', 'slug' => 'manage_announcements', 'module' => 'Website', 'description' => 'Manage homepage announcements.'],
            ['name' => 'Manage Resources', 'slug' => 'manage_resources', 'module' => 'Website', 'description' => 'Manage public library resources.'],
            ['name' => 'Manage Homepage', 'slug' => 'manage_homepage', 'module' => 'Website', 'description' => 'Manage homepage sections and content.'],
            ['name' => 'Manage Team', 'slug' => 'manage_team', 'module' => 'Website', 'description' => 'Manage team members and display order.'],
            ['name' => 'Manage Media', 'slug' => 'manage_media', 'module' => 'Website', 'description' => 'Manage and upload reusable media assets.'],

            // Store Module
            ['name' => 'View Store Admin', 'slug' => 'store.view', 'module' => 'Store', 'description' => 'Access Store Admin control plane, products list, inventory, and orders.'],
            ['name' => 'Manage Store Products', 'slug' => 'store.manage_products', 'module' => 'Store', 'description' => 'Create, edit, publish, and delete merchandise products and variants.'],
            ['name' => 'Manage Store Inventory', 'slug' => 'store.manage_inventory', 'module' => 'Store', 'description' => 'Manage stock quantities, restocking alerts, and inventory logs.'],
            ['name' => 'Manage Store Orders', 'slug' => 'store.manage_orders', 'module' => 'Store', 'description' => 'Fulfill, process, update order statuses, and issue customer refunds.'],
            ['name' => 'Store Administrator', 'slug' => 'store.admin', 'module' => 'Store', 'description' => 'Full administrative access over Store settings, products, inventory, and analytics.'],
            ['name' => 'View Store Products', 'slug' => 'store.products.view', 'module' => 'Store', 'description' => 'View products in the Store admin portal.'],
            ['name' => 'Create Store Products', 'slug' => 'store.products.create', 'module' => 'Store', 'description' => 'Create new merchandise products and variants.'],
            ['name' => 'Update Store Products', 'slug' => 'store.products.update', 'module' => 'Store', 'description' => 'Edit merchandise products, variants, and product images.'],
            ['name' => 'Delete Store Products', 'slug' => 'store.products.delete', 'module' => 'Store', 'description' => 'Archive or delete merchandise products.'],
            ['name' => 'View Inventory', 'slug' => 'store.inventory.view', 'module' => 'Store', 'description' => 'View stock levels and inventory adjustment logs.'],
            ['name' => 'Update Inventory', 'slug' => 'store.inventory.update', 'module' => 'Store', 'description' => 'Perform manual inventory stock adjustments.'],
            ['name' => 'View Store Orders', 'slug' => 'store.orders.view', 'module' => 'Store', 'description' => 'View customer merchandise orders and details.'],
            ['name' => 'Update Store Orders', 'slug' => 'store.orders.update', 'module' => 'Store', 'description' => 'Update order fulfillment status.'],
            ['name' => 'Refund Store Orders', 'slug' => 'store.orders.refund', 'module' => 'Store', 'description' => 'Issue refunds for paid merchandise orders.'],

            // Donations Module
            ['name' => 'View DMS Admin', 'slug' => 'donations.view', 'module' => 'Donations', 'description' => 'Access Donation Management System control plane and records.'],
            ['name' => 'Manage Donations', 'slug' => 'donations.manage', 'module' => 'Donations', 'description' => 'Manage donation records and donor lookup.'],
            ['name' => 'Refund Donations', 'slug' => 'donations.refund', 'module' => 'Donations', 'description' => 'Authorize and issue donation refunds via Square.'],
            ['name' => 'Donation Reports', 'slug' => 'donations.reports', 'module' => 'Donations', 'description' => 'Access financial donation reports.'],
            ['name' => 'Export Donations', 'slug' => 'donations.export', 'module' => 'Donations', 'description' => 'Export donation records as CSV.'],
            ['name' => 'Reconcile Donations', 'slug' => 'donations.reconcile', 'module' => 'Donations', 'description' => 'Run reconciliation routines with Square API.'],

            // Sponsorship Module (SPMS)
            ['name' => 'View Sponsorships', 'slug' => 'sponsorship.view', 'module' => 'Sponsorship', 'description' => 'View SPMS dashboard, corporate partners, opportunities, and sponsorship records.'],
            ['name' => 'Create Sponsorship Opportunities', 'slug' => 'sponsorship.create', 'module' => 'Sponsorship', 'description' => 'Register new partner organizations, contacts, and sponsorship opportunity packages.'],
            ['name' => 'Edit Sponsorship Details', 'slug' => 'sponsorship.edit', 'module' => 'Sponsorship', 'description' => 'Update partner organization info, contacts, follow-ups, and opportunity details.'],
            ['name' => 'Manage Sponsorships', 'slug' => 'sponsorship.manage', 'module' => 'Sponsorship', 'description' => 'Full administrative management of corporate partnerships, commitments, and status transitions.'],
            ['name' => 'Manage Sponsorship Agreements', 'slug' => 'sponsorship.agreements', 'module' => 'Sponsorship', 'description' => 'Execute, upload, and update formal sponsorship legal agreements.'],
            ['name' => 'Manage Sponsorship Payments', 'slug' => 'sponsorship.payments', 'module' => 'Sponsorship', 'description' => 'Record manual cash/cheque payments and create Square checkout links for commitments.'],
            ['name' => 'Manage Deliverables & Fulfillment', 'slug' => 'sponsorship.fulfillment', 'module' => 'Sponsorship', 'description' => 'Track benefit deliverables, in-kind contributions, and mark sponsorship fulfillment complete.'],
            ['name' => 'Export Sponsorship Reports', 'slug' => 'sponsorship.export', 'module' => 'Sponsorship', 'description' => 'Export SPMS financial reports, renewals, and partner logs in CSV format.'],
            ['name' => 'SPMS Administrator', 'slug' => 'sponsorship.admin', 'module' => 'Sponsorship', 'description' => 'Full administrative control over Sponsorship & Partnerships Management System.'],

            // Library Module (MLibMS)
            ['name' => 'View Library Admin', 'slug' => 'library.view', 'module' => 'Library', 'description' => 'Access MLibMS admin control plane and dashboard.'],
            ['name' => 'Catalog Books', 'slug' => 'library.catalog', 'module' => 'Library', 'description' => 'Create and edit bibliographic book catalog entries.'],
            ['name' => 'Manage Inventory Copies', 'slug' => 'library.copies', 'module' => 'Library', 'description' => 'Add and update physical book copy barcodes and condition.'],
            ['name' => 'Manage Members', 'slug' => 'library.members', 'module' => 'Library', 'description' => 'Manage library member roster, status, and search.'],
            ['name' => 'Manage Loans', 'slug' => 'library.loans', 'module' => 'Library', 'description' => 'Perform staff loans, returns, renewals, and overdue overrides.'],
            ['name' => 'Manage Reservations', 'slug' => 'library.reservations', 'module' => 'Library', 'description' => 'Manage hold queues and reservation fulfillments.'],
            ['name' => 'Library Reports', 'slug' => 'library.reports', 'module' => 'Library', 'description' => 'Access library circulation and inventory reports.'],
            ['name' => 'Library Settings', 'slug' => 'library.settings', 'module' => 'Library', 'description' => 'Configure library rules, loan limits, and due dates.'],
            ['name' => 'MLibMS Administrator', 'slug' => 'library.admin', 'module' => 'Library', 'description' => 'Full administrative control over MSA Library Management System.'],

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

            // Keep seed metadata current (e.g. Phase 9 manage_events retirement text).
            $permissions[$permData['slug']]->fill([
                'name' => $permData['name'],
                'module' => $permData['module'],
                'description' => $permData['description'],
            ])->save();
        }

        // 3. Map Permissions to Roles

        // Super Admin gets all permissions
        $roles['super-admin']->permissions()->sync(
            Permission::pluck('id')->toArray()
        );

        // Admin Role mapping
        $adminPermissions = [
            'manage_users', 'manage_courses', 'manage_modules', 'manage_lessons', 'manage_quizzes',
            'manage_certificates', 'manage_volunteers', 'manage_mentors', 'manage_students', 'manage_learning_paths',
            'view_progress', 'manage_progress', 'manage_discussions',
            'manage_events', 'manage_announcements', 'manage_resources',
            'manage_homepage', 'manage_team', 'manage_media',
            'store.view', 'store.manage_products', 'store.manage_inventory', 'store.manage_orders', 'store.admin',
            'store.products.view', 'store.products.create', 'store.products.update', 'store.products.delete',
            'store.inventory.view', 'store.inventory.update', 'store.orders.view', 'store.orders.update', 'store.orders.refund',
            'donations.view', 'donations.manage', 'donations.refund', 'donations.reports', 'donations.export', 'donations.reconcile',
            'sponsorship.view', 'sponsorship.create', 'sponsorship.edit', 'sponsorship.manage', 'sponsorship.agreements',
            'sponsorship.payments', 'sponsorship.fulfillment', 'sponsorship.export', 'sponsorship.admin',
            'library.view', 'library.catalog', 'library.copies', 'library.members', 'library.loans', 'library.reservations', 'library.reports', 'library.settings', 'library.admin',
            'view_analytics', 'view_reports', 'manage_analytics', 'export_analytics',
            'manage_queues', 'view_queue_status', 'retry_failed_jobs', 'manage_scheduler',
            'view_security', 'manage_security'
        ];
        $roles['admin']->permissions()->sync(
            Permission::whereIn('slug', $adminPermissions)->pluck('id')->toArray()
        );

        // Store Administrator Role mapping
        if (isset($roles['store-administrator'])) {
            $roles['store-administrator']->permissions()->sync(
                Permission::whereIn('slug', ['store.view', 'store.manage_products', 'store.manage_inventory', 'store.manage_orders', 'store.admin', 'store.products.view', 'store.products.create', 'store.products.update', 'store.products.delete', 'store.inventory.view', 'store.inventory.update', 'store.orders.view', 'store.orders.update', 'store.orders.refund', 'view_analytics'])->pluck('id')->toArray()
            );
        }

        // Store Staff Role mapping
        if (isset($roles['store-staff'])) {
            $roles['store-staff']->permissions()->sync(
                Permission::whereIn('slug', ['store.view', 'store.manage_inventory', 'store.manage_orders', 'store.products.view', 'store.inventory.view', 'store.inventory.update', 'store.orders.view', 'store.orders.update'])->pluck('id')->toArray()
            );
        }

        // DMS Administrator Role mapping
        if (isset($roles['dms-administrator'])) {
            $roles['dms-administrator']->permissions()->sync(
                Permission::whereIn('slug', ['donations.view', 'donations.manage', 'donations.refund', 'donations.reports', 'donations.export', 'donations.reconcile', 'view_analytics'])->pluck('id')->toArray()
            );
        }

        // DMS Staff Role mapping
        if (isset($roles['dms-staff'])) {
            $roles['dms-staff']->permissions()->sync(
                Permission::whereIn('slug', ['donations.view', 'donations.manage', 'donations.reports'])->pluck('id')->toArray()
            );
        }

        // SPMS Administrator Role mapping
        if (isset($roles['spms-administrator'])) {
            $roles['spms-administrator']->permissions()->sync(
                Permission::whereIn('slug', ['sponsorship.view', 'sponsorship.create', 'sponsorship.edit', 'sponsorship.manage', 'sponsorship.agreements', 'sponsorship.payments', 'sponsorship.fulfillment', 'sponsorship.export', 'sponsorship.admin', 'view_analytics'])->pluck('id')->toArray()
            );
        }

        // SPMS Staff Role mapping
        if (isset($roles['spms-staff'])) {
            $roles['spms-staff']->permissions()->sync(
                Permission::whereIn('slug', ['sponsorship.view', 'sponsorship.create', 'sponsorship.edit', 'sponsorship.fulfillment'])->pluck('id')->toArray()
            );
        }

        // SPMS Viewer Role mapping
        if (isset($roles['spms-viewer'])) {
            $roles['spms-viewer']->permissions()->sync(
                Permission::whereIn('slug', ['sponsorship.view'])->pluck('id')->toArray()
            );
        }

        // Library Administrator Role mapping
        if (isset($roles['library-admin'])) {
            $roles['library-admin']->permissions()->sync(
                Permission::whereIn('slug', ['library.view', 'library.catalog', 'library.copies', 'library.members', 'library.loans', 'library.reservations', 'library.reports', 'library.settings', 'library.admin', 'view_analytics'])->pluck('id')->toArray()
            );
        }

        // Library Staff Role mapping
        if (isset($roles['library-staff'])) {
            $roles['library-staff']->permissions()->sync(
                Permission::whereIn('slug', ['library.view', 'library.catalog', 'library.copies', 'library.members', 'library.loans', 'library.reservations'])->pluck('id')->toArray()
            );
        }

        // Director Role mapping
        $directorPermissions = ['manage_events', 'view_analytics', 'view_reports', 'export_analytics'];
        $roles['director']->permissions()->sync(
            Permission::whereIn('slug', $directorPermissions)->pluck('id')->toArray()
        );

        // Dawah Coordinator Role mapping (DAMS operator — not learner)
        $coordinatorPermissions = [
            'manage_courses', 'manage_modules', 'manage_lessons', 'manage_quizzes',
            'manage_certificates', 'manage_volunteers', 'manage_mentors', 'manage_students',
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

        // Seed Application Access for platform administrators
        $allApps = ['admin-portal', 'dawah-academy', 'cms', 'dams', 'ems', 'store', 'donations', 'sponsorship', 'mlibms'];
        foreach ($allApps as $app) {
            \Illuminate\Support\Facades\DB::table('application_access')->insertOrIgnore([
                'user_id' => $superAdminUser->id,
                'application' => $app,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            \Illuminate\Support\Facades\DB::table('application_access')->insertOrIgnore([
                'user_id' => $adminUser->id,
                'application' => $app,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        \Illuminate\Support\Facades\DB::table('application_access')->insertOrIgnore([
            ['user_id' => $coordinatorUser->id, 'application' => 'dams', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $coordinatorUser->id, 'application' => 'dawah-academy', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $mentorUser->id, 'application' => 'dawah-academy', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $volunteerUser->id, 'application' => 'dawah-academy', 'created_at' => now(), 'updated_at' => now()],
        ]);

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

        // 8. Seed the Event Management System (roles, permissions, categories)
        $this->call(EmsDatabaseSeeder::class);

        // 9. Seed Sponsorship & Partnerships Management System (roles and permissions)
        $this->call(SpmsRolePermissionSeeder::class);

        // 10. Platform notification permissions + preference backfill
        $this->call(NotificationSeeder::class);
    }
}
