<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Security\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['slug' => 'super-admin', 'name' => 'Super Admin']);
        
        $permissions = ['platform.view', 'platform.health', 'platform.audit', 'platform.alerts', 'platform.operations'];
        $permIds = [];
        foreach ($permissions as $slug) {
            $perm = Permission::firstOrCreate(['slug' => $slug, 'name' => $slug, 'module' => 'Platform']);
            $permIds[] = $perm->id;
        }
        $adminRole->permissions()->sync($permIds);

        $this->admin = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $this->admin->roles()->sync([$adminRole->id]);
    }

    /** @test */
    public function health_probe_and_intelligence_metrics_respond_under_250ms()
    {
        $this->actingAs($this->admin);

        // Warmup query
        $this->getJson('/api/v1/admin/platform/intelligence/metrics');

        // Measured run
        $startTime = microtime(true);
        $res = $this->getJson('/api/v1/admin/platform/intelligence/metrics');
        $durationMs = (microtime(true) - $startTime) * 1000;

        $res->assertStatus(200);
        $this->assertLessThan(250, $durationMs, "Platform intelligence metrics API took {$durationMs}ms, which exceeds the 250ms benchmark!");
    }

    /** @test */
    public function audit_search_responds_under_300ms_with_populated_records()
    {
        $this->actingAs($this->admin);

        // Seed 100 audit logs across apps and severities
        $apps = ['ems', 'store', 'donations', 'sponsorship', 'mlibms', 'cms', 'dams', 'platform'];
        $severities = ['info', 'warning', 'error', 'critical'];

        for ($i = 0; $i < 100; $i++) {
            AuditLogger::log(
                action: 'test_action_' . ($i % 10),
                description: 'Test description ' . $i,
                payload: ['index' => $i],
                userId: $this->admin->id,
                application: $apps[$i % count($apps)],
                severity: $severities[$i % count($severities)]
            );
        }

        // Measure paginated audit search
        $startTime = microtime(true);
        $res = $this->getJson('/api/v1/admin/platform/audit?application=store&severity=info&page=1');
        $durationMs = (microtime(true) - $startTime) * 1000;

        $res->assertStatus(200);
        $this->assertLessThan(300, $durationMs, "Audit search API took {$durationMs}ms, which exceeds the 300ms benchmark!");
    }
}
