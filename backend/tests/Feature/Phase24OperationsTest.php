<?php

namespace Tests\Feature;

use App\Models\ApplicationAccess;
use App\Models\AuditLog;
use App\Models\OperationalAlert;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Operations\OperationalAlertService;
use App\Services\Operations\OperationalDetectionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase24OperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $normalUser;
    private OperationalAlertService $alertService;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $permission = Permission::firstOrCreate(['slug' => 'platform.operations'], ['name' => 'Manage Platform Operations', 'module' => 'platform']);

        $this->adminUser = User::factory()->create();
        $this->adminUser->roles()->attach($adminRole);
        $this->adminUser->permissions()->attach($permission);

        ApplicationAccess::create([
            'user_id' => $this->adminUser->id,
            'application' => 'admin-portal',
            'granted_by' => $this->adminUser->id,
        ]);

        $this->normalUser = User::factory()->create();
        $this->alertService = app(OperationalAlertService::class);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $response = $this->getJson('/api/v1/admin/operations');
        $response->assertStatus(401);
    }

    public function test_unauthorized_users_receive_403(): void
    {
        $response = $this->actingAs($this->normalUser, 'sanctum')
            ->getJson('/api/v1/admin/operations');

        $response->assertStatus(403);
    }

    public function test_authorized_admin_can_fetch_operations_summary_and_alerts(): void
    {
        // Seed an operational alert
        $alert = $this->alertService->upsertAlert([
            'category' => 'ems',
            'severity' => 'high',
            'title' => 'Test EMS Alert',
            'description' => 'Test event issue',
            'source_type' => 'Registration',
            'source_id' => '101',
            'rule_key' => 'ems_payment_ticket_mismatch',
        ]);

        // Get Summary
        $responseSummary = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/operations/summary');

        $responseSummary->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'summary' => [
                    'total_active',
                    'by_status' => ['open', 'acknowledged', 'resolved', 'dismissed'],
                    'by_severity' => ['critical', 'high', 'medium', 'low'],
                    'by_category',
                ],
            ]);

        // Get Index
        $responseIndex = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/operations');

        $responseIndex->assertStatus(200)
            ->assertJsonPath('alerts.total', 1)
            ->assertJsonPath('alerts.data.0.rule_key', 'ems_payment_ticket_mismatch');
    }

    public function test_operational_detection_engine_runs_and_deduplicates_fingerprints(): void
    {
        $engine = app(OperationalDetectionEngine::class);
        $report = $engine->runAll();

        $this->assertIsArray($report);
        $this->assertArrayHasKey('total_detected', $report);
        $this->assertArrayHasKey('details', $report);

        // Re-running detection engine should update timestamps without duplicating rows
        $firstCount = OperationalAlert::count();
        $engine->runAll();
        $secondCount = OperationalAlert::count();

        $this->assertEquals($firstCount, $secondCount, 'Detection engine should atomically deduplicate existing operational alerts by fingerprint.');
    }

    public function test_fingerprint_unambiguous_delimiter_prevents_collisions(): void
    {
        $hash1 = OperationalAlert::computeFingerprint('ems', 'Registration', '12', 'ruleA');
        $hash2 = OperationalAlert::computeFingerprint('emsR', 'egistration', '12', 'ruleA');

        $this->assertNotEquals($hash1, $hash2, 'Delimiter-separated fingerprint calculation must prevent string boundary collisions.');
    }

    public function test_operational_alert_lifecycle_state_transitions_and_audit_logging(): void
    {
        $alert = $this->alertService->upsertAlert([
            'category' => 'store',
            'severity' => 'medium',
            'title' => 'Low Stock Warning',
            'description' => 'Product inventory low',
            'source_type' => 'StoreProduct',
            'source_id' => '55',
            'rule_key' => 'store_inventory_low',
        ]);

        $this->assertEquals('open', $alert->status);

        // Acknowledge
        $ackRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/operations/alerts/{$alert->id}/acknowledge");
        $ackRes->assertStatus(200)
            ->assertJsonPath('alert.status', 'acknowledged');

        $this->assertDatabaseHas('operational_alerts', [
            'id' => $alert->id,
            'status' => 'acknowledged',
            'acknowledged_by' => $this->adminUser->id,
        ]);

        // Resolve with note
        $resolveRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/operations/alerts/{$alert->id}/resolve", [
                'reason' => 'Inventory restocked by supplier',
            ]);
        $resolveRes->assertStatus(200)
            ->assertJsonPath('alert.status', 'resolved');

        $this->assertDatabaseHas('operational_alerts', [
            'id' => $alert->id,
            'status' => 'resolved',
            'resolved_by' => $this->adminUser->id,
            'resolution_reason' => 'Inventory restocked by supplier',
        ]);

        // Reopen
        $reopenRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/operations/alerts/{$alert->id}/reopen");
        $reopenRes->assertStatus(200)
            ->assertJsonPath('alert.status', 'open');

        // Audit Log verification
        $this->assertDatabaseHas('audit_logs', [
            'application' => 'admin-portal',
            'action' => 'operational_alert_transition',
            'user_id' => $this->adminUser->id,
        ]);
    }

    public function test_invalid_state_transitions_return_422(): void
    {
        $alert = $this->alertService->upsertAlert([
            'category' => 'mlibms',
            'severity' => 'medium',
            'title' => 'Overdue Book',
            'description' => 'Loan overdue',
            'source_type' => 'Loan',
            'source_id' => '99',
            'rule_key' => 'mlibms_loan_overdue_7d',
        ]);

        // First resolve the alert
        $this->alertService->transitionStatus($alert, 'resolved', $this->adminUser);

        // Attempting to acknowledge a resolved alert directly is invalid (must reopen first)
        $invalidRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/admin/operations/alerts/{$alert->id}/acknowledge");

        $invalidRes->assertStatus(422);
    }

    public function test_artisan_operations_detect_command(): void
    {
        $this->artisan('operations:detect')
            ->assertExitCode(0);
    }
}
