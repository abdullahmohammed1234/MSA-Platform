<?php

namespace Tests\Feature;

use App\Models\ApplicationAccess;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VmsSystemControlTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $normalUser;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $permission = Permission::firstOrCreate(['slug' => 'system.view'], ['name' => 'View System Control', 'module' => 'system']);

        $this->adminUser = User::factory()->create();
        $this->adminUser->roles()->attach($adminRole);
        $this->adminUser->permissions()->attach($permission);

        ApplicationAccess::create([
            'user_id' => $this->adminUser->id,
            'application' => 'admin-portal',
            'granted_by' => $this->adminUser->id,
        ]);

        $this->normalUser = User::factory()->create();
    }

    public function test_vms_system_info_endpoint(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/systems/vms');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'system' => [
                    'name' => 'Volunteer Management System (VMS)',
                    'slug' => 'vms',
                ],
            ]);
    }

    public function test_vms_system_health_endpoint(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/systems/vms/health');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'health' => [
                    'status' => 'operational',
                ],
            ]);
    }

    public function test_vms_system_metrics_endpoint(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/systems/vms/metrics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'metrics' => [
                    'total_opportunities',
                    'open_opportunities',
                    'total_teams',
                    'total_shifts',
                    'total_signups',
                    'active_signups',
                    'completed_signups',
                    'waitlisted_signups',
                ],
            ]);
    }

    public function test_vms_included_in_systems_control_plane_overview(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/systems');

        $response->assertStatus(200);
        $apps = $response->json('applications');
        $vmsApp = collect($apps)->firstWhere('id', 'vms');

        $this->assertNotNull($vmsApp, 'VMS application must be registered in Systems Control Plane overview.');
        $this->assertEquals('Volunteer Management System (VMS)', $vmsApp['name']);
        $this->assertEquals('/vms', $vmsApp['url']);
    }

    public function test_unauthenticated_vms_system_requests_fail(): void
    {
        $this->getJson('/api/v1/admin/systems/vms')->assertStatus(401);
        $this->getJson('/api/v1/admin/systems/vms/health')->assertStatus(401);
        $this->getJson('/api/v1/admin/systems/vms/metrics')->assertStatus(401);
    }
}
