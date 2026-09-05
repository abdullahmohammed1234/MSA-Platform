<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformHealthMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $healthPermission = Permission::firstOrCreate([
            'slug' => 'platform.health',
            'name' => 'View Platform Health',
            'module' => 'Platform',
        ]);

        $adminRole = Role::firstOrCreate([
            'slug' => 'admin',
            'name' => 'Admin',
        ]);

        $adminRole->permissions()->sync([$healthPermission->id]);

        $this->admin = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $this->admin->roles()->sync([$adminRole->id]);

        $this->regularUser = User::factory()->create([
            'is_active' => true,
        ]);
    }

    /** @test */
    public function guests_cannot_access_platform_health_endpoints()
    {
        $this->getJson('/api/v1/admin/platform/health/history')->assertStatus(401);
        $this->postJson('/api/v1/admin/platform/health/snapshot')->assertStatus(401);
    }

    /** @test */
    public function unprivileged_users_are_forbidden_from_platform_health()
    {
        $this->actingAs($this->regularUser);

        $this->getJson('/api/v1/admin/platform/health/history')->assertStatus(403);
        $this->postJson('/api/v1/admin/platform/health/snapshot')->assertStatus(403);
    }

    /** @test */
    public function authorized_admin_can_record_and_fetch_health_snapshots()
    {
        $this->actingAs($this->admin);

        // Record a snapshot
        $snapshotRes = $this->postJson('/api/v1/admin/platform/health/snapshot')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $data = $snapshotRes->json('data');
        $this->assertNotNull($data);
        $this->assertArrayHasKey('apps_health', $data);
        $this->assertArrayHasKey('services_health', $data);

        // Verify all 9 applications exist in snapshot probe
        $apps = ['ems', 'main-website', 'cms', 'dawah-academy', 'dams', 'store', 'donations', 'sponsorship', 'mlibms'];
        foreach ($apps as $app) {
            $this->assertArrayHasKey($app, $data['apps_health']);
        }

        // Verify platform services exist in snapshot probe
        $services = ['database', 'storage', 'email', 'queues'];
        foreach ($services as $service) {
            $this->assertArrayHasKey($service, $data['services_health']);
        }

        // Fetch history
        $historyRes = $this->getJson('/api/v1/admin/platform/health/history')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($historyRes->json('data'));
    }
}
