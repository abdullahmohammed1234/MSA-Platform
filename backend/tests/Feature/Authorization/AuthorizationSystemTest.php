<?php

namespace Tests\Feature\Authorization;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Automatically runs DatabaseSeeder which defines our roles, permissions, and test users
        $this->seed();
    }

    /**
     * Test database seeding worked properly.
     */
    public function test_database_seeding_creates_roles_and_permissions()
    {
        $this->assertDatabaseHas('roles', ['slug' => 'super-admin']);
        $this->assertDatabaseHas('roles', ['slug' => 'admin']);
        $this->assertDatabaseHas('roles', ['slug' => 'volunteer']);
        $this->assertDatabaseHas('permissions', ['slug' => 'manage_users']);
        $this->assertDatabaseHas('permissions', ['slug' => 'manage_courses']);
    }

    /**
     * Test role assignment helper methods.
     */
    public function test_user_can_be_assigned_and_removed_from_roles()
    {
        $user = User::factory()->create();
        $this->assertFalse($user->hasRole('admin'));

        $user->assignRole('admin');
        $this->assertTrue($user->hasRole('admin'));

        $user->removeRole('admin');
        $this->assertFalse($user->hasRole('admin'));
    }

    /**
     * Test role checks with multiple roles.
     */
    public function test_user_has_any_role_helper()
    {
        $user = User::factory()->create();
        $user->assignRole('mentor');

        $this->assertTrue($user->hasAnyRole(['admin', 'mentor']));
        $this->assertFalse($user->hasAnyRole(['admin', 'super-admin']));
    }

    /**
     * Test direct and inherited permissions.
     */
    public function test_user_inherits_permissions_from_roles()
    {
        $user = User::factory()->create();
        $this->assertFalse($user->hasPermission('manage_courses'));

        // Volunteer does not have manage_courses
        $user->assignRole('volunteer');
        $this->assertFalse($user->hasPermission('manage_courses'));

        // Dawah Coordinator has manage_courses
        $user->assignRole('dawah-coordinator');
        $this->assertTrue($user->hasPermission('manage_courses'));

        // Remove Dawah Coordinator role
        $user->removeRole('dawah-coordinator');
        $this->assertFalse($user->hasPermission('manage_courses'));

        // Grant direct permission
        $user->givePermission('manage_courses');
        $this->assertTrue($user->hasPermission('manage_courses'));
    }

    /**
     * Test super admin bypass.
     */
    public function test_super_admin_bypasses_all_permission_checks()
    {
        $superAdmin = User::where('email', 'superadmin@example.com')->first();
        $this->assertTrue($superAdmin->hasRole('super-admin'));
        
        // Even if we check a random/unseeded permission slug, super-admin is true
        $this->assertTrue($superAdmin->hasPermission('non_existent_permission'));
    }

    /**
     * Test route protection with Permission middleware.
     */
    public function test_middleware_restricts_unauthorized_users_by_permission()
    {
        $volunteer = User::where('email', 'volunteer@example.com')->first();
        $coordinator = User::where('email', 'coordinator@example.com')->first();

        // Volunteer tries to create course (requires manage_courses permission)
        $response = $this->actingAs($volunteer)
            ->postJson(route('api.admin.courses.store'), [
                'title' => 'New Dawah Course',
                'slug' => 'new-dawah-course',
                'description' => 'Course description',
            ]);

        $response->assertStatus(403);

        // Coordinator tries to create course (has manage_courses permission)
        $response = $this->actingAs($coordinator)
            ->postJson(route('api.admin.courses.store'), [
                'title' => 'New Dawah Course',
                'slug' => 'new-dawah-course',
                'description' => 'Course description',
            ]);

        $response->assertStatus(201);
    }

    /**
     * Test Super Admin protection policy - Admin can modify Super Admin because they are equivalent roles.
     */
    public function test_admin_can_modify_super_admin_users()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $superAdmin = User::where('email', 'superadmin@example.com')->first();
        $volunteer = User::where('email', 'volunteer@example.com')->first();

        // Admin can assign roles to Super Admin
        $response = $this->actingAs($admin)
            ->postJson(route('api.admin.users.roles.assign', $superAdmin->uuid), [
                'roles' => ['volunteer'],
            ]);
        $response->assertStatus(200);

        // Admin can modify Super Admin direct permissions
        $response = $this->actingAs($admin)
            ->postJson(route('api.admin.users.permissions.assign', $superAdmin->uuid), [
                'permissions' => ['manage_courses'],
            ]);
        $response->assertStatus(200);

        // Restore Super Admin role to $superAdmin since the previous test successfully downgraded them
        $superAdmin->syncRoles(['super-admin']);

        // Super Admin CAN assign roles to Super Admin (or anyone)
        $response = $this->actingAs($superAdmin)
            ->postJson(route('api.admin.users.roles.assign', $volunteer->uuid), [
                'roles' => ['mentor'],
            ]);
        $response->assertStatus(200);
    }

    /**
     * Test audit logs are recorded.
     */
    public function test_audit_logs_are_saved_when_roles_are_assigned()
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $volunteer = User::where('email', 'volunteer@example.com')->first();

        // Assign roles via API
        $this->actingAs($admin)
            ->postJson(route('api.admin.users.roles.assign', $volunteer->uuid), [
                'roles' => ['volunteer', 'mentor'],
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'roles_synced',
            'target_type' => User::class,
            'target_id' => $volunteer->id,
        ]);
    }
}
