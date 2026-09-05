<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Permission;
use App\Models\Role;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformSecurityAndIdorTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $opsAdmin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $superRole = Role::firstOrCreate(['slug' => 'super-admin', 'name' => 'Super Admin']);
        $opsRole = Role::firstOrCreate(['slug' => 'admin', 'name' => 'Admin']);

        $opsPermission = Permission::firstOrCreate([
            'slug' => 'platform.operations',
            'name' => 'Platform Operations',
            'module' => 'Platform',
        ]);

        $opsRole->permissions()->sync([$opsPermission->id]);

        $this->superAdmin = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $this->superAdmin->roles()->sync([$superRole->id]);

        $this->opsAdmin = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $this->opsAdmin->roles()->sync([$opsRole->id]);

        $this->regularUser = User::factory()->create(['is_active' => true]);
    }

    /** @test */
    public function regular_user_is_forbidden_from_destructive_platform_operations()
    {
        $this->actingAs($this->regularUser);

        $this->postJson('/api/v1/admin/platform/operations/flush-failed', ['confirm' => true])
            ->assertStatus(403);

        $this->postJson('/api/v1/admin/platform/operations/retry-job', ['job_id' => 1])
            ->assertStatus(403);
    }

    /** @test */
    public function flush_failed_jobs_requires_explicit_confirmation_flag()
    {
        $this->actingAs($this->opsAdmin);

        // Missing confirm flag
        $this->postJson('/api/v1/admin/platform/operations/flush-failed', [])
            ->assertStatus(422);

        // Explicit confirm: false
        $this->postJson('/api/v1/admin/platform/operations/flush-failed', ['confirm' => false])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function confirmed_flush_failed_jobs_logs_critical_audit_record()
    {
        $this->actingAs($this->opsAdmin);

        $this->postJson('/api/v1/admin/platform/operations/flush-failed', ['confirm' => true])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'flush_failed_jobs',
            'application' => 'platform',
            'severity' => 'critical',
            'user_id' => $this->opsAdmin->id,
        ]);
    }
}
