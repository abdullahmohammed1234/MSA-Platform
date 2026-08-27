<?php

namespace Tests\Feature\Phase15;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ApplicationAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApplicationAccessTest extends TestCase
{
    use RefreshDatabase;

    protected $appAccessService;
    protected $adminUser;
    protected $normalUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->appAccessService = app(ApplicationAccessService::class);

        // Seed roles & permissions
        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $superAdminRole = Role::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
        $memberRole = Role::create(['name' => 'Member', 'slug' => 'member']);
        $volunteerRole = Role::create(['name' => 'Volunteer', 'slug' => 'volunteer']);

        $manageUsersPermission = Permission::create([
            'name' => 'Manage Users',
            'slug' => 'manage_users',
            'module' => 'platform'
        ]);
        $adminRole->permissions()->attach($manageUsersPermission);

        // Create users
        $this->adminUser = User::factory()->create();
        $this->adminUser->roles()->attach($adminRole);

        $this->normalUser = User::factory()->create();
        $this->normalUser->roles()->attach($memberRole);
    }

    /**
     * Test explicit grant, revoke, and duplicates prevention.
     */
    public function test_grant_and_revoke_explicit_access(): void
    {
        $this->assertFalse($this->appAccessService->canAccess($this->normalUser, 'cms'));

        // Grant CMS access
        $this->appAccessService->grant($this->normalUser, 'cms', $this->adminUser);
        $this->assertTrue($this->appAccessService->canAccess($this->normalUser, 'cms'));

        // Verify duplicate grant does not crash and keeps record count unique
        $this->appAccessService->grant($this->normalUser, 'cms', $this->adminUser);
        $this->assertTrue($this->appAccessService->canAccess($this->normalUser, 'cms'));

        // Revoke CMS access
        $this->appAccessService->revoke($this->normalUser, 'cms', $this->adminUser);
        $this->assertFalse($this->appAccessService->canAccess($this->normalUser, 'cms'));
    }

    /**
     * Test role-based automatic bypass for privileged admins.
     */
    public function test_privileged_admin_bypass(): void
    {
        // Admin has access to all portals by default
        $this->assertTrue($this->appAccessService->canAccess($this->adminUser, 'cms'));
        $this->assertTrue($this->appAccessService->canAccess($this->adminUser, 'dams'));
        $this->assertTrue($this->appAccessService->canAccess($this->adminUser, 'ems'));
        $this->assertTrue($this->appAccessService->canAccess($this->adminUser, 'admin-portal'));

        // Check source is 'privileged'
        $apps = $this->appAccessService->accessibleApplications($this->adminUser);
        $this->assertEquals('privileged', $apps['cms']['source']);
        $this->assertEquals('privileged', $apps['dams']['source']);
        $this->assertEquals('privileged', $apps['ems']['source']);
        $this->assertEquals('privileged', $apps['admin-portal']['source']);
    }

    /**
     * Test Dawah Academy learner access rules for volunteer/mentor roles.
     */
    public function test_dawah_academy_learner_roles_bypass(): void
    {
        // Normal user does not have Dawah Academy access
        $this->assertFalse($this->appAccessService->canAccess($this->normalUser, 'dawah-academy'));

        // Assign Volunteer role
        $volunteerRole = Role::where('slug', 'volunteer')->first();
        $this->normalUser->roles()->sync([$volunteerRole->id]);

        // Volunters have automatic Dawah Academy access
        $this->assertTrue($this->appAccessService->canAccess($this->normalUser, 'dawah-academy'));

        $apps = $this->appAccessService->accessibleApplications($this->normalUser);
        $this->assertEquals('role', $apps['dawah-academy']['source']);
    }

    /**
     * Test cross-application access isolation rules.
     */
    public function test_cross_application_access_isolation(): void
    {
        // Grant CMS only
        $this->appAccessService->grant($this->normalUser, 'cms', $this->adminUser);

        $this->assertTrue($this->appAccessService->canAccess($this->normalUser, 'cms'));
        $this->assertFalse($this->appAccessService->canAccess($this->normalUser, 'dams'));
        $this->assertFalse($this->appAccessService->canAccess($this->normalUser, 'ems'));
        $this->assertFalse($this->appAccessService->canAccess($this->normalUser, 'admin-portal'));
    }

    /**
     * Test backend route middleware blocks.
     */
    public function test_route_middleware_blocks_unauthorized_users(): void
    {
        // Unauthenticated -> 401
        $this->getJson('/api/v1/cms/dashboard')->assertStatus(401);

        // Authenticated but no CMS access -> 403
        $this->actingAs($this->normalUser)
            ->getJson('/api/v1/cms/dashboard')
            ->assertStatus(403);

        // Grant CMS access -> 200 (base dashboard is allowed by application access)
        $this->appAccessService->grant($this->normalUser, 'cms', $this->adminUser);
        
        $this->actingAs($this->normalUser)
            ->getJson('/api/v1/cms/dashboard')
            ->assertStatus(200);

        // But protected operations (e.g. managing team members) are still blocked by permission guard
        $this->actingAs($this->normalUser)
            ->getJson('/api/v1/cms/team')
            ->assertStatus(403);

        // Grant the CMS manage_team permission
        $manageTeam = Permission::create(['name' => 'Manage Team', 'slug' => 'manage_team', 'module' => 'cms']);
        $this->normalUser->permissions()->attach($manageTeam);

        // Now they should pass the permission guard too!
        $this->actingAs($this->normalUser)
            ->getJson('/api/v1/cms/team')
            ->assertStatus(200);
    }

    /**
     * Test public customer tickets bypass on EMS operational routes.
     */
    public function test_ems_public_my_tickets_remains_accessible(): void
    {
        // Normal user does not have EMS application access
        $this->assertFalse($this->appAccessService->canAccess($this->normalUser, 'ems'));

        // Normal user CAN still fetch their own public tickets
        $this->actingAs($this->normalUser)
            ->getJson('/api/v1/ems/public/my-tickets')
            ->assertStatus(200);

        // But they CANNOT access operational EMS dashboard
        $this->actingAs($this->normalUser)
            ->getJson('/api/v1/ems/dashboard')
            ->assertStatus(403);
    }

    /**
     * Test audit logging on grant and revoke.
     */
    public function test_audit_logs_on_grant_and_revoke(): void
    {
        $this->appAccessService->grant($this->normalUser, 'cms', $this->adminUser);
        
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'action' => 'grant_application_access',
            'target_type' => User::class,
            'target_id' => $this->normalUser->id
        ]);

        $this->appAccessService->revoke($this->normalUser, 'cms', $this->adminUser);
        
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->adminUser->id,
            'action' => 'revoke_application_access',
            'target_type' => User::class,
            'target_id' => $this->normalUser->id
        ]);
    }

    /**
     * Test Admin Portal access restrictions and persistence.
     */
    public function test_admin_portal_access_boundaries_and_persistence(): void
    {
        // Create an ordinary user (has no roles or permissions initially)
        $user = User::factory()->create();

        // 1. User without Admin application access is denied entry (TEST 1 / TEST 10)
        // (Even if they are assigned an admin-related permission)
        $manageUsersPermission = Permission::where('slug', 'manage_users')->first();
        $user->permissions()->attach($manageUsersPermission);
        
        $this->assertFalse($this->appAccessService->canAccess($user, 'admin-portal'));

        // Check using route middleware (TEST 10: RBAC role/permission alone is denied)
        $this->actingAs($user)
            ->getJson('/api/v1/admin/application-access') // Endpoint protected by app.access:admin-portal
            ->assertStatus(403);

        // 2. Granting Admin application access allows the user to enter Admin (TEST 2)
        $this->appAccessService->grant($user, 'admin-portal', $this->adminUser);
        $this->assertTrue($this->appAccessService->canAccess($user, 'admin-portal'));

        $this->actingAs($user)
            ->getJson('/api/v1/admin/application-access')
            ->assertStatus(200);

        // 3. Removing Admin application access prevents Admin access (TEST 3)
        $this->appAccessService->revoke($user, 'admin-portal', $this->adminUser);
        $this->assertFalse($this->appAccessService->canAccess($user, 'admin-portal'));

        $this->actingAs($user)
            ->getJson('/api/v1/admin/application-access')
            ->assertStatus(403);

        // 4. After removal, reloading/re-fetching application access does NOT restore access (TEST 4 / TEST 5 / TEST 6)
        $apps = $this->appAccessService->accessibleApplications($user);
        $this->assertFalse($apps['admin-portal']['access']);
        $this->assertEquals('none', $apps['admin-portal']['source']);

        // Check via Controller API to make sure UI gets correct persisted state (TEST 6)
        $this->actingAs($this->adminUser)
            ->getJson("/api/v1/admin/application-access/{$user->id}")
            ->assertStatus(200)
            ->assertJsonPath('application_access.admin-portal.access', false)
            ->assertJsonPath('application_access.admin-portal.source', 'none');

        // 5. The Superadmin can grant and revoke access via Controller API (TEST 7)
        // Grant via API update:
        $this->actingAs($this->adminUser)
            ->putJson("/api/v1/admin/application-access/{$user->id}", [
                'access' => [
                    'main-website' => false,
                    'cms' => false,
                    'dawah-academy' => false,
                    'dams' => false,
                    'ems' => false,
                    'admin-portal' => true
                ]
            ])
            ->assertStatus(200)
            ->assertJsonPath('application_access.admin-portal.access', true)
            ->assertJsonPath('application_access.admin-portal.source', 'explicit');

        // Confirm database record exists
        $this->assertDatabaseHas('application_access', [
            'user_id' => $user->id,
            'application' => 'admin-portal'
        ]);

        // Revoke via API update:
        $this->actingAs($this->adminUser)
            ->putJson("/api/v1/admin/application-access/{$user->id}", [
                'access' => [
                    'main-website' => false,
                    'cms' => false,
                    'dawah-academy' => false,
                    'dams' => false,
                    'ems' => false,
                    'admin-portal' => false
                ]
            ])
            ->assertStatus(200)
            ->assertJsonPath('application_access.admin-portal.access', false)
            ->assertJsonPath('application_access.admin-portal.source', 'none');

        // Confirm database record is deleted
        $this->assertDatabaseMissing('application_access', [
            'user_id' => $user->id,
            'application' => 'admin-portal'
        ]);

        // 6. RBAC permissions continue to behave correctly (TEST 8)
        // User with permission but no app access gets 403 on app routes
        $this->actingAs($user)
            ->getJson('/api/v1/admin/application-access')
            ->assertStatus(403);

        // Grant app access back
        $this->appAccessService->grant($user, 'admin-portal', $this->adminUser);

        // Now they have both app access AND manage_users permission, so they get 200
        $this->actingAs($user)
            ->getJson('/api/v1/admin/application-access')
            ->assertStatus(200);

        // Remove manage_users permission
        $user->permissions()->detach($manageUsersPermission);

        // They still have app access, but lack the permission, so they should fail the permission guard (TEST 8)
        $this->actingAs($user)
            ->getJson('/api/v1/admin/application-access')
            ->assertStatus(403);
    }
}
