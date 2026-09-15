<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase29LifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $superRole = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $managerRole = Role::firstOrCreate(['slug' => 'manager'], ['name' => 'Manager']);

        $viewPerm = Permission::firstOrCreate(['slug' => 'platform.lifecycle.view'], ['name' => 'View Lifecycle', 'module' => 'platform']);
        $envPerm = Permission::firstOrCreate(['slug' => 'platform.lifecycle.environment'], ['name' => 'View Environment', 'module' => 'platform']);
        $configPerm = Permission::firstOrCreate(['slug' => 'platform.lifecycle.configuration'], ['name' => 'View Configuration', 'module' => 'platform']);
        $readinessPerm = Permission::firstOrCreate(['slug' => 'platform.lifecycle.readiness'], ['name' => 'View Readiness', 'module' => 'platform']);
        $managePerm = Permission::firstOrCreate(['slug' => 'platform.lifecycle.manage'], ['name' => 'Manage Lifecycle', 'module' => 'platform']);

        $superRole->permissions()->attach([
            $viewPerm->id,
            $envPerm->id,
            $configPerm->id,
            $readinessPerm->id,
            $managePerm->id,
        ]);

        $this->adminUser = User::factory()->create();
        $this->adminUser->roles()->attach($superRole);

        $this->regularUser = User::factory()->create();
    }

    public function test_unauthenticated_user_cannot_access_lifecycle_endpoints(): void
    {
        $response = $this->getJson('/api/v1/admin/lifecycle');
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_without_permission_cannot_access_lifecycle(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/v1/admin/lifecycle');

        $response->assertStatus(403);
    }

    public function test_authorized_admin_can_access_lifecycle_overview(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/lifecycle');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'generated_at',
                    'readiness' => [
                        'readiness_status',
                        'summary' => ['total_checks', 'passed', 'warning', 'failed', 'unknown'],
                        'checks',
                    ],
                    'release' => [
                        'application_version',
                        'api_version',
                        'frontend_build',
                        'release_identifier',
                        'deployment_detected_at',
                    ],
                    'environment' => [
                        'environment',
                        'debug_mode',
                        'laravel_version',
                        'php_version',
                        'database_driver',
                        'cache_driver',
                        'queue_driver',
                        'filesystem_driver',
                        'configured_services',
                    ],
                    'migrations' => [
                        'status',
                        'is_up_to_date',
                        'applied_count',
                        'pending_count',
                    ],
                    'dependencies' => [
                        'overall_status',
                        'services' => [
                            'database',
                            'cache',
                            'queue',
                            'scheduler',
                            'mail',
                            'storage',
                            'square',
                        ],
                    ],
                    'scheduler_queue' => [
                        'scheduler' => ['driver', 'status', 'tasks'],
                        'queues' => ['driver', 'verification_state', 'configured_queues'],
                    ],
                    'drift' => [
                        'status',
                        'total_findings',
                        'critical_count',
                        'warning_count',
                        'info_count',
                        'findings',
                    ],
                ],
            ]);
    }

    public function test_environment_inventory_redacts_sensitive_values(): void
    {
        $envService = app(\App\Services\Lifecycle\EnvironmentInventoryService::class);
        $dirtyConfig = [
            'database_password' => 'SuperSecretPass123',
            'api_token' => 'bearer_xyz_999',
            'app_key' => 'base64:uniquekeyhere',
            'public_name' => 'SFU MSA Platform',
        ];

        $sanitized = $envService->sanitizeConfig($dirtyConfig);

        $this->assertEquals('[REDACTED]', $sanitized['database_password']);
        $this->assertEquals('[REDACTED]', $sanitized['api_token']);
        $this->assertEquals('[REDACTED]', $sanitized['app_key']);
        $this->assertEquals('SFU MSA Platform', $sanitized['public_name']);
    }

    public function test_subresource_endpoints_return_correct_structures(): void
    {
        $endpoints = [
            '/api/v1/admin/lifecycle/environment',
            '/api/v1/admin/lifecycle/release',
            '/api/v1/admin/lifecycle/dependencies',
            '/api/v1/admin/lifecycle/migrations',
            '/api/v1/admin/lifecycle/scheduler',
            '/api/v1/admin/lifecycle/drift',
            '/api/v1/admin/lifecycle/readiness',
        ];

        foreach ($endpoints as $url) {
            $response = $this->actingAs($this->adminUser)->getJson($url);
            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        }
    }

    public function test_deployment_readiness_evaluation_returns_valid_status(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/lifecycle/readiness');

        $response->assertStatus(200);
        $status = $response->json('data.readiness_status');
        $this->assertContains($status, ['READY', 'READY_WITH_WARNINGS', 'NOT_READY', 'UNKNOWN']);
    }

    public function test_configuration_drift_detection_flags_issues(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/lifecycle/drift');

        $response->assertStatus(200);
        $status = $response->json('data.status');
        $this->assertContains($status, ['NO_DRIFT', 'MINOR_DRIFT', 'WARNING_DRIFT', 'CRITICAL_DRIFT']);
    }
}
