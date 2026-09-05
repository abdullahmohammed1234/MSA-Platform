<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Permission;
use App\Models\Role;
use App\Platform\Models\PlatformAlert;
use App\Platform\Enums\AlertSeverity;
use App\Platform\Enums\AlertStatus;
use App\Services\Platform\PlatformAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAlertsEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $alertsPermission = Permission::firstOrCreate([
            'slug' => 'platform.alerts',
            'name' => 'Manage Platform Alerts',
            'module' => 'Platform',
        ]);

        $adminRole = Role::firstOrCreate([
            'slug' => 'admin',
            'name' => 'Admin',
        ]);

        $adminRole->permissions()->sync([$alertsPermission->id]);

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
    public function guests_cannot_access_platform_alerts()
    {
        $this->getJson('/api/v1/admin/platform/alerts')->assertStatus(401);
    }

    /** @test */
    public function alert_service_deduplicates_active_alerts_within_cooldown()
    {
        $alertService = app(PlatformAlertService::class);

        // Fire alert 1
        $alert1 = $alertService->triggerAlert(
            alertKey: 'test_health_degraded',
            appKey: 'ems',
            severity: AlertSeverity::WARNING,
            title: 'EMS Health Degraded',
            message: 'Response time exceeds threshold',
        );

        $this->assertNotNull($alert1);

        // Fire alert 2 with same key within cooldown period
        $alert2 = $alertService->triggerAlert(
            alertKey: 'test_health_degraded',
            appKey: 'ems',
            severity: AlertSeverity::WARNING,
            title: 'EMS Health Degraded',
            message: 'Response time exceeds threshold',
        );

        // Should return existing alert instance (deduplicated)
        $this->assertEquals($alert1->id, $alert2->id);
        $this->assertEquals(1, PlatformAlert::count());
    }

    /** @test */
    public function admin_can_acknowledge_and_resolve_alerts()
    {
        $this->actingAs($this->admin);

        $alertService = app(PlatformAlertService::class);
        $alert = $alertService->triggerAlert(
            alertKey: 'cron_timeout',
            appKey: 'platform',
            severity: AlertSeverity::CRITICAL,
            title: 'cPanel Cron Stale',
            message: 'No heartbeat recorded in > 10m',
        );

        // 1. Fetch alerts
        $this->getJson('/api/v1/admin/platform/alerts?status=active')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');

        // 2. Acknowledge
        $this->postJson("/api/v1/admin/platform/alerts/{$alert->id}/acknowledge", [
            'notes' => 'Investigating cPanel cron process',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'acknowledged');

        $this->assertDatabaseHas('platform_alerts', [
            'id' => $alert->id,
            'status' => 'acknowledged',
            'acknowledged_by' => $this->admin->id,
        ]);

        // 3. Resolve
        $this->postJson("/api/v1/admin/platform/alerts/{$alert->id}/resolve", [
            'notes' => 'Cron restarted successfully',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'resolved');

        $this->assertDatabaseHas('platform_alerts', [
            'id' => $alert->id,
            'status' => 'resolved',
            'resolved_by' => $this->admin->id,
        ]);
    }
}
