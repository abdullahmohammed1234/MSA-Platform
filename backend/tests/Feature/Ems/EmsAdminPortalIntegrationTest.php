<?php

namespace Tests\Feature\Ems;

use App\Ems\Models\Event;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use App\Ems\Models\CheckIn;
use App\Ems\Models\Payment;
use App\Ems\Models\WebhookEvent;
use App\Ems\Support\EmsRoles;
use App\Ems\Support\EmsPermissions;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmsAdminPortalIntegrationTest extends EmsTestCase
{
    protected User $superAdmin;
    protected User $eventAdmin;
    protected User $organizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = $this->emsUser(EmsRoles::SUPER_ADMIN);
        $this->eventAdmin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $this->organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);

        app(\App\Services\ApplicationAccessService::class)->grant($this->eventAdmin, 'admin-portal');
    }

    /** @test */
    public function guests_cannot_access_ems_system_endpoints()
    {
        $this->getJson('/api/v1/admin/systems/ems')->assertStatus(401);
        $this->getJson('/api/v1/admin/systems/ems/health')->assertStatus(401);
        $this->getJson('/api/v1/admin/systems/ems/metrics')->assertStatus(401);
        $this->getJson('/api/v1/admin/systems/ems/logs')->assertStatus(401);
    }

    /** @test */
    public function event_organizers_and_normal_users_are_forbidden()
    {
        $this->actingAsEms($this->organizer);

        $this->getJson('/api/v1/admin/systems/ems')->assertStatus(403);
        $this->getJson('/api/v1/admin/systems/ems/health')->assertStatus(403);
        $this->getJson('/api/v1/admin/systems/ems/metrics')->assertStatus(403);
        $this->getJson('/api/v1/admin/systems/ems/config')->assertStatus(403);
    }

    /** @test */
    public function system_administrators_and_super_admins_can_monitor_ems_system()
    {
        // Event Admin holds 'system.view' permission according to EmsRoles::permissionMatrix()
        $this->actingAsEms($this->eventAdmin);

        $this->getJson('/api/v1/admin/systems/ems')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['system' => ['name', 'status', 'version']]);

        $this->getJson('/api/v1/admin/systems/ems/health')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['health' => ['api', 'database', 'queues', 'email', 'storage', 'cache']]);

        $this->getJson('/api/v1/admin/systems/ems/metrics')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['metrics' => ['total_events', 'registrations', 'tickets_sold', 'check_ins', 'revenue']]);
    }

    /** @test */
    public function metrics_endpoint_returns_correct_statistics()
    {
        $this->actingAsEms($this->superAdmin);

        // Seed some data
        $evt = Event::factory()->create(['status' => 'live']);
        $reg = Registration::factory()->create([
            'event_id' => $evt->id,
            'status' => 'confirmed',
            'amount_due' => 10.00,
        ]);
        $ticket = Ticket::factory()->create([
            'event_id' => $evt->id,
            'status' => 'issued',
        ]);
        CheckIn::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $evt->id,
            'ticket_id' => $ticket->id,
            'registration_id' => $reg->id,
            'checked_in_at' => now(),
            'method' => \App\Ems\Enums\CheckInMethod::Manual,
        ]);
        Payment::create([
            'uuid' => (string) Str::uuid(),
            'registration_id' => $reg->id,
            'amount' => 10.00,
            'currency' => 'CAD',
            'provider' => \App\Ems\Enums\PaymentProvider::Square,
            'status' => \App\Ems\Enums\PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $res = $this->getJson('/api/v1/admin/systems/ems/metrics')
            ->assertStatus(200)
            ->json('metrics');

        $this->assertGreaterThan(0, $res['total_events']);
        $this->assertGreaterThan(0, $res['registrations']);
        $this->assertGreaterThan(0, $res['tickets_sold']);
        $this->assertGreaterThan(0, $res['check_ins']);
        $this->assertGreaterThan(0, $res['revenue']);
    }

    /** @test */
    public function configuration_can_be_retrieved_and_saved_only_by_authorized_users()
    {
        // 1. Event Admin can view config
        $this->actingAsEms($this->eventAdmin);
        $this->getJson('/api/v1/admin/systems/ems/config')
            ->assertStatus(200)
            ->assertJsonStructure(['config' => ['timezone', 'currency', 'ticket_code_prefix']]);

        // 2. Event Admin CANNOT edit/save configuration
        $this->putJson('/api/v1/admin/systems/ems/config', [
            'timezone' => 'America/Vancouver',
            'currency' => 'CAD',
            'ticket_code_prefix' => 'MSA',
            'ticket_code_length' => 10,
            'ticket_qr_enabled' => true,
            'queue_payments' => 'ems-payments',
            'queue_operations' => 'ems-operations',
            'queue_notifications' => 'ems-notifications',
            'email_from_address' => 'events@sfumsa.org',
            'email_from_name' => 'SFU MSA Events',
            'email_max_retries' => 3,
            'reminder_defaults_enabled' => false,
            'analytics_retention_days' => 365,
            'import_chunk_size' => 100,
            'import_sync_threshold' => 50,
        ])->assertStatus(403);

        // 3. Super Admin can save config
        $this->actingAsEms($this->superAdmin);
        $this->putJson('/api/v1/admin/systems/ems/config', [
            'timezone' => 'Asia/Riyadh',
            'currency' => 'SAR',
            'ticket_code_prefix' => 'SFU',
            'ticket_code_length' => 12,
            'ticket_qr_enabled' => false,
            'queue_payments' => 'test-payments',
            'queue_operations' => 'test-operations',
            'queue_notifications' => 'test-notifications',
            'email_from_address' => 'admin@sfumsa.org',
            'email_from_name' => 'SFU MSA Admin',
            'email_max_retries' => 5,
            'reminder_defaults_enabled' => true,
            'analytics_retention_days' => 180,
            'import_chunk_size' => 200,
            'import_sync_threshold' => 100,
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true);

        // Verify config is saved
        $this->getJson('/api/v1/admin/systems/ems/config')
            ->assertStatus(200)
            ->assertJsonPath('config.timezone', 'Asia/Riyadh')
            ->assertJsonPath('config.currency', 'SAR')
            ->assertJsonPath('config.ticket_code_prefix', 'SFU');

        // Cleanup configuration file
        @unlink(storage_path('app/ems_config.json'));
    }

    /** @test */
    public function log_viewer_returns_parsed_entries()
    {
        $this->actingAsEms($this->superAdmin);

        // Write a test log entry
        $logDir = storage_path('logs');
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/ems-' . date('Y-m-d') . '.log';
        file_put_contents($logFile, "[2026-07-26 12:00:00] testing.ERROR: ems.payments.reconciliation_failed {\"payment_uuid\":\"test-payment-123\",\"issues\":[\"Failed test reconcile.\"]}\n");

        $res = $this->getJson('/api/v1/admin/systems/ems/logs')
            ->assertStatus(200)
            ->json('logs');

        $this->assertGreaterThan(0, $res['total']);
        $this->assertEquals('ERROR', $res['data'][0]['severity']);
        $this->assertEquals('payment', $res['data'][0]['type']);

        // Cleanup log entry
        @unlink($logFile);
    }

    /** @test */
    public function obsolete_legacy_cms_routes_do_not_exist()
    {
        $this->actingAsEms($this->superAdmin);

        // The legacy CMS event endpoints must return 404
        $this->getJson('/api/v1/admin/cms/events')->assertStatus(404);
        $this->postJson('/api/v1/admin/cms/events/check-in')->assertStatus(404);
    }
}
