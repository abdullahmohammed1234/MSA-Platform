<?php

namespace Tests\Feature;

use App\Ems\Enums\EventStatus;
use App\Ems\Models\EventCategory as EmsCategory;
use App\Ems\Models\Event as EmsEvent;
use App\Models\AuditLog;
use App\Models\CMS\Announcement;
use App\Models\DeviceSubscription;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ApplicationAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConnectSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $connectAdmin;
    private User $connectViewer;
    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $configFile = storage_path('app/connect_config.json');
        if (file_exists($configFile)) {
            @unlink($configFile);
        }

        // Admin role
        $adminRole = Role::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin',
            'slug' => 'admin',
        ]);

        $this->admin = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin-connect@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $this->admin->roles()->attach($adminRole);

        // Connect Admin role & permission
        $managePerm = Permission::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Manage Connect System',
            'slug' => 'connect.manage',
            'module' => 'Connect',
            'description' => 'Configure and administer Connect system',
        ]);

        $viewPerm = Permission::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'View Connect Management',
            'slug' => 'connect.view',
            'module' => 'Connect',
            'description' => 'View Connect management',
        ]);

        $connectAdminRole = Role::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Connect Administrator',
            'slug' => 'connect-administrator',
        ]);
        $connectAdminRole->permissions()->attach([$managePerm->id, $viewPerm->id]);

        $this->connectAdmin = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Connect Admin User',
            'email' => 'connect-admin@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $this->connectAdmin->roles()->attach($connectAdminRole);

        // Connect Viewer user
        $viewerRole = Role::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Connect Staff',
            'slug' => 'connect-staff',
        ]);
        $viewerRole->permissions()->attach($viewPerm->id);

        $this->connectViewer = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Connect Viewer User',
            'email' => 'connect-viewer@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $this->connectViewer->roles()->attach($viewerRole);

        // Unauthorized user (member with no admin or connect permissions)
        $this->unauthorizedUser = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Regular Member',
            'email' => 'regular-member@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    /** @test */
    public function unauthenticated_requests_are_rejected_with_401(): void
    {
        $this->getJson('/api/v1/admin/systems/connect')->assertStatus(401);
        $this->getJson('/api/v1/admin/connect/overview')->assertStatus(401);
        $this->getJson('/api/v1/admin/connect/config')->assertStatus(401);
    }

    /** @test */
    public function unauthorized_users_are_forbidden_from_connect_management(): void
    {
        $this->actingAs($this->unauthorizedUser)
            ->getJson('/api/v1/admin/systems/connect')
            ->assertStatus(403);

        $this->actingAs($this->unauthorizedUser)
            ->getJson('/api/v1/admin/connect/overview')
            ->assertStatus(403);

        $this->actingAs($this->unauthorizedUser)
            ->getJson('/api/v1/admin/connect/config')
            ->assertStatus(403);

        $this->actingAs($this->unauthorizedUser)
            ->putJson('/api/v1/admin/connect/config', [])
            ->assertStatus(403);
    }

    /** @test */
    public function system_registry_endpoint_returns_correct_connect_metadata(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/systems/connect')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('system.name', 'MSA Connect Management')
            ->assertJsonPath('system.slug', 'connect')
            ->assertJsonPath('system.admin_path', '/admin/systems/connect');

        $owns = $response->json('system.owns');
        $this->assertContains('connect_configuration', $owns);
        $this->assertContains('device_subscriptions', $owns);

        $doesNotOwn = $response->json('system.does_not_own');
        $this->assertContains('ems_events', $doesNotOwn);
        $this->assertContains('cms_announcements', $doesNotOwn);
    }

    /** @test */
    public function health_diagnostics_returns_database_and_web_push_status(): void
    {
        $response = $this->actingAs($this->connectAdmin)
            ->getJson('/api/v1/admin/connect/health')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $health = $response->json('health');
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('database', $health);
        $this->assertArrayHasKey('web_push', $health);
        $this->assertArrayHasKey('cache', $health);
        $this->assertArrayHasKey('devices_store', $health);
        $this->assertSame('synchronous', $health['web_push']['delivery_mode']);
    }

    /** @test */
    public function metrics_endpoint_returns_real_device_and_inquiry_counts(): void
    {
        // Create sample device subscription
        DeviceSubscription::create([
            'user_id' => $this->connectAdmin->id,
            'endpoint' => 'https://push.example.com/test-endpoint-1',
            'public_key' => 'test-p256dh-sample-key',
            'auth_token' => 'test-auth-sample-token',
            'content_encoding' => 'aes128gcm',
            'user_agent' => 'Test Agent',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->connectAdmin)
            ->getJson('/api/v1/admin/connect/metrics')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $metrics = $response->json('metrics');
        $this->assertEquals(1, $metrics['registered_devices']);
        $this->assertEquals(1, $metrics['active_devices']);
        $this->assertEquals(1, $metrics['unique_device_users']);
    }

    /** @test */
    public function config_get_and_update_validates_and_persists_settings_with_audit_log(): void
    {
        // 1. Get default config
        $response = $this->actingAs($this->connectAdmin)
            ->getJson('/api/v1/admin/connect/config')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $config = $response->json('config');
        $this->assertArrayHasKey('connect_resources_enabled', $config);
        $this->assertArrayHasKey('webpush_enabled', $config);
        $this->assertArrayHasKey('max_devices_per_user', $config);

        // 2. Viewer cannot update config (requires manage permission)
        $this->actingAs($this->connectViewer)
            ->putJson('/api/v1/admin/connect/config', [
                'connect_resources_enabled' => true,
            ])
            ->assertStatus(403);

        // 3. Update with invalid data fails validation with 422
        $this->actingAs($this->connectAdmin)
            ->putJson('/api/v1/admin/connect/config', [
                'connect_resources_enabled' => 'not-a-boolean',
                'max_devices_per_user' => 99999, // exceeds max 50
            ])
            ->assertStatus(422);

        // 4. Update with valid data succeeds
        $validPayload = [
            'connect_resources_enabled' => true,
            'member_directory_enabled' => true,
            'direct_messaging_enabled' => true,
            'events_feed_enabled' => true,
            'announcements_feed_enabled' => true,
            'webpush_enabled' => true,
            'max_devices_per_user' => 8,
            'delivery_timeout_seconds' => 4.5,
            'support_inquiries_enabled' => true,
            'maintenance_mode' => false,
            'maintenance_message' => null,
        ];

        $updateResponse = $this->actingAs($this->connectAdmin)
            ->putJson('/api/v1/admin/connect/config', $validPayload)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('config.max_devices_per_user', 8);

        // 5. Verify audit log entry was created
        $this->assertDatabaseHas('audit_logs', [
            'application' => 'connect',
            'action' => 'connect.config_updated',
            'user_id' => $this->connectAdmin->id,
        ]);
    }

    /** @test */
    public function events_feed_reads_from_ems_without_duplicating_event_ownership(): void
    {
        $response = $this->actingAs($this->connectViewer)
            ->getJson('/api/v1/admin/connect/events')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('authoritative_source', 'EMS (Event Management System)');

        $this->assertStringContainsString('EMS is the sole authoritative owner', $response->json('ownership_notice'));
    }

    /** @test */
    public function announcements_feed_reads_from_cms_without_duplicating_content_ownership(): void
    {
        $response = $this->actingAs($this->connectViewer)
            ->getJson('/api/v1/admin/connect/announcements')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('authoritative_source', 'CMS (Content Management System)');

        $this->assertStringContainsString('CMS is the authoritative owner', $response->json('ownership_notice'));
    }

    /** @test */
    public function audit_endpoint_returns_connect_logs(): void
    {
        AuditLog::create([
            'user_id' => $this->connectAdmin->id,
            'application' => 'connect',
            'action' => 'connect.config_updated',
            'severity' => 'info',
            'description' => 'Test audit entry',
            'payload' => ['updated' => true],
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->actingAs($this->connectViewer)
            ->getJson('/api/v1/admin/connect/audit')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertGreaterThanOrEqual(1, count($response->json('logs.data')));
    }

    /** @test */
    public function root_connect_admin_endpoint_resolves_and_returns_metadata(): void
    {
        $this->actingAs($this->connectViewer)
            ->getJson('/api/v1/admin/connect')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('system.slug', 'connect');
    }

    /** @test */
    public function audit_endpoint_sanitizes_sensitive_payload_credentials_and_tokens(): void
    {
        AuditLog::create([
            'user_id' => $this->connectAdmin->id,
            'application' => 'connect',
            'action' => 'connect.sensitive_test',
            'severity' => 'info',
            'description' => 'Audit log with sensitive keys',
            'payload' => [
                'safe_label' => 'connect-settings',
                'secret_token' => 'super-secret-token-12345',
                'auth_token' => 'my-auth-token',
                'password' => 'secret-password',
                'user_id' => 42,
            ],
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->actingAs($this->connectViewer)
            ->getJson('/api/v1/admin/connect/audit')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $logs = $response->json('logs.data');
        $this->assertNotEmpty($logs);
        $sensitiveLog = collect($logs)->firstWhere('action', 'connect.sensitive_test');
        $this->assertNotNull($sensitiveLog);
        $this->assertSame('connect-settings', $sensitiveLog['payload']['safe_label']);
        $this->assertSame(42, $sensitiveLog['payload']['user_id']);
        $this->assertSame('[REDACTED]', $sensitiveLog['payload']['secret_token']);
        $this->assertSame('[REDACTED]', $sensitiveLog['payload']['auth_token']);
        $this->assertSame('[REDACTED]', $sensitiveLog['payload']['password']);
    }

    /** @test */
    public function feeds_can_be_dynamically_gated_via_configuration(): void
    {
        // 1. Disable events and announcements feeds
        $this->actingAs($this->connectAdmin)
            ->putJson('/api/v1/admin/connect/config', [
                'connect_resources_enabled' => true,
                'member_directory_enabled' => true,
                'direct_messaging_enabled' => true,
                'events_feed_enabled' => false,
                'announcements_feed_enabled' => false,
                'webpush_enabled' => true,
                'max_devices_per_user' => 10,
                'delivery_timeout_seconds' => 5.0,
                'support_inquiries_enabled' => true,
                'maintenance_mode' => false,
                'maintenance_message' => null,
            ])
            ->assertStatus(200);

        // 2. Events feed reports feed_enabled: false
        $this->actingAs($this->connectViewer)
            ->getJson('/api/v1/admin/connect/events')
            ->assertStatus(200)
            ->assertJsonPath('feed_enabled', false)
            ->assertJsonPath('events.data', []);

        // 3. Announcements feed reports feed_enabled: false
        $this->actingAs($this->connectViewer)
            ->getJson('/api/v1/admin/connect/announcements')
            ->assertStatus(200)
            ->assertJsonPath('feed_enabled', false)
            ->assertJsonPath('announcements.data', []);
    }

    /** @test */
    public function webpush_master_enablement_and_max_devices_gate_runtime(): void
    {
        config(['webpush.vapid.public_key' => 'BPjS_mock_public_key_for_testing_purposes_only12345']);

        // 1. Master push disabled
        $this->actingAs($this->connectAdmin)
            ->putJson('/api/v1/admin/connect/config', [
                'connect_resources_enabled' => false,
                'member_directory_enabled' => true,
                'direct_messaging_enabled' => true,
                'events_feed_enabled' => true,
                'announcements_feed_enabled' => true,
                'webpush_enabled' => false,
                'max_devices_per_user' => 2,
                'delivery_timeout_seconds' => 5.0,
                'support_inquiries_enabled' => true,
                'maintenance_mode' => false,
                'maintenance_message' => null,
            ])
            ->assertStatus(200);

        // Public push config returns enabled: false
        $this->getJson('/api/v1/push/config')
            ->assertStatus(200)
            ->assertJsonPath('enabled', false);

        // Attempting to register device is rejected with 503
        $this->actingAs($this->connectAdmin)
            ->postJson('/api/v1/push/subscriptions', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-abc',
                'keys' => [
                    'p256dh' => 'BDs8Z1c_mockKey_min_32_characters_long_for_test1234567890',
                    'auth' => 'mockAuthKey_min12_chars',
                ],
            ])
            ->assertStatus(503);

        // 2. Re-enable push with max_devices_per_user = 2
        $this->actingAs($this->connectAdmin)
            ->putJson('/api/v1/admin/connect/config', [
                'connect_resources_enabled' => false,
                'member_directory_enabled' => true,
                'direct_messaging_enabled' => true,
                'events_feed_enabled' => true,
                'announcements_feed_enabled' => true,
                'webpush_enabled' => true,
                'max_devices_per_user' => 2,
                'delivery_timeout_seconds' => 5.0,
                'support_inquiries_enabled' => true,
                'maintenance_mode' => false,
                'maintenance_message' => null,
            ])
            ->assertStatus(200);

        $this->getJson('/api/v1/push/config')
            ->assertStatus(200)
            ->assertJsonPath('enabled', true);

        // Register 3 devices -> oldest should be revoked (active remains <= 2)
        for ($i = 1; $i <= 3; $i++) {
            $this->actingAs($this->connectAdmin)
                ->postJson('/api/v1/push/subscriptions', [
                    'endpoint' => "https://fcm.googleapis.com/fcm/send/test-endpoint-dev-{$i}",
                    'keys' => [
                        'p256dh' => "BDs8Z1c_mockKey_min_32_characters_long_for_test123456789{$i}",
                        'auth' => "mockAuthKey_min12_chars_{$i}",
                    ],
                ])
                ->assertStatus(201);
        }

        $activeDevicesCount = $this->connectAdmin->deviceSubscriptions()->active()->count();
        $this->assertEquals(2, $activeDevicesCount);
    }

    /** @test */
    public function connect_resources_setting_gates_support_resources_endpoint(): void
    {
        // 1. Enable resources
        $this->actingAs($this->connectAdmin)
            ->putJson('/api/v1/admin/connect/config', [
                'connect_resources_enabled' => true,
                'member_directory_enabled' => true,
                'direct_messaging_enabled' => true,
                'events_feed_enabled' => true,
                'announcements_feed_enabled' => true,
                'webpush_enabled' => true,
                'max_devices_per_user' => 10,
                'delivery_timeout_seconds' => 5.0,
                'support_inquiries_enabled' => true,
                'maintenance_mode' => false,
                'maintenance_message' => null,
            ])
            ->assertStatus(200);

        $this->getJson('/api/v1/support/resources/status')
            ->assertStatus(200)
            ->assertJsonPath('enabled', true);

        // 2. Disable resources
        $this->actingAs($this->connectAdmin)
            ->putJson('/api/v1/admin/connect/config', [
                'connect_resources_enabled' => false,
                'member_directory_enabled' => true,
                'direct_messaging_enabled' => true,
                'events_feed_enabled' => true,
                'announcements_feed_enabled' => true,
                'webpush_enabled' => true,
                'max_devices_per_user' => 10,
                'delivery_timeout_seconds' => 5.0,
                'support_inquiries_enabled' => true,
                'maintenance_mode' => false,
                'maintenance_message' => null,
            ])
            ->assertStatus(200);

        $this->getJson('/api/v1/support/resources/status')
            ->assertStatus(200)
            ->assertJsonPath('enabled', false);
    }

    /** @test */
    public function member_facing_public_events_feed_is_gated_by_configuration(): void
    {
        $category = EmsCategory::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Campus Halaqahs',
            'slug' => 'campus-halaqahs',
        ]);

        $event = EmsEvent::factory()->create([
            'name' => 'SFU Connect Member Halaqah',
            'slug' => 'sfu-connect-member-halaqah',
            'category_id' => $category->id,
            'status' => EventStatus::Published,
            'is_public' => true,
            'start_at' => Carbon::tomorrow()->setTime(17, 0),
            'end_at' => Carbon::tomorrow()->setTime(19, 0),
        ]);

        // 1. Initial State / Enabled: Feed delivers published public events
        $response = $this->getJson('/api/v1/ems/public/events');
        $response->assertStatus(200);
        $this->assertTrue(collect($response->json('data'))->contains('slug', 'sfu-connect-member-halaqah'));

        // 2. Disabling the flag prevents delivery to members
        $this->actingAs($this->connectAdmin)
            ->putJson('/api/v1/admin/connect/config', [
                'connect_resources_enabled' => true,
                'member_directory_enabled' => true,
                'direct_messaging_enabled' => true,
                'events_feed_enabled' => false,
                'announcements_feed_enabled' => true,
                'webpush_enabled' => true,
                'max_devices_per_user' => 10,
                'delivery_timeout_seconds' => 5.0,
                'support_inquiries_enabled' => true,
                'maintenance_mode' => false,
                'maintenance_message' => null,
            ])
            ->assertStatus(200);

        $gatedResponse = $this->getJson('/api/v1/ems/public/events');
        $gatedResponse->assertStatus(200)
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.feed_enabled', false);

        // 3. Re-enabling the flag restores delivery to members
        $this->actingAs($this->connectAdmin)
            ->putJson('/api/v1/admin/connect/config', [
                'connect_resources_enabled' => true,
                'member_directory_enabled' => true,
                'direct_messaging_enabled' => true,
                'events_feed_enabled' => true,
                'announcements_feed_enabled' => true,
                'webpush_enabled' => true,
                'max_devices_per_user' => 10,
                'delivery_timeout_seconds' => 5.0,
                'support_inquiries_enabled' => true,
                'maintenance_mode' => false,
                'maintenance_message' => null,
            ])
            ->assertStatus(200);

        $restoredResponse = $this->getJson('/api/v1/ems/public/events');
        $restoredResponse->assertStatus(200);
        $this->assertTrue(collect($restoredResponse->json('data'))->contains('slug', 'sfu-connect-member-halaqah'));
    }

    /** @test */
    public function member_facing_website_announcements_feed_is_gated_by_configuration(): void
    {
        Cache::forget('website_announcements');

        $announcement = Announcement::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Connect Orientation Gathering',
            'slug' => 'connect-orientation-gathering',
            'content' => 'All SFU MSA members are invited to our term orientation gathering.',
            'summary' => 'Orientation',
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        // 1. Initial State / Enabled: Feed delivers published announcements
        $response = $this->getJson('/api/v1/website/announcements');
        $response->assertStatus(200);
        $announcements = $response->json('announcements');
        $this->assertTrue(collect($announcements)->contains('slug', 'connect-orientation-gathering'));

        // 2. Disabling the flag prevents delivery to members
        $this->actingAs($this->connectAdmin)
            ->putJson('/api/v1/admin/connect/config', [
                'connect_resources_enabled' => true,
                'member_directory_enabled' => true,
                'direct_messaging_enabled' => true,
                'events_feed_enabled' => true,
                'announcements_feed_enabled' => false,
                'webpush_enabled' => true,
                'max_devices_per_user' => 10,
                'delivery_timeout_seconds' => 5.0,
                'support_inquiries_enabled' => true,
                'maintenance_mode' => false,
                'maintenance_message' => null,
            ])
            ->assertStatus(200);

        $gatedResponse = $this->getJson('/api/v1/website/announcements');
        $gatedResponse->assertStatus(200)
            ->assertJsonPath('announcements', [])
            ->assertJsonPath('feed_enabled', false);

        // 3. Re-enabling the flag restores delivery to members
        $this->actingAs($this->connectAdmin)
            ->putJson('/api/v1/admin/connect/config', [
                'connect_resources_enabled' => true,
                'member_directory_enabled' => true,
                'direct_messaging_enabled' => true,
                'events_feed_enabled' => true,
                'announcements_feed_enabled' => true,
                'webpush_enabled' => true,
                'max_devices_per_user' => 10,
                'delivery_timeout_seconds' => 5.0,
                'support_inquiries_enabled' => true,
                'maintenance_mode' => false,
                'maintenance_message' => null,
            ])
            ->assertStatus(200);

        $restoredResponse = $this->getJson('/api/v1/website/announcements');
        $restoredResponse->assertStatus(200);
        $restoredAnnouncements = $restoredResponse->json('announcements');
        $this->assertTrue(collect($restoredAnnouncements)->contains('slug', 'connect-orientation-gathering'));
    }

    protected function tearDown(): void
    {
        $configFile = storage_path('app/connect_config.json');
        if (file_exists($configFile)) {
            @unlink($configFile);
        }

        Cache::forget('website_announcements');

        parent::tearDown();
    }
}
