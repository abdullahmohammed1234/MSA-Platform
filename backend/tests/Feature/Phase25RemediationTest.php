<?php

namespace Tests\Feature;

use App\Models\ApplicationAccess;
use App\Models\OperationalActionExecution;
use App\Models\OperationalAlert;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Operations\OperationalAlertService;
use App\Services\Operations\Remediation\OperationalRemediationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase25RemediationTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $unauthorizedUser;
    private OperationalAlertService $alertService;
    private OperationalRemediationService $remediationService;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $permission = Permission::firstOrCreate(['slug' => 'platform.operations.execute'], ['name' => 'Execute Platform Operations', 'module' => 'platform']);

        $this->adminUser = User::factory()->create();
        $this->adminUser->roles()->attach($adminRole);
        $this->adminUser->permissions()->attach($permission);

        ApplicationAccess::create([
            'user_id' => $this->adminUser->id,
            'application' => 'admin-portal',
            'granted_by' => $this->adminUser->id,
        ]);

        $this->unauthorizedUser = User::factory()->create();
        $this->alertService = app(OperationalAlertService::class);
        $this->remediationService = app(OperationalRemediationService::class);
    }

    public function test_get_actions_for_alert_returns_available_action_handlers(): void
    {
        $alert = $this->alertService->upsertAlert([
            'category' => 'volunteering',
            'severity' => 'medium',
            'title' => 'Volunteer Registration Backlog',
            'description' => '10 pending volunteer applications',
            'source_type' => 'System',
            'source_id' => 'volunteer_pending_backlog_summary',
            'rule_key' => 'volunteer_pending_backlog',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/admin/operations/alerts/{$alert->id}/actions");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('alert_id', $alert->id)
            ->assertJsonPath('actions.0.key', 'volunteers.refresh_pending_backlog')
            ->assertJsonPath('actions.0.precondition.valid', true);
    }

    public function test_unauthorized_user_cannot_execute_remediation_action(): void
    {
        $alert = $this->alertService->upsertAlert([
            'category' => 'volunteering',
            'severity' => 'medium',
            'title' => 'Volunteer Backlog Test',
            'description' => 'Test description',
            'source_type' => 'System',
            'source_id' => 'volunteer_pending_backlog_summary',
            'rule_key' => 'volunteer_pending_backlog',
        ]);

        $response = $this->actingAs($this->unauthorizedUser, 'sanctum')
            ->postJson("/api/v1/admin/operations/alerts/{$alert->id}/actions/volunteers.refresh_pending_backlog");

        $response->assertStatus(403);
    }

    public function test_executing_remediation_action_records_execution_snapshots_and_audit_logs(): void
    {
        $alert = $this->alertService->upsertAlert([
            'category' => 'volunteering',
            'severity' => 'medium',
            'title' => 'Volunteer Registration Backlog',
            'description' => '10 pending volunteer applications',
            'source_type' => 'System',
            'source_id' => 'volunteer_pending_backlog_summary',
            'rule_key' => 'volunteer_pending_backlog',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/operations/alerts/{$alert->id}/actions/volunteers.refresh_pending_backlog");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('execution.status', 'completed')
            ->assertJsonPath('execution.action_key', 'volunteers.refresh_pending_backlog');

        $this->assertDatabaseHas('operational_action_executions', [
            'operational_alert_id' => $alert->id,
            'action_key' => 'volunteers.refresh_pending_backlog',
            'status' => 'completed',
            'requested_by' => $this->adminUser->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'application' => 'admin-portal',
            'action' => 'operational_remediation_execute',
            'user_id' => $this->adminUser->id,
        ]);
    }

    public function test_executing_action_with_failed_precondition_returns_422(): void
    {
        // Donation refund alert pointing to non-existent refund ID 99999
        $alert = $this->alertService->upsertAlert([
            'category' => 'donations',
            'severity' => 'medium',
            'title' => 'Unreconciled Refund',
            'description' => 'Donation refund #99999 unreconciled',
            'source_type' => 'Refund',
            'source_id' => '99999',
            'rule_key' => 'donation_refund_unreconciled',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/operations/alerts/{$alert->id}/actions/donations.recheck_refund_status");

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('execution.status', 'failed')
            ->assertJsonPath('execution.error_code', 'PRECONDITION_FAILED');

        $this->assertDatabaseHas('operational_action_executions', [
            'operational_alert_id' => $alert->id,
            'action_key' => 'donations.recheck_refund_status',
            'status' => 'failed',
            'error_code' => 'PRECONDITION_FAILED',
        ]);
    }

    public function test_index_and_show_executions_audit_log_endpoints(): void
    {
        $alert = $this->alertService->upsertAlert([
            'category' => 'volunteering',
            'severity' => 'medium',
            'title' => 'Volunteer Backlog Test',
            'description' => 'Test',
            'source_type' => 'System',
            'source_id' => 'volunteer_pending_backlog_summary',
            'rule_key' => 'volunteer_pending_backlog',
        ]);

        $execution = $this->remediationService->executeAction($alert, 'volunteers.refresh_pending_backlog', $this->adminUser);

        // Index
        $indexRes = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/operations/executions');

        $indexRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('executions.total', 1)
            ->assertJsonPath('executions.data.0.uuid', $execution->uuid);

        // Show detail
        $showRes = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/admin/operations/executions/{$execution->uuid}");

        $showRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('execution.uuid', $execution->uuid);
    }
}
