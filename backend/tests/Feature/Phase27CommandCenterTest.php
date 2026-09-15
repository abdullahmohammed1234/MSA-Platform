<?php

namespace Tests\Feature;

use App\Models\ApplicationAccess;
use App\Models\OperationalActionApproval;
use App\Models\OperationalAlert;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Operations\OperationalAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase27CommandCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $restrictedAdmin;
    private User $unauthorizedUser;
    private OperationalAlertService $alertService;

    protected function setUp(): void
    {
        parent::setUp();

        $superRole = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $opsRole = Role::firstOrCreate(['slug' => 'operations-manager'], ['name' => 'Operations Manager']);

        $viewPerm = Permission::firstOrCreate(['slug' => 'platform.operations'], ['name' => 'Platform Operations', 'module' => 'platform']);

        // Super Admin
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->roles()->attach($superRole);

        ApplicationAccess::create([
            'user_id' => $this->superAdmin->id,
            'application' => 'admin-portal',
            'granted_by' => $this->superAdmin->id,
        ]);

        // Restricted Admin (has platform.operations, but NO explicit donation/store application access)
        $this->restrictedAdmin = User::factory()->create();
        $this->restrictedAdmin->roles()->attach($opsRole);
        $this->restrictedAdmin->permissions()->attach($viewPerm);

        ApplicationAccess::create([
            'user_id' => $this->restrictedAdmin->id,
            'application' => 'admin-portal',
            'granted_by' => $this->restrictedAdmin->id,
        ]);

        // Unauthorized User
        $this->unauthorizedUser = User::factory()->create();

        $this->alertService = app(OperationalAlertService::class);
    }

    public function test_unauthenticated_and_unauthorized_users_cannot_access_command_center(): void
    {
        // Unauthenticated
        $res1 = $this->getJson('/api/v1/admin/command-center');
        $res1->assertStatus(401);

        // Unauthorized
        $res2 = $this->actingAs($this->unauthorizedUser, 'sanctum')
            ->getJson('/api/v1/admin/command-center');
        $res2->assertStatus(403);
    }

    public function test_authorized_admin_receives_aggregated_command_center_telemetry(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/command-center?period=30d');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'generated_at',
                'period',
                'platform' => [
                    'overall_status',
                    'critical_alerts_count',
                    'high_alerts_count',
                    'pending_approvals_count',
                    'blocked_automations_count',
                    'failed_jobs_count',
                    'scheduler_status',
                    'communication_failures_count',
                ],
                'risk' => [
                    'score',
                    'level',
                    'color',
                    'contributing_factors',
                ],
                'attention',
                'infrastructure' => [
                    'database',
                    'storage',
                    'email',
                    'queues',
                    'scheduler',
                    'communications',
                ],
                'applications',
                'business' => [
                    'ems',
                    'donations',
                    'store',
                    'mlibms',
                    'communications',
                    'volunteers',
                    'feedback',
                ],
                'automation' => [
                    'status',
                    'total_executions',
                    'execution_success_rate',
                    'problem_resolution_effectiveness_rate',
                ],
            ]);
    }

    public function test_attention_required_list_prioritizes_critical_and_high_alerts(): void
    {
        // Seed a critical alert and a high alert
        $criticalAlert = $this->alertService->upsertAlert([
            'category' => 'ems',
            'severity' => 'critical',
            'title' => 'Critical Event Surge',
            'description' => 'Server capacity threshold reached during ticket drop',
            'source_type' => 'System',
            'source_id' => 'ems_surge_critical',
            'rule_key' => 'ems_payment_ticket_mismatch',
        ]);

        $highAlert = $this->alertService->upsertAlert([
            'category' => 'donations',
            'severity' => 'high',
            'title' => 'Unreconciled Refund Backlog',
            'description' => 'Square refund status mismatch',
            'source_type' => 'Refund',
            'source_id' => 'refund_123',
            'rule_key' => 'donation_refund_unreconciled',
        ]);

        // Seed a pending approval
        $approval = OperationalActionApproval::create([
            'operational_alert_id' => $criticalAlert->id,
            'action_key' => 'ems.issue_missing_ticket',
            'status' => 'pending',
            'requested_by' => $this->restrictedAdmin->id,
            'request_reason' => 'Ticket missed during surge',
            'requested_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/command-center');

        $response->assertStatus(200);
        $attention = $response->json('attention');

        $this->assertNotEmpty($attention);
        $this->assertEquals('critical', $attention[0]['severity']);
        $this->assertEquals('Critical Event Surge', $attention[0]['title']);
    }

    public function test_application_access_filtering_restricts_domain_details_for_unprivileged_users(): void
    {
        $response = $this->actingAs($this->restrictedAdmin, 'sanctum')
            ->getJson('/api/v1/admin/command-center');

        $response->assertStatus(200);

        $donationsBusiness = $response->json('business.donations');
        $this->assertFalse($donationsBusiness['access_granted']);
        $this->assertEquals('restricted', $donationsBusiness['status']);
    }

    public function test_deterministic_operational_risk_calculation(): void
    {
        // Seed 2 critical alerts (+60 pts) -> Risk level CRITICAL
        for ($i = 0; $i < 2; $i++) {
            $this->alertService->upsertAlert([
                'category' => 'ems',
                'severity' => 'critical',
                'title' => "Critical Test Alert {$i}",
                'description' => 'Test description',
                'source_type' => 'System',
                'source_id' => "crit_test_{$i}",
                'rule_key' => 'ems_payment_ticket_mismatch',
            ]);
        }

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/command-center');

        $response->assertStatus(200)
            ->assertJsonPath('risk.level', 'CRITICAL');

        $this->assertGreaterThanOrEqual(60, $response->json('risk.score'));
        $this->assertNotEmpty($response->json('risk.contributing_factors'));
    }

    public function test_privacy_guarantees_no_sensitive_pii_or_tokens_exposed(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/command-center');

        $content = $response->getContent();

        $this->assertStringNotContainsString('password', strtolower($content));
        $this->assertStringNotContainsString('secret', strtolower($content));
        $this->assertStringNotContainsString('bearer', strtolower($content));
        $this->assertStringNotContainsString('credit_card', strtolower($content));
    }
}
