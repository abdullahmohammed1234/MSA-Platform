<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\PlatformChange;
use App\Models\PlatformRelease;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase30ReleaseManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $creatorUser;
    private User $approverUser;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $superRole = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);

        $viewPerm = Permission::firstOrCreate(['slug' => 'platform.releases.view'], ['name' => 'View Releases', 'module' => 'platform']);
        $managePerm = Permission::firstOrCreate(['slug' => 'platform.releases.manage'], ['name' => 'Manage Releases', 'module' => 'platform']);
        $approvePerm = Permission::firstOrCreate(['slug' => 'platform.releases.approve'], ['name' => 'Approve Releases', 'module' => 'platform']);
        $verifyPerm = Permission::firstOrCreate(['slug' => 'platform.releases.verify'], ['name' => 'Verify Releases', 'module' => 'platform']);
        $changeViewPerm = Permission::firstOrCreate(['slug' => 'platform.changes.view'], ['name' => 'View Changes', 'module' => 'platform']);
        $changeManagePerm = Permission::firstOrCreate(['slug' => 'platform.changes.manage'], ['name' => 'Manage Changes', 'module' => 'platform']);

        $superRole->permissions()->attach([
            $viewPerm->id,
            $managePerm->id,
            $approvePerm->id,
            $verifyPerm->id,
            $changeViewPerm->id,
            $changeManagePerm->id,
        ]);

        $this->adminUser = User::factory()->create();
        $this->adminUser->roles()->attach($superRole);

        $this->creatorUser = User::factory()->create();
        $this->creatorUser->roles()->attach($adminRole);

        $this->approverUser = User::factory()->create();
        $this->approverUser->roles()->attach($adminRole);

        $this->regularUser = User::factory()->create();
    }

    public function test_unauthenticated_user_cannot_access_release_endpoints(): void
    {
        $response = $this->getJson('/api/v1/admin/releases');
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_without_permission_cannot_access_releases(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/v1/admin/releases');

        $response->assertStatus(403);
    }

    public function test_admin_can_view_releases_overview_and_current_release(): void
    {
        PlatformRelease::create([
            'release_identifier' => 'v1.30.0-prod',
            'application_version' => '1.30.0',
            'api_version' => 'v1',
            'frontend_build' => 'build-20260914-001',
            'status' => 'SUCCESSFUL',
            'is_active' => true,
            'released_at' => now(),
            'release_notes' => 'Production Release Phase 30',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/releases');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);

        $currentRes = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/releases/current');

        $currentRes->assertStatus(200)
            ->assertJsonPath('data.release_identifier', 'v1.30.0-prod');
    }

    public function test_create_release_and_change_registration(): void
    {
        $payload = [
            'release_identifier' => 'v1.31.0-rc1',
            'application_version' => '1.31.0',
            'api_version' => 'v1',
            'frontend_build' => 'build-rc1',
            'release_notes' => 'Release candidate for testing',
            'affected_applications' => ['platform', 'ems'],
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/releases', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.release_identifier', 'v1.31.0-rc1')
            ->assertJsonPath('data.status', 'PLANNED');

        $this->assertDatabaseHas('platform_releases', [
            'release_identifier' => 'v1.31.0-rc1',
            'application_version' => '1.31.0',
        ]);

        $release = PlatformRelease::where('release_identifier', 'v1.31.0-rc1')->first();

        // Register change for release
        $changePayload = [
            'release_id' => $release->id,
            'category' => 'backend_code',
            'title' => 'Update Release Engine',
            'description' => 'Added change impact analysis',
            'affected_applications' => ['admin_portal', 'api_gateway'],
            'affected_services' => ['releases', 'audit'],
            'impact_level' => 'MEDIUM',
        ];

        $changeRes = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/changes', $changePayload);

        $changeRes->assertStatus(201)
            ->assertJsonPath('data.title', 'Update Release Engine');

        $this->assertDatabaseHas('platform_changes', [
            'category' => 'backend_code',
            'title' => 'Update Release Engine',
        ]);
    }

    public function test_separation_of_duties_prevents_creator_from_approving_own_release(): void
    {
        // creatorUser (admin role, non super-admin) creates release
        $release = PlatformRelease::create([
            'release_identifier' => 'v1.32.0-prod',
            'application_version' => '1.32.0',
            'status' => 'PLANNED',
            'created_by' => $this->creatorUser->id,
        ]);

        // creatorUser tries to approve own release -> 422 separation of duties
        $response = $this->actingAs($this->creatorUser)
            ->postJson("/api/v1/admin/releases/{$release->id}/approve", [
                'release_notes' => 'Self approving test',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Separation of duties required: You cannot approve a release that you created.');

        // approverUser (different admin user) approves -> 200
        $approverResponse = $this->actingAs($this->approverUser)
            ->postJson("/api/v1/admin/releases/{$release->id}/approve", [
                'release_notes' => 'Approved by independent approver',
            ]);

        $approverResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'APPROVED')
            ->assertJsonPath('data.approved_by', $this->approverUser->id);
    }

    public function test_post_release_verification_and_rollback_readiness(): void
    {
        $prevRelease = PlatformRelease::create([
            'release_identifier' => 'v1.29.0-prod',
            'application_version' => '1.29.0',
            'status' => 'SUCCESSFUL',
        ]);

        $release = PlatformRelease::create([
            'release_identifier' => 'v1.30.0-prod',
            'application_version' => '1.30.0',
            'status' => 'DEPLOYED',
            'previous_release_id' => $prevRelease->id,
        ]);

        PlatformChange::create([
            'release_id' => $release->id,
            'change_identifier' => 'CHG-1001',
            'category' => 'migration',
            'title' => 'Schema Change',
            'affected_applications' => ['database'],
            'affected_services' => ['migrations'],
            'author_user_id' => $this->adminUser->id,
        ]);

        // Run verification
        $verifyRes = $this->actingAs($this->adminUser)
            ->postJson("/api/v1/admin/releases/{$release->id}/verify");

        $verifyRes->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'verification_status',
                    'probes' => [
                        'database',
                        'cache',
                        'queue',
                        'scheduler',
                        'operational_alerts',
                    ],
                ],
            ]);

        // Check Rollback Readiness
        $rollbackRes = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/admin/releases/{$release->id}/rollback");

        $rollbackRes->assertStatus(200)
            ->assertJsonPath('data.rollback_readiness', 'ROLLBACK_MANUAL')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'release_identifier',
                    'rollback_readiness',
                    'blockers',
                    'manual_recovery_steps',
                ],
            ]);
    }

    public function test_release_timeline_reconstruction(): void
    {
        $release = PlatformRelease::create([
            'release_identifier' => 'v1.30.0-prod',
            'application_version' => '1.30.0',
            'status' => 'SUCCESSFUL',
            'released_at' => now()->subHours(2),
        ]);

        PlatformChange::create([
            'release_id' => $release->id,
            'change_identifier' => 'CHG-2001',
            'category' => 'backend_code',
            'title' => 'Refactored Auth Controller',
            'affected_applications' => ['api'],
            'author_user_id' => $this->adminUser->id,
            'created_at' => now()->subHours(3),
        ]);

        $timelineRes = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/admin/releases/{$release->id}/timeline");

        $timelineRes->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'release_identifier',
                    'timeline',
                    'correlation_semantics',
                ],
            ]);
    }

    public function test_release_comparison(): void
    {
        $relA = PlatformRelease::create([
            'release_identifier' => 'v1.29.0-prod',
            'application_version' => '1.29.0',
            'status' => 'SUCCESSFUL',
        ]);

        $relB = PlatformRelease::create([
            'release_identifier' => 'v1.30.0-prod',
            'application_version' => '1.30.0',
            'status' => 'SUCCESSFUL',
        ]);

        PlatformChange::create([
            'release_id' => $relB->id,
            'change_identifier' => 'CHG-3001',
            'category' => 'remediation_action',
            'title' => 'Auto-Remediation rule',
            'affected_applications' => ['ops'],
            'author_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/admin/releases/compare?release_a={$relA->id}&release_b={$relB->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'release_a',
                    'release_b',
                    'comparison',
                ],
            ]);
    }
}
