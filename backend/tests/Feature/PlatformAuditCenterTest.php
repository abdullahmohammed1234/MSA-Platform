<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Permission;
use App\Models\Role;
use App\Models\AuditLog;
use App\Services\Security\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAuditCenterTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $auditPermission = Permission::firstOrCreate([
            'slug' => 'platform.audit',
            'name' => 'View Audit Logs',
            'module' => 'Platform',
        ]);

        $adminRole = Role::firstOrCreate([
            'slug' => 'admin',
            'name' => 'Admin',
        ]);

        $adminRole->permissions()->sync([$auditPermission->id]);

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
    public function guests_and_unauthorized_users_cannot_access_audit_search()
    {
        $this->getJson('/api/v1/admin/platform/audit')->assertStatus(401);

        $this->actingAs($this->regularUser);
        $this->getJson('/api/v1/admin/platform/audit')->assertStatus(403);
    }

    /** @test */
    public function admin_can_search_audit_logs_with_app_and_severity_filters()
    {
        $this->actingAs($this->admin);

        // Seed test audit logs using AuditLogger
        AuditLogger::log(
            action: 'store_order_placed',
            description: 'Store order #101 placed',
            payload: ['total' => 45.00],
            userId: $this->admin->id,
            application: 'store',
            severity: 'info'
        );

        AuditLogger::log(
            action: 'flush_failed_jobs',
            description: 'Flushed failed jobs',
            payload: ['cleared' => 5],
            userId: $this->admin->id,
            application: 'platform',
            severity: 'critical'
        );

        // 1. Fetch all
        $resAll = $this->getJson('/api/v1/admin/platform/audit')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertCount(2, $resAll->json('data'));

        // 2. Filter by application=store
        $resStore = $this->getJson('/api/v1/admin/platform/audit?application=store')
            ->assertStatus(200);
        $this->assertCount(1, $resStore->json('data'));
        $this->assertEquals('store', $resStore->json('data.0.application'));

        // 3. Filter by severity=critical
        $resCritical = $this->getJson('/api/v1/admin/platform/audit?severity=critical')
            ->assertStatus(200);
        $this->assertCount(1, $resCritical->json('data'));
        $this->assertEquals('critical', $resCritical->json('data.0.severity'));
    }
}
