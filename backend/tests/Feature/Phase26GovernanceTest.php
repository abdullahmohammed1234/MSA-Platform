<?php

namespace Tests\Feature;

use App\Models\ApplicationAccess;
use App\Models\OperationalActionApproval;
use App\Models\OperationalActionExecution;
use App\Models\OperationalAlert;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Operations\OperationalAlertService;
use App\Services\Operations\Remediation\OperationalRemediationService;
use App\Services\Operations\Remediation\OperationalGovernanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase26GovernanceTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $approverUser;
    private User $unauthorizedUser;
    private OperationalAlertService $alertService;
    private OperationalRemediationService $remediationService;
    private OperationalGovernanceService $governanceService;

    protected function setUp(): void
    {
        parent::setUp();

        $opsRole = Role::firstOrCreate(['slug' => 'operations-manager'], ['name' => 'Operations Manager']);
        
        $viewPerm = Permission::firstOrCreate(['slug' => 'platform.operations'], ['name' => 'View Platform Operations', 'module' => 'platform']);
        $executePerm = Permission::firstOrCreate(['slug' => 'platform.operations.execute'], ['name' => 'Execute Platform Operations', 'module' => 'platform']);
        $approvePerm = Permission::firstOrCreate(['slug' => 'platform.operations.approve'], ['name' => 'Approve Platform Operations', 'module' => 'platform']);

        // Admin User (Requester/Executor)
        $this->adminUser = User::factory()->create();
        $this->adminUser->roles()->attach($opsRole);
        $this->adminUser->permissions()->attach([$viewPerm->id, $executePerm->id]);

        ApplicationAccess::create([
            'user_id' => $this->adminUser->id,
            'application' => 'admin-portal',
            'granted_by' => $this->adminUser->id,
        ]);

        // Approver User
        $this->approverUser = User::factory()->create();
        $this->approverUser->roles()->attach($opsRole);
        $this->approverUser->permissions()->attach([$viewPerm->id, $approvePerm->id]);

        ApplicationAccess::create([
            'user_id' => $this->approverUser->id,
            'application' => 'admin-portal',
            'granted_by' => $this->approverUser->id,
        ]);

        // Unauthorized User
        $this->unauthorizedUser = User::factory()->create();

        $this->alertService = app(OperationalAlertService::class);
        $this->remediationService = app(OperationalRemediationService::class);
        $this->governanceService = app(OperationalGovernanceService::class);
    }

    public function test_actions_endpoint_includes_governance_and_risk_metadata(): void
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
            ->assertJsonPath('actions.0.key', 'volunteers.refresh_pending_backlog')
            ->assertJsonPath('actions.0.risk_level', 'low')
            ->assertJsonPath('actions.0.governance.allowed', true);
    }

    public function test_cooldown_enforcement_prevents_immediate_reexecution(): void
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

        // First execution succeeds
        $firstRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/operations/alerts/{$alert->id}/actions/volunteers.refresh_pending_backlog");

        $firstRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // Immediate second execution fails due to active cooldown (GOVERNANCE_BLOCKED)
        $secondRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/operations/alerts/{$alert->id}/actions/volunteers.refresh_pending_backlog");

        $secondRes->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('execution.error_code', 'GOVERNANCE_BLOCKED');
    }

    public function test_repeated_failure_threshold_blocks_action(): void
    {
        $alert = $this->alertService->upsertAlert([
            'category' => 'ems',
            'severity' => 'high',
            'title' => 'EMS Ticket Missing',
            'description' => 'Registration #99999 paid but missing ticket',
            'source_type' => 'Registration',
            'source_id' => '99999',
            'rule_key' => 'ems_payment_ticket_mismatch',
        ]);

        // Create 3 failed execution records for this alert and action
        for ($i = 0; $i < 3; $i++) {
            OperationalActionExecution::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'operational_alert_id' => $alert->id,
                'action_key' => 'ems.issue_missing_ticket',
                'requested_by' => $this->adminUser->id,
                'status' => 'failed',
                'error_code' => 'PRECONDITION_FAILED',
                'execution_notes' => 'Test failure',
                'executed_at' => now()->subMinutes(5),
            ]);
        }

        // Attempting to execute now should trigger repeated failure governance block
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/operations/alerts/{$alert->id}/actions/ems.issue_missing_ticket");

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('execution.error_code', 'GOVERNANCE_BLOCKED');
    }

    public function test_elevated_approval_flow_and_separation_of_duties(): void
    {
        $alert = $this->alertService->upsertAlert([
            'category' => 'ems',
            'severity' => 'high',
            'title' => 'EMS Ticket Missing',
            'description' => 'Registration #99999 paid but missing ticket',
            'source_type' => 'Registration',
            'source_id' => '99999',
            'rule_key' => 'ems_payment_ticket_mismatch',
        ]);

        // Step 1: Request approval
        $reqRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/operations/alerts/{$alert->id}/actions/ems.issue_missing_ticket/request-approval", [
                'request_reason' => 'Ticket missed during network surge',
            ]);

        $reqRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('approval.status', 'pending');

        $approvalUuid = $reqRes->json('approval.uuid');
        $approvalId = $reqRes->json('approval.id');

        // Step 2: Requester attempts to self-approve -> Fails Separation of Duties
        $selfApproveRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/operations/approvals/{$approvalUuid}/approve", [
                'decision_reason' => 'Self approving my request',
            ]);

        $selfApproveRes->assertStatus(422)
            ->assertJsonPath('success', false);

        // Step 3: Authorized distinct approver approves request -> Succeeds
        $approveRes = $this->actingAs($this->approverUser, 'sanctum')
            ->postJson("/api/v1/admin/operations/approvals/{$approvalUuid}/approve", [
                'decision_reason' => 'Verified payment in gateway logs',
            ]);

        $approveRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('approval.status', 'approved');

        // Step 4: Execute action with approved approval_id
        $execRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/operations/alerts/{$alert->id}/actions/ems.issue_missing_ticket", [
                'approval_id' => $approvalId,
            ]);

        // Execution fails at precondition (invalid reg ID 99999), but governance check passes!
        $execRes->assertStatus(422)
            ->assertJsonPath('execution.error_code', 'PRECONDITION_FAILED');

        $this->assertDatabaseHas('operational_action_executions', [
            'operational_alert_id' => $alert->id,
            'approval_id' => $approvalId,
        ]);
    }

    public function test_approval_rejection_flow(): void
    {
        $alert = $this->alertService->upsertAlert([
            'category' => 'ems',
            'severity' => 'high',
            'title' => 'EMS Ticket Missing',
            'description' => 'Registration #99999 paid but missing ticket',
            'source_type' => 'Registration',
            'source_id' => '99999',
            'rule_key' => 'ems_payment_ticket_mismatch',
        ]);

        $reqRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/operations/alerts/{$alert->id}/actions/ems.issue_missing_ticket/request-approval", [
                'request_reason' => 'Need ticket reissue',
            ]);

        $approvalUuid = $reqRes->json('approval.uuid');
        $approvalId = $reqRes->json('approval.id');

        $rejectRes = $this->actingAs($this->approverUser, 'sanctum')
            ->postJson("/api/v1/admin/operations/approvals/{$approvalUuid}/reject", [
                'decision_reason' => 'Payment was refunded, ticket issue rejected.',
            ]);

        $rejectRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('approval.status', 'rejected');

        // Attempt to execute with rejected approval_id fails
        $execRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/operations/alerts/{$alert->id}/actions/ems.issue_missing_ticket", [
                'approval_id' => $approvalId,
            ]);

        $execRes->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('execution.error_code', 'GOVERNANCE_BLOCKED');
    }

    public function test_governance_health_metrics_endpoint(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/operations/governance/health');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'metrics' => [
                    'total_executions',
                    'completed_executions',
                    'failed_executions',
                    'execution_success_rate',
                    'problem_resolution_effectiveness_rate',
                    'pending_approvals_count',
                    'blocked_executions_count',
                    'action_breakdown',
                ]
            ]);
    }

    public function test_unauthorized_user_cannot_approve_or_reject(): void
    {
        $alert = $this->alertService->upsertAlert([
            'category' => 'ems',
            'severity' => 'high',
            'title' => 'EMS Ticket Missing',
            'description' => 'Test',
            'source_type' => 'Registration',
            'source_id' => '99999',
            'rule_key' => 'ems_payment_ticket_mismatch',
        ]);

        $reqRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/operations/alerts/{$alert->id}/actions/ems.issue_missing_ticket/request-approval", [
                'request_reason' => 'Test reason',
            ]);

        $approvalUuid = $reqRes->json('approval.uuid');

        $approveRes = $this->actingAs($this->unauthorizedUser, 'sanctum')
            ->postJson("/api/v1/admin/operations/approvals/{$approvalUuid}/approve", [
                'decision_reason' => 'Unauthorized attempt',
            ]);

        $approveRes->assertStatus(403);
    }
}
