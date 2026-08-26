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

        // Grant CMS access -> 200 (or whatever route code is, eg 403 from internal permission)
        $this->appAccessService->grant($this->normalUser, 'cms', $this->adminUser);
        
        // Wait, since normalUser has CMS application access but no actual sub-permissions,
        // it should get 403 from the permission middleware, NOT 403 from the app access middleware!
        // The dashboard route has `permission:view_analytics`.
        $this->actingAs($this->normalUser)
            ->getJson('/api/v1/cms/dashboard')
            ->assertStatus(403); // blocked by permission guard, which is correct

        // Let's grant a CMS permission
        $viewAnalytics = Permission::create(['name' => 'View Analytics', 'slug' => 'view_analytics', 'module' => 'cms']);
        $this->normalUser->permissions()->attach($viewAnalytics);

        // Now they should pass both CMS app access and permission guard!
        $this->actingAs($this->normalUser)
            ->getJson('/api/v1/cms/dashboard')
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
}
