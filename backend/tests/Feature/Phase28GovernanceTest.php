<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\OperationalActionApproval;
use App\Models\OperationalActionExecution;
use App\Models\OperationalAlert;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ApplicationAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase28GovernanceTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $restrictedAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Super Admin
        $this->superAdmin = User::factory()->create([
            'email' => 'supergovernance@sfumsa.ca',
            'name' => 'Super Governance Admin',
        ]);
        $superRole = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $this->superAdmin->roles()->attach($superRole);

        // Create Restricted Admin
        $this->restrictedAdmin = User::factory()->create([
            'email' => 'restrictedgovernance@sfumsa.ca',
            'name' => 'Restricted Governance Admin',
        ]);
        $restrictedRole = Role::firstOrCreate(['slug' => 'manager'], ['name' => 'Manager']);

        $viewPerm = Permission::firstOrCreate(['slug' => 'platform.governance.view'], ['name' => 'View Governance', 'module' => 'platform']);
        $auditPerm = Permission::firstOrCreate(['slug' => 'platform.governance.audit'], ['name' => 'Audit Governance', 'module' => 'platform']);
        $legacyAuditPerm = Permission::firstOrCreate(['slug' => 'platform.audit'], ['name' => 'Platform Audit', 'module' => 'platform']);
        $integrityPerm = Permission::firstOrCreate(['slug' => 'platform.governance.integrity'], ['name' => 'Integrity Governance', 'module' => 'platform']);
        $continuityPerm = Permission::firstOrCreate(['slug' => 'platform.governance.continuity'], ['name' => 'Continuity Governance', 'module' => 'platform']);
        $exportPerm = Permission::firstOrCreate(['slug' => 'platform.governance.export'], ['name' => 'Export Governance', 'module' => 'platform']);
        
        $restrictedRole->permissions()->attach([
            $viewPerm->id,
            $auditPerm->id,
            $legacyAuditPerm->id,
            $integrityPerm->id,
            $continuityPerm->id,
            $exportPerm->id,
        ]);
        $this->restrictedAdmin->roles()->attach($restrictedRole);

        // Grant explicit admin-portal and EMS access only to restricted admin
        app(ApplicationAccessService::class)->grant($this->restrictedAdmin, 'admin-portal', $this->superAdmin);
        app(ApplicationAccessService::class)->grant($this->restrictedAdmin, 'ems', $this->superAdmin);
    }

    public function test_unauthorized_users_cannot_access_governance_endpoints(): void
    {
        $guest = User::factory()->create();

        $response = $this->actingAs($guest)->getJson('/api/v1/admin/governance');
        $response->assertStatus(403);

        $responseAudit = $this->actingAs($guest)->getJson('/api/v1/admin/governance/audit');
        $responseAudit->assertStatus(403);
    }

    public function test_super_admin_can_fetch_governance_overview_payload(): void
    {
        $response = $this->actingAs($this->superAdmin)->getJson('/api/v1/admin/governance?period=7d');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period',
                    'generated_at',
                    'governance_score',
                    'score_level',
                    'score_drivers',
                    'audit_activity' => ['recent_logs_count', 'recent_logs'],
                    'change_accountability',
                    'integrity_status' => ['status', 'scanned_at', 'total_checks', 'passed_count', 'checks'],
                    'continuity_readiness' => ['overall_status', 'probes'],
                    'governance_backlog',
                    'automation_reliability',
                ],
            ]);
    }

    public function test_audit_logs_search_filtering_and_sensitive_payload_redaction(): void
    {
        AuditLog::create([
            'user_id' => $this->superAdmin->id,
            'application' => 'ems',
            'action' => 'update_event_setting',
            'severity' => 'info',
            'description' => 'Updated EMS setting',
            'payload' => [
                'setting_key' => 'max_capacity',
                'password' => 'super_secret_123',
                'api_key' => 'sk_live_abc123',
            ],
        ]);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/v1/admin/governance/audit?application=ems');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);

        $log = $data[0];
        $this->assertEquals('update_event_setting', $log['action']);
        $this->assertEquals('[REDACTED]', $log['payload']['password']);
        $this->assertEquals('[REDACTED]', $log['payload']['api_key']);
        $this->assertEquals('max_capacity', $log['payload']['setting_key']);
    }

    public function test_change_accountability_formats_who_what_when_where_why_result(): void
    {
        AuditLog::create([
            'user_id' => $this->superAdmin->id,
            'application' => 'store',
            'action' => 'refund_order',
            'severity' => 'warning',
            'description' => 'Refunded store order #999',
            'payload' => [
                'reason' => 'Customer cancellation request',
                'result' => 'Refund processed',
            ],
        ]);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/v1/admin/governance');

        $response->assertStatus(200);
        $changes = $response->json('data.change_accountability');
        $this->assertNotEmpty($changes);

        $refundChanges = array_values(array_filter($changes, fn ($c) => $c['what']['action'] === 'refund_order'));
        $this->assertNotEmpty($refundChanges);

        $change = $refundChanges[0];
        $this->assertEquals($this->superAdmin->name, $change['who']['name']);
        $this->assertEquals('refund_order', $change['what']['action']);
        $this->assertEquals('store', $change['where']['domain']);
        $this->assertEquals('Customer cancellation request', $change['why']);
        $this->assertEquals('Refund processed', $change['result']);
    }

    public function test_change_accountability_uses_reason_not_recorded_fallback(): void
    {
        AuditLog::create([
            'user_id' => $this->superAdmin->id,
            'application' => 'security',
            'action' => 'flush_failed_jobs',
            'severity' => 'critical',
            'description' => 'Flushed failed queue jobs',
            'payload' => [],
        ]);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/v1/admin/governance');

        $response->assertStatus(200);
        $changes = $response->json('data.change_accountability');
        $this->assertNotEmpty($changes);

        $change = array_values(array_filter($changes, fn ($c) => $c['what']['action'] === 'flush_failed_jobs'))[0];
        $this->assertEquals('Reason not recorded', $change['why']);
    }

    public function test_incident_timeline_reconstruction(): void
    {
        $alert = OperationalAlert::create([
            'category' => 'Ems',
            'severity' => 'critical',
            'status' => 'acknowledged',
            'title' => 'Critical Ticket Mismatch',
            'description' => 'Missing ticket for paid registration',
            'source_type' => 'App\\Ems\\Models\\Registration',
            'source_id' => 999,
            'rule_key' => 'ems.missing_ticket',
            'acknowledged_at' => now()->subMinutes(10),
            'acknowledged_by' => $this->superAdmin->id,
        ]);

        $approval = OperationalActionApproval::create([
            'operational_alert_id' => $alert->id,
            'action_key' => 'ems.issue_missing_ticket',
            'status' => 'approved',
            'requested_by' => $this->superAdmin->id,
            'approved_by' => $this->superAdmin->id,
            'request_reason' => 'Fix missing ticket',
            'decision_reason' => 'Verified customer payment',
            'decided_at' => now()->subMinutes(5),
        ]);

        $execution = OperationalActionExecution::create([
            'operational_alert_id' => $alert->id,
            'approval_id' => $approval->id,
            'action_key' => 'ems.issue_missing_ticket',
            'status' => 'completed',
            'requested_by' => $this->superAdmin->id,
            'executed_by' => $this->superAdmin->id,
            'started_at' => now()->subMinutes(4),
            'completed_at' => now()->subMinutes(3),
            'result_summary' => 'Issued ticket #T-100',
        ]);

        $response = $this->actingAs($this->superAdmin)->getJson("/api/v1/admin/governance/incidents/{$alert->uuid}/timeline");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'alert',
                    'nodes_count',
                    'nodes' => [
                        '*' => [
                            'id',
                            'timestamp',
                            'event_type',
                            'severity',
                            'title',
                            'summary',
                            'actor',
                            'relationship_label',
                        ],
                    ],
                ],
            ]);

        $nodes = $response->json('data.nodes');
        $this->assertGreaterThanOrEqual(4, count($nodes));
    }

    public function test_integrity_check_engine_runs_diagnostic_checks(): void
    {
        $response = $this->actingAs($this->superAdmin)->getJson('/api/v1/admin/governance/integrity');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'status',
                    'scanned_at',
                    'total_checks',
                    'passed_count',
                    'warning_count',
                    'failed_count',
                    'checks' => [
                        '*' => ['domain', 'check_key', 'status', 'issue_count', 'summary'],
                    ],
                ],
            ]);

        $checks = $response->json('data.checks');
        $domains = array_unique(array_column($checks, 'domain'));
        $this->assertContains('EMS', $domains);
        $this->assertContains('Donations', $domains);
        $this->assertContains('Store', $domains);
        $this->assertContains('MLibMS', $domains);
        $this->assertContains('Operations', $domains);
    }

    public function test_continuity_readiness_truthfully_reports_not_verified_backup_status(): void
    {
        $response = $this->actingAs($this->superAdmin)->getJson('/api/v1/admin/governance/continuity');

        $response->assertStatus(200)
            ->assertJsonPath('data.probes.backup_verification.status', 'NOT_VERIFIED')
            ->assertJsonPath('data.probes.backup_verification.verification_state', 'UNKNOWN');
    }

    public function test_deterministic_governance_score_deducts_points_for_not_verified_backup_and_alerts(): void
    {
        OperationalAlert::create([
            'category' => 'Security',
            'severity' => 'critical',
            'status' => 'open',
            'title' => 'Critical Security Incident',
            'description' => 'Test critical incident',
            'source_type' => 'App\\Models\\User',
            'source_id' => 1,
            'rule_key' => 'security.critical_test',
        ]);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/v1/admin/governance');

        $response->assertStatus(200);
        $score = $response->json('data.governance_score');
        $drivers = $response->json('data.score_drivers');

        // Base 100 - 15 (1 critical alert) - 10 (NOT_VERIFIED backup) = 75 or lower
        $this->assertLessThanOrEqual(75, $score);
        $driverCategories = array_column($drivers, 'category');
        $this->assertContains('incidents', $driverCategories);
        $this->assertContains('backup_verification', $driverCategories);
    }

    public function test_restricted_admin_respects_application_access_boundaries(): void
    {
        // Store log should be excluded from restricted admin who only has EMS access
        AuditLog::create([
            'user_id' => $this->superAdmin->id,
            'application' => 'store',
            'action' => 'update_product_price',
            'severity' => 'info',
            'description' => 'Updated store product price',
        ]);

        AuditLog::create([
            'user_id' => $this->restrictedAdmin->id,
            'application' => 'ems',
            'action' => 'create_ems_event',
            'severity' => 'info',
            'description' => 'Created EMS event',
        ]);

        $response = $this->actingAs($this->restrictedAdmin)->getJson('/api/v1/admin/governance/audit');

        $response->assertStatus(200);
        $logs = $response->json('data');
        $apps = array_column($logs, 'application');

        $this->assertContains('ems', $apps);
        $this->assertNotContains('store', $apps);
    }

    public function test_governance_report_export_json_and_csv(): void
    {
        // JSON Export
        $jsonResponse = $this->actingAs($this->superAdmin)->getJson('/api/v1/admin/governance/report?period=30d&format=json');
        $jsonResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'report_metadata',
                    'overview',
                ],
            ]);

        // CSV Export
        $csvResponse = $this->actingAs($this->superAdmin)->get('/api/v1/admin/governance/report?period=30d&format=csv');
        $csvResponse->assertStatus(200);
        $csvResponse->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Governance Readiness Score', $csvResponse->streamedContent());
        $this->assertStringContainsString('NOT_VERIFIED', $csvResponse->streamedContent());
    }
}
