<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase23IntelligenceTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $normalUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Admin User
        $adminRole = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $permission = Permission::firstOrCreate(['slug' => 'platform.view'], ['name' => 'View Platform', 'module' => 'platform']);
        
        $this->adminUser = User::factory()->create();
        $this->adminUser->roles()->attach($adminRole);
        $this->adminUser->permissions()->attach($permission);

        // Grant Admin Portal App Access
        \App\Models\ApplicationAccess::create([
            'user_id' => $this->adminUser->id,
            'application' => 'admin-portal',
            'granted_by' => $this->adminUser->id,
        ]);

        // Create Normal User
        $this->normalUser = User::factory()->create();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $response = $this->getJson('/api/v1/admin/platform/intelligence');
        $response->assertStatus(401);
    }

    public function test_unauthorized_users_receive_403(): void
    {
        $response = $this->actingAs($this->normalUser, 'sanctum')
            ->getJson('/api/v1/admin/platform/intelligence');

        $response->assertStatus(403);
    }

    public function test_authorized_admin_receives_200_with_intelligence_payload(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/platform/intelligence?period=30d');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'overall_health',
                    'platform',
                    'domains' => [
                        'ems',
                        'donations',
                        'store',
                        'mlibms',
                        'communications',
                        'volunteers',
                        'feedback',
                    ],
                ],
            ]);
    }

    public function test_domain_intelligence_endpoints_return_correct_structures(): void
    {
        // EMS
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/platform/intelligence/ems?period=7d')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['kpis', 'funnel', 'financial', 'trends']]);

        // Donations
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/platform/intelligence/donations?period=30d')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['kpis', 'trends']]);

        // Store
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/platform/intelligence/store?period=90d')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['kpis', 'inventory_warnings', 'trends']]);

        // MLibMS
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/platform/intelligence/mlibms?period=this_year')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['kpis', 'trends']]);

        // Communications
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/platform/intelligence/communications?period=30d')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['kpis', 'trends']]);

        // Volunteers
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/platform/intelligence/volunteers?period=30d')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['kpis', 'trends']]);

        // Feedback
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/platform/intelligence/feedback?period=30d')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['kpis', 'distribution', 'trends']]);
    }

    public function test_custom_date_range_processing(): void
    {
        $start = now()->subDays(10)->format('Y-m-d');
        $end = now()->format('Y-m-d');

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/admin/platform/intelligence/ems?period=custom&start_date={$start}&end_date={$end}");

        $response->assertStatus(200);
        $this->assertEquals('custom', $response->json('data.period.type'));
    }
}
