<?php

namespace Tests\Feature\Push;

use App\Models\Conversation;
use App\Models\DeviceSubscription;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\SupportInquiry;
use App\Models\SupportMessage;
use App\Models\User;
use App\Notifications\DirectMessageNotification;
use App\Notifications\SupportReplyNotification;
use App\Services\Notifications\WebPushChannel;
use App\Services\WebPush\PushEndpointValidator;
use App\Services\WebPush\WebPushPayloadFormatter;
use App\Services\WebPush\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebPushTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        WebPushService::fake();
    }

    protected function tearDown(): void
    {
        PushEndpointValidator::resetDnsResolver();
        WebPushService::stopFaking();
        parent::tearDown();
    }

    /**
     * Helper to create an active verified user.
     */
    protected function createUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'is_active' => true,
            'email_verified_at' => now(),
        ], $attributes));
    }

    /**
     * Helper to create a device subscription fixture.
     */
    protected function createDevice(User $user, array $attributes = []): DeviceSubscription
    {
        $endpoint = $attributes['endpoint'] ?? ('https://fcm.googleapis.com/fcm/send/' . Str::random(40));
        return DeviceSubscription::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => hash('sha256', $endpoint),
            'public_key' => 'BPjO3L0X_test_p256dh_key_' . Str::random(20),
            'auth_token' => 'auth_token_' . Str::random(16),
            'content_encoding' => 'aes128gcm',
            'device_name' => 'Chrome on Windows',
            'device_type' => 'desktop',
            'is_active' => true,
        ], $attributes));
    }

    protected function createSupportMessage(SupportInquiry $inquiry, User $user, string $text = 'Staff response.'): SupportMessage
    {
        return SupportMessage::create([
            'uuid' => (string) Str::uuid(),
            'inquiry_id' => $inquiry->id,
            'user_id' => $user->id,
            'is_staff_reply' => true,
            'message' => $text,
        ]);
    }

    /**
     * Test 1: Config endpoint returns capability and public key.
     */
    public function test_push_config_endpoint_returns_capabilities(): void
    {
        $user = $this->createUser();

        config(['webpush.vapid.public_key' => 'TEST_PUBLIC_VAPID_KEY_123']);

        $response = $this->actingAs($user)->getJson('/api/v1/push/config');

        $response->assertStatus(200)
            ->assertJson([
                'push_supported' => true,
                'public_key' => 'TEST_PUBLIC_VAPID_KEY_123',
            ]);
    }

    /**
     * Test 2: Valid subscription registration creates record and returns sanitized metadata.
     */
    public function test_device_subscription_registration_and_validation(): void
    {
        $user = $this->createUser();

        $payload = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/sample-token-123',
            'keys' => [
                'p256dh' => 'BDqG3w_sample_p256dh_key_with_proper_length_32_chars',
                'auth' => 'sample_auth_token_secret',
            ],
            'content_encoding' => 'aes128gcm',
            'device_name' => 'Firefox on macOS',
            'device_type' => 'desktop',
        ];

        $response = $this->actingAs($user)->postJson('/api/v1/push/subscriptions', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'device' => [
                    'device_name' => 'Firefox on macOS',
                    'device_type' => 'desktop',
                    'is_active' => true,
                ],
            ]);

        // Assert record exists in database with cryptographic hash
        $this->assertDatabaseHas('device_subscriptions', [
            'user_id' => $user->id,
            'endpoint_hash' => hash('sha256', 'https://fcm.googleapis.com/fcm/send/sample-token-123'),
            'device_name' => 'Firefox on macOS',
            'is_active' => 1,
        ]);

        // Assert sensitive secrets are NOT exposed in API response
        $data = $response->json('device');
        $this->assertArrayNotHasKey('endpoint', $data);
        $this->assertArrayNotHasKey('public_key', $data);
        $this->assertArrayNotHasKey('auth_token', $data);
    }

    /**
     * Test 3: Disallowed/SSRF endpoints (non-HTTPS, localhost, private IP) are rejected.
     */
    public function test_disallowed_and_ssrf_endpoints_are_strictly_rejected(): void
    {
        $user = $this->createUser();

        $invalidEndpoints = [
            'http://fcm.googleapis.com/insecure',
            'https://localhost/push',
            'https://127.0.0.1/push',
            'https://10.0.0.1/push',
            'https://192.168.1.1/push',
            'https://169.254.169.254/latest/meta-data',
            'https://internal.lan/service',
        ];

        foreach ($invalidEndpoints as $badUrl) {
            $response = $this->actingAs($user)->postJson('/api/v1/push/subscriptions', [
                'endpoint' => $badUrl,
                'keys' => [
                    'p256dh' => 'BDqG3w_sample_p256dh_key_with_proper_length_32_chars',
                    'auth' => 'sample_auth_token_secret',
                ],
            ]);

            $response->assertStatus(422);
        }
    }

    /**
     * Test 4: Multiple subscriptions belonging to the same user can be registered.
     */
    public function test_user_can_register_multiple_devices(): void
    {
        $user = $this->createUser();

        $deviceA = $this->createDevice($user, ['device_name' => 'Mobile Safari']);
        $deviceB = $this->createDevice($user, ['device_name' => 'Work Desktop']);

        $response = $this->actingAs($user)->getJson('/api/v1/push/subscriptions');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'devices');

        $deviceNames = collect($response->json('devices'))->pluck('device_name')->all();
        $this->assertContains('Mobile Safari', $deviceNames);
        $this->assertContains('Work Desktop', $deviceNames);
    }

    /**
     * Test 5: Re-registering an existing endpoint updates keys and re-activates subscription.
     */
    public function test_re_registering_existing_endpoint_updates_and_activates(): void
    {
        $user = $this->createUser();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/shared-endpoint';

        // Pre-existing revoked subscription
        $existing = $this->createDevice($user, [
            'endpoint' => $endpoint,
            'is_active' => false,
            'revoked_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/push/subscriptions', [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'UPDATED_P256DH_KEY_WITH_PROPER_LENGTH_32_CHARS',
                'auth' => 'UPDATED_AUTH_TOKEN_SECRET',
            ],
            'device_name' => 'Updated Browser',
        ]);

        $response->assertStatus(201);

        $existing->refresh();
        $this->assertTrue($existing->is_active);
        $this->assertNull($existing->revoked_at);
        $this->assertEquals('UPDATED_P256DH_KEY_WITH_PROPER_LENGTH_32_CHARS', $existing->public_key);
        $this->assertEquals('Updated Browser', $existing->device_name);
    }

    /**
     * Test 6: Cross-user subscription access, listing, and revocation is forbidden (IDOR protection).
     */
    public function test_cross_user_device_access_and_revocation_is_denied(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();

        $deviceA = $this->createDevice($userA, ['device_name' => 'User A Laptop']);

        // User B cannot see User A's device
        $listResponse = $this->actingAs($userB)->getJson('/api/v1/push/subscriptions');
        $listResponse->assertStatus(200)->assertJsonCount(0, 'devices');

        // User B cannot revoke User A's device
        $revokeResponse = $this->actingAs($userB)->deleteJson("/api/v1/push/subscriptions/{$deviceA->uuid}");
        $revokeResponse->assertStatus(404);

        // Device A remains active
        $this->assertTrue($deviceA->fresh()->is_active);
    }

    /**
     * Test 7: User can revoke an individual device.
     */
    public function test_user_can_revoke_individual_device(): void
    {
        $user = $this->createUser();
        $deviceA = $this->createDevice($user);
        $deviceB = $this->createDevice($user);

        $response = $this->actingAs($user)->deleteJson("/api/v1/push/subscriptions/{$deviceA->uuid}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertFalse($deviceA->fresh()->is_active);
        $this->assertNotNull($deviceA->fresh()->revoked_at);
        $this->assertTrue($deviceB->fresh()->is_active);
    }

    /**
     * Test 8: User can revoke all active devices in bulk.
     */
    public function test_user_can_revoke_all_devices(): void
    {
        $user = $this->createUser();
        $deviceA = $this->createDevice($user);
        $deviceB = $this->createDevice($user);

        $response = $this->actingAs($user)->deleteJson('/api/v1/push/subscriptions');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'revoked_count' => 2,
            ]);

        $this->assertFalse($deviceA->fresh()->is_active);
        $this->assertFalse($deviceB->fresh()->is_active);
    }

    /**
     * Test 9: Push preference retrieval and toggling.
     */
    public function test_push_preference_retrieval_and_update(): void
    {
        $user = $this->createUser();

        // Initial default: push_enabled is true
        $getRes = $this->actingAs($user)->getJson('/api/v1/notifications/preferences');
        $getRes->assertStatus(200)
            ->assertJson(['push_enabled' => true]);

        // Toggle push_enabled to false
        $updateRes = $this->actingAs($user)->putJson('/api/v1/notifications/preferences', [
            'push_enabled' => false,
        ]);

        $updateRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'preferences' => ['push_enabled' => false],
            ]);

        $this->assertFalse($user->fresh()->notificationPreferences->push_enabled);
    }

    /**
     * Test 10: Global push opt-out suppresses push delivery attempts.
     */
    public function test_global_push_opt_out_suppresses_delivery(): void
    {
        $user = $this->createUser();
        $this->createDevice($user);

        $user->notificationPreferences()->update([
            'push_enabled' => false,
        ]);

        $inquiry = SupportInquiry::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'subject' => 'General Question',
            'category' => 'general',
            'status' => 'open',
            'reference_code' => 'INQ-202610-1001',
        ]);
        $message = $this->createSupportMessage($inquiry, $user, 'Staff response message.');

        $notification = new SupportReplyNotification($inquiry, $message);
        $channels = $notification->via($user);

        // WebPushChannel must NOT be in delivery channels when push_enabled is false
        $this->assertNotContains(WebPushChannel::class, $channels);
    }

    /**
     * Test 11: Topic-level opt-out suppresses push delivery attempts.
     */
    public function test_topic_level_opt_out_suppresses_push_delivery(): void
    {
        $user = $this->createUser();
        $this->createDevice($user);

        // Opt out of support_updates topic specifically
        $user->notificationPreferences()->update([
            'support_updates' => false,
            'push_enabled' => true,
        ]);

        $inquiry = SupportInquiry::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'subject' => 'General Question',
            'category' => 'general',
            'status' => 'open',
            'reference_code' => 'INQ-202610-1002',
        ]);
        $message = $this->createSupportMessage($inquiry, $user, 'Staff response message.');

        $notification = new SupportReplyNotification($inquiry, $message);
        $channels = $notification->via($user);

        // All channels should be empty when user opted out of topic
        $this->assertEmpty($channels);
    }

    /**
     * Test 12: Independent email and push preferences — disabling email does not disable push.
     */
    public function test_disabling_email_does_not_disable_push(): void
    {
        $user = $this->createUser();
        $this->createDevice($user);

        $user->notificationPreferences()->update([
            'email_enabled' => false,
            'push_enabled' => true,
        ]);

        $inquiry = SupportInquiry::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'subject' => 'Question',
            'category' => 'general',
            'status' => 'open',
            'reference_code' => 'INQ-202610-1003',
        ]);
        $message = $this->createSupportMessage($inquiry, $user, 'Response.');

        $notification = new SupportReplyNotification($inquiry, $message);
        $channels = $notification->via($user);

        $this->assertNotContains('mail', $channels);
        $this->assertContains(WebPushChannel::class, $channels);
    }

    /**
     * Test 13: Synchronous post-commit delivery execution.
     */
    public function test_synchronous_delivery_executes_after_transaction_commit(): void
    {
        $user = $this->createUser();
        $device = $this->createDevice($user);

        WebPushService::resetFakeDispatches();

        $inquiry = SupportInquiry::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'subject' => 'Transaction Test',
            'category' => 'general',
            'status' => 'open',
            'reference_code' => 'INQ-202610-1004',
        ]);
        $message = $this->createSupportMessage($inquiry, $user, 'Committed response.');

        $notification = new SupportReplyNotification($inquiry, $message);

        // Execute inside database transaction
        DB::transaction(function () use ($user, $notification) {
            $user->notify($notification);

            // While transaction is open, delivery should be deferred
            $this->assertCount(0, WebPushService::getFakeDispatches());
        });

        // Immediately after transaction commits, delivery must have executed synchronously!
        $dispatches = WebPushService::getFakeDispatches();
        $this->assertCount(1, $dispatches);
        $this->assertEquals($user->id, $dispatches[0]['user_id']);
        $this->assertEquals(1, $dispatches[0]['devices_count']);
    }

    /**
     * Test 14: Transaction rollback suppresses push delivery entirely.
     */
    public function test_transaction_rollback_prevents_push_delivery(): void
    {
        $user = $this->createUser();
        $this->createDevice($user);

        WebPushService::resetFakeDispatches();

        $inquiry = SupportInquiry::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'subject' => 'Rollback Test',
            'category' => 'general',
            'status' => 'open',
            'reference_code' => 'INQ-202610-1005',
        ]);
        $message = $this->createSupportMessage($inquiry, $user, 'Rollback response.');

        $notification = new SupportReplyNotification($inquiry, $message);

        try {
            DB::transaction(function () use ($user, $notification) {
                $user->notify($notification);
                throw new \Exception("Simulated failure inside transaction");
            });
        } catch (\Exception $e) {
            // Expected transaction failure
        }

        // Post-rollback: ZERO push notifications must be delivered!
        $this->assertCount(0, WebPushService::getFakeDispatches());
    }

    /**
     * Test 15: Zero queue dependency — push delivery works synchronously when queue is stopped.
     */
    public function test_delivery_works_without_queue_worker(): void
    {
        $user = $this->createUser();
        $this->createDevice($user);

        WebPushService::resetFakeDispatches();

        $inquiry = SupportInquiry::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'subject' => 'Queue Independence Test',
            'category' => 'general',
            'status' => 'open',
            'reference_code' => 'INQ-202610-1006',
        ]);
        $message = $this->createSupportMessage($inquiry, $user, 'Testing synchronous direct delivery.');

        $notification = new SupportReplyNotification($inquiry, $message);

        // Notify directly outside any worker
        $user->notify($notification);

        // Immediate synchronous delivery
        $this->assertCount(1, WebPushService::getFakeDispatches());
    }

    /**
     * Test 16: Payload privacy for Direct Messaging — generic text, no message body or sender leak.
     */
    public function test_direct_message_push_payload_privacy(): void
    {
        $sender = $this->createUser(['name' => 'Secret Admin']);
        $recipient = $this->createUser(['name' => 'Private Member']);

        $conversation = Conversation::create([
            'uuid' => (string) Str::uuid(),
            'user_one_id' => min($sender->id, $recipient->id),
            'user_two_id' => max($sender->id, $recipient->id),
        ]);

        $notification = new DirectMessageNotification($conversation, $sender);
        $payload = WebPushPayloadFormatter::format($notification, $recipient);

        // Must have generic, privacy-preserving title and body
        $this->assertEquals('New Direct Message', $payload['title']);
        $this->assertEquals('You have received a new private message.', $payload['body']);
        $this->assertEquals("/app/messages/{$conversation->uuid}", $payload['url']);

        // Sensitive details must NEVER appear
        $payloadJson = json_encode($payload);
        $this->assertStringNotContainsString('Secret Admin', $payloadJson);
        $this->assertStringNotContainsString($sender->email, $payloadJson);
    }

    /**
     * Test 17: Payload privacy for Support Reply — inquiry reference included, inquiry body redacted.
     */
    public function test_support_reply_push_payload_privacy(): void
    {
        $user = $this->createUser();

        $inquiry = SupportInquiry::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'subject' => 'Confidential Financial Aid Request',
            'category' => 'financial',
            'status' => 'open',
            'reference_code' => 'INQ-202610-7788',
        ]);
        $message = $this->createSupportMessage($inquiry, $user, 'Private financial decision text.');

        $notification = new SupportReplyNotification($inquiry, $message);
        $payload = WebPushPayloadFormatter::format($notification, $user);

        $this->assertEquals('Support Inquiry Update', $payload['title']);
        $this->assertEquals('An update was posted to inquiry #INQ-202610-7788.', $payload['body']);
        $this->assertEquals('/app/support/INQ-202610-7788', $payload['url']);

        // Private content must NEVER appear
        $payloadJson = json_encode($payload);
        $this->assertStringNotContainsString('Confidential Financial Aid Request', $payloadJson);
        $this->assertStringNotContainsString('Private financial decision text', $payloadJson);
    }

    /**
     * Test 18: Navigation URL allowlist validation defuses open redirects and protocols.
     */
    public function test_navigation_target_url_sanitization(): void
    {
        // Safe internal paths
        $this->assertEquals('/app/messages/abc-123', WebPushPayloadFormatter::sanitizeTargetUrl('/app/messages/abc-123'));
        $this->assertEquals('/app/support/INQ-01', WebPushPayloadFormatter::sanitizeTargetUrl('/app/support/INQ-01'));
        $this->assertEquals('/app/notifications', WebPushPayloadFormatter::sanitizeTargetUrl('/app/notifications'));

        // Dangerous / external evasion URLs must fall back safely to /app/notifications
        $this->assertEquals('/app/notifications', WebPushPayloadFormatter::sanitizeTargetUrl('https://evil.com/phish'));
        $this->assertEquals('/app/notifications', WebPushPayloadFormatter::sanitizeTargetUrl('//evil.com/phish'));
        $this->assertEquals('/app/notifications', WebPushPayloadFormatter::sanitizeTargetUrl('/\\evil.com'));
        $this->assertEquals('/app/notifications', WebPushPayloadFormatter::sanitizeTargetUrl('javascript:alert(1)'));
        $this->assertEquals('/app/notifications', WebPushPayloadFormatter::sanitizeTargetUrl('data:text/html,evil'));
        $this->assertEquals('/app/notifications', WebPushPayloadFormatter::sanitizeTargetUrl('relative/path/without/slash'));
        $this->assertEquals('/app/notifications', WebPushPayloadFormatter::sanitizeTargetUrl(null));
        $this->assertEquals('/app/notifications', WebPushPayloadFormatter::sanitizeTargetUrl(''));
    }

    /**
     * Test 19: Missing VAPID configuration fails closed safely without breaking business flow.
     */
    public function test_missing_vapid_config_fails_closed_without_exceptions(): void
    {
        WebPushService::stopFaking(); // Test real service execution path

        config(['webpush.vapid.public_key' => null]);
        config(['webpush.vapid.private_key' => null]);

        $user = $this->createUser();
        $this->createDevice($user);

        $inquiry = SupportInquiry::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'subject' => 'Unconfigured Test',
            'category' => 'general',
            'status' => 'open',
            'reference_code' => 'INQ-202610-1007',
        ]);
        $message = $this->createSupportMessage($inquiry, $user, 'Testing missing VAPID keys.');

        $notification = new SupportReplyNotification($inquiry, $message);

        // Should complete smoothly without throwing an unhandled exception
        $service = app(WebPushService::class);
        $service->deliver($user, $notification);

        $this->assertTrue(true);
    }

    /**
     * Test 20: Expired subscriptions (HTTP 410 Gone / isSubscriptionExpired) are deactivated.
     */
    public function test_expired_subscription_deactivation_on_410_gone(): void
    {
        WebPushService::stopFaking();

        config([
            'webpush.vapid.public_key' => 'TEST_PUB_KEY',
            'webpush.vapid.private_key' => 'TEST_PRIV_KEY',
        ]);

        $user = $this->createUser();
        $deviceA = $this->createDevice($user, ['endpoint' => 'https://fcm.googleapis.com/fcm/send/expired-token']);
        $deviceB = $this->createDevice($user, ['endpoint' => 'https://fcm.googleapis.com/fcm/send/active-token']);

        // Mock WebPush client and MessageSentReport objects
        $reportA = \Mockery::mock(\Minishlink\WebPush\MessageSentReport::class);
        $reportA->shouldReceive('getEndpoint')->andReturn('https://fcm.googleapis.com/fcm/send/expired-token');
        $reportA->shouldReceive('isSuccess')->andReturn(false);
        $reportA->shouldReceive('isSubscriptionExpired')->andReturn(true);

        $reportB = \Mockery::mock(\Minishlink\WebPush\MessageSentReport::class);
        $reportB->shouldReceive('getEndpoint')->andReturn('https://fcm.googleapis.com/fcm/send/active-token');
        $reportB->shouldReceive('isSuccess')->andReturn(true);

        $generatorA = (function () use ($reportA, $reportB) {
            yield $reportA;
            yield $reportB;
        })();

        $mockClient = \Mockery::mock(\Minishlink\WebPush\WebPush::class);
        $mockClient->shouldReceive('queueNotification')->twice();
        $mockClient->shouldReceive('flush')->once()->andReturn($generatorA);

        $this->app->instance(\Minishlink\WebPush\WebPush::class, $mockClient);

        $inquiry = SupportInquiry::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'subject' => 'Deactivation Test',
            'category' => 'general',
            'status' => 'open',
            'reference_code' => 'INQ-202610-8899',
        ]);
        $message = $this->createSupportMessage($inquiry, $user, 'Testing 410 deactivation.');
        $notification = new SupportReplyNotification($inquiry, $message);

        $service = app(WebPushService::class);
        $service->deliver($user, $notification);

        // Device A (expired) must be revoked
        $this->assertFalse($deviceA->fresh()->is_active);
        $this->assertNotNull($deviceA->fresh()->revoked_at);

        // Device B (active) must remain active with updated last_used_at
        $this->assertTrue($deviceB->fresh()->is_active);
        $this->assertNotNull($deviceB->fresh()->last_used_at);
    }

    /**
     * Test 21: Failure on one device does not prevent delivery to other devices (isolation).
     */
    public function test_transient_error_on_one_device_does_not_block_other_device(): void
    {
        WebPushService::stopFaking();

        config([
            'webpush.vapid.public_key' => 'TEST_PUB_KEY',
            'webpush.vapid.private_key' => 'TEST_PRIV_KEY',
        ]);

        $user = $this->createUser();
        $deviceA = $this->createDevice($user, ['endpoint' => 'https://fcm.googleapis.com/fcm/send/device-a']);
        $deviceB = $this->createDevice($user, ['endpoint' => 'https://fcm.googleapis.com/fcm/send/device-b']);

        $reportA = \Mockery::mock(\Minishlink\WebPush\MessageSentReport::class);
        $reportA->shouldReceive('getEndpoint')->andReturn('https://fcm.googleapis.com/fcm/send/device-a');
        $reportA->shouldReceive('isSuccess')->andReturn(false);
        $reportA->shouldReceive('isSubscriptionExpired')->andReturn(false);
        $reportA->shouldReceive('getReason')->andReturn('HTTP 503 Service Unavailable');

        $reportB = \Mockery::mock(\Minishlink\WebPush\MessageSentReport::class);
        $reportB->shouldReceive('getEndpoint')->andReturn('https://fcm.googleapis.com/fcm/send/device-b');
        $reportB->shouldReceive('isSuccess')->andReturn(true);

        $generatorB = (function () use ($reportA, $reportB) {
            yield $reportA;
            yield $reportB;
        })();

        $mockClient = \Mockery::mock(\Minishlink\WebPush\WebPush::class);
        $mockClient->shouldReceive('queueNotification')->twice();
        $mockClient->shouldReceive('flush')->once()->andReturn($generatorB);

        $this->app->instance(\Minishlink\WebPush\WebPush::class, $mockClient);

        $inquiry = SupportInquiry::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'subject' => 'Isolation Test',
            'category' => 'general',
            'status' => 'open',
            'reference_code' => 'INQ-202610-9900',
        ]);
        $message = $this->createSupportMessage($inquiry, $user, 'Testing device error isolation.');
        $notification = new SupportReplyNotification($inquiry, $message);

        $service = app(WebPushService::class);
        $service->deliver($user, $notification);

        // Device A remains active (transient error, not 410)
        $this->assertTrue($deviceA->fresh()->is_active);
        // Device B was delivered successfully
        $this->assertTrue($deviceB->fresh()->is_active);
        $this->assertNotNull($deviceB->fresh()->last_used_at);
    }

    /**
     * Test 22: Direct message notification delivers to Web Push channel post-commit.
     */
    public function test_direct_message_delivers_to_web_push_channel(): void
    {
        $admin = $this->createUser();
        $member = $this->createUser();
        $this->createDevice($member);

        WebPushService::resetFakeDispatches();

        $conversation = Conversation::create([
            'uuid' => (string) Str::uuid(),
            'user_one_id' => min($admin->id, $member->id),
            'user_two_id' => max($admin->id, $member->id),
        ]);

        $notification = new DirectMessageNotification($conversation, $admin);

        // Post-commit notify
        $member->notify($notification);

        $dispatches = WebPushService::getFakeDispatches();
        $this->assertCount(1, $dispatches);
        $this->assertEquals($member->id, $dispatches[0]['user_id']);
        $this->assertEquals('New Direct Message', $dispatches[0]['payload']['title']);
        $this->assertEquals("/app/messages/{$conversation->uuid}", $dispatches[0]['payload']['url']);
    }

    /**
     * Test 23: Cross-user endpoint registration is strictly forbidden (HTTP 409 Conflict).
     * Prevents subscription hijacking and IDOR ownership transfer.
     */
    public function test_cross_user_endpoint_re_registration_is_strictly_forbidden_409(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();

        $endpoint = 'https://fcm.googleapis.com/fcm/send/token-unique-user-a';

        // User A registers their device
        $deviceA = $this->createDevice($userA, [
            'endpoint' => $endpoint,
            'device_name' => 'User A Phone',
        ]);

        // User B attempts to register User A's endpoint
        $response = $this->actingAs($userB)->postJson('/api/v1/push/subscriptions', [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'USER_B_KEY_ATTEMPT_WITH_MINIMUM_32_CHARS_LONG',
                'auth' => 'USER_B_AUTH_SECRET_VAL',
            ],
            'device_name' => 'User B Attempt',
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'This push subscription endpoint is already registered to another account.',
            ]);

        // Verify User A still owns the subscription and record was untouched
        $deviceA->refresh();
        $this->assertEquals($userA->id, $deviceA->user_id);
        $this->assertEquals('User A Phone', $deviceA->device_name);
        $this->assertTrue($deviceA->is_active);
    }

    /**
     * Test 24: Advanced SSRF and obfuscated numeric IP representations are rejected.
     */
    public function test_advanced_ssrf_and_obfuscated_ips_are_rejected(): void
    {
        $user = $this->createUser();

        $dangerousEndpoints = [
            'https://2130706433/send', // Decimal integer IP for 127.0.0.1
            'https://0x7f000001/send',  // Hexadecimal IP for 127.0.0.1
            'https://0177.0.0.1/send',  // Octal IP for 127.0.0.1
            'https://[::ffff:127.0.0.1]/send', // IPv4-mapped IPv6 loopback
            'https://fcm.googleapis.com:8080/send', // Non-standard port
            'https://fcm.googleapis.com:22/send', // SSH port
            'https://admin:secret@fcm.googleapis.com/send', // Embedded credentials
            'https://metadata.google.internal/computeMetadata/v1', // Internal cloud metadata
            'https://server.home/push', // Home LAN suffix
            'https://corp.local/push', // Local domain
        ];

        foreach ($dangerousEndpoints as $badUrl) {
            $response = $this->actingAs($user)->postJson('/api/v1/push/subscriptions', [
                'endpoint' => $badUrl,
                'keys' => [
                    'p256dh' => 'BDqG3w_sample_p256dh_key_with_proper_length_32_chars',
                    'auth' => 'sample_auth_token_secret',
                ],
            ]);

            $response->assertStatus(422);
        }
    }

    /**
     * Test 25: Subscription keys validation bounds and character constraints.
     */
    public function test_subscription_key_validation_bounds(): void
    {
        $user = $this->createUser();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/token-test-keys-bounds';

        // 1. Short p256dh key (< 32 chars)
        $res = $this->actingAs($user)->postJson('/api/v1/push/subscriptions', [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'short_key',
                'auth' => 'sample_auth_token_secret',
            ],
        ]);
        $res->assertStatus(422)->assertJsonValidationErrors('keys.p256dh');

        // 2. Non-base64url characters in p256dh
        $res = $this->actingAs($user)->postJson('/api/v1/push/subscriptions', [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'BDqG3w*invalid*characters!in#key*string*here*valid_len',
                'auth' => 'sample_auth_token_secret',
            ],
        ]);
        $res->assertStatus(422)->assertJsonValidationErrors('keys.p256dh');

        // 3. Short auth secret (< 12 chars)
        $res = $this->actingAs($user)->postJson('/api/v1/push/subscriptions', [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'BDqG3w_sample_p256dh_key_with_proper_length_32_chars',
                'auth' => 'short',
            ],
        ]);
        $res->assertStatus(422)->assertJsonValidationErrors('keys.auth');
    }

    /**
     * Test 26: Active device limit per user (bounded fan-out) deactivates oldest device FIFO.
     */
    public function test_device_limit_per_user_deactivates_oldest_device_fifo(): void
    {
        $user = $this->createUser();
        config(['webpush.max_devices_per_user' => 3]);

        // Register 3 devices
        $dev1 = $this->createDevice($user, ['device_name' => 'Device 1']);
        $dev2 = $this->createDevice($user, ['device_name' => 'Device 2']);
        $dev3 = $this->createDevice($user, ['device_name' => 'Device 3']);

        $this->assertEquals(3, $user->deviceSubscriptions()->active()->count());

        // Register 4th device via API
        $response = $this->actingAs($user)->postJson('/api/v1/push/subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/token-fourth-device',
            'keys' => [
                'p256dh' => 'BDqG3w_sample_p256dh_key_with_proper_length_32_chars',
                'auth' => 'sample_auth_token_secret',
            ],
            'device_name' => 'Device 4',
        ]);

        $response->assertStatus(201);

        // Active devices count must still be strictly 3 (bounded)
        $this->assertEquals(3, $user->deviceSubscriptions()->active()->count());

        // Device 1 (oldest) must have been deactivated
        $this->assertFalse($dev1->fresh()->is_active);
        $this->assertNotNull($dev1->fresh()->revoked_at);

        // Device 2, 3, 4 remain active
        $this->assertTrue($dev2->fresh()->is_active);
        $this->assertTrue($dev3->fresh()->is_active);
    }

    /**
     * Test 27: Idempotent delivery prevents duplicate push dispatches in the same request.
     */
    public function test_idempotent_delivery_prevents_duplicate_push_in_same_request(): void
    {
        $user = $this->createUser();
        $this->createDevice($user);

        $inquiry = SupportInquiry::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'subject' => 'Idempotency Test',
            'category' => 'general',
            'status' => 'open',
            'reference_code' => 'INQ-202610-7711',
        ]);
        $message = $this->createSupportMessage($inquiry, $user, 'Checking idempotency.');
        $notification = new SupportReplyNotification($inquiry, $message);

        WebPushService::resetFakeDispatches();
        $service = app(WebPushService::class);
        $service->clearIdempotencyCache();

        // First delivery
        $service->deliver($user, $notification);
        $this->assertCount(1, WebPushService::getFakeDispatches());

        // Second delivery of same notification in same request lifecycle
        $service->deliver($user, $notification);
        // Dispatches count remains 1 (duplicate suppressed!)
        $this->assertCount(1, WebPushService::getFakeDispatches());
    }

    /**
     * Test 28: Missing notification preferences record uses documented default (push enabled).
     */
    public function test_missing_preference_record_defaults_to_enabled(): void
    {
        $user = $this->createUser();
        $this->createDevice($user);

        // Delete preference record to simulate missing preferences
        $user->notificationPreferences()->delete();
        $this->assertNull($user->fresh()->notificationPreferences);

        $inquiry = SupportInquiry::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'subject' => 'Fallback Default Test',
            'category' => 'general',
            'status' => 'open',
            'reference_code' => 'INQ-202610-7722',
        ]);
        $message = $this->createSupportMessage($inquiry, $user, 'Testing default push.');
        $notification = new SupportReplyNotification($inquiry, $message);

        WebPushService::resetFakeDispatches();
        $service = app(WebPushService::class);
        $service->clearIdempotencyCache();

        $service->deliver($user, $notification);

        // Documented default is enabled, so delivery is attempted
        $this->assertCount(1, WebPushService::getFakeDispatches());
    }

    /**
     * Test 29: Deterministic mock DNS answers verify both IPv4 and IPv6 address families.
     * Proves that any private, loopback, link-local, multicast, or reserved answer fails closed.
     */
    public function test_ipv6_and_ipv4_dns_mock_resolution_verifies_all_address_families(): void
    {
        $user = $this->createUser();
        $dummyUrl = 'https://push-service.example.org/v1/notify';

        // 1. IPv4 Loopback (127.0.0.1) -> Rejected
        PushEndpointValidator::setDnsResolver(fn () => ['127.0.0.1']);
        $res = $this->actingAs($user)->postJson('/api/v1/push/subscriptions', [
            'endpoint' => $dummyUrl,
            'keys' => [
                'p256dh' => 'BDqG3w_sample_p256dh_key_with_proper_length_32_chars',
                'auth' => 'sample_auth_token_secret',
            ],
        ]);
        $res->assertStatus(422);

        // 2. IPv6 Loopback (::1) -> Rejected
        PushEndpointValidator::setDnsResolver(fn () => ['::1']);
        $res = $this->actingAs($user)->postJson('/api/v1/push/subscriptions', [
            'endpoint' => $dummyUrl,
            'keys' => [
                'p256dh' => 'BDqG3w_sample_p256dh_key_with_proper_length_32_chars',
                'auth' => 'sample_auth_token_secret',
            ],
        ]);
        $res->assertStatus(422);

        // 3. IPv6 Link-Local (fe80::1) -> Rejected
        PushEndpointValidator::setDnsResolver(fn () => ['fe80::1']);
        $res = $this->actingAs($user)->postJson('/api/v1/push/subscriptions', [
            'endpoint' => $dummyUrl,
            'keys' => [
                'p256dh' => 'BDqG3w_sample_p256dh_key_with_proper_length_32_chars',
                'auth' => 'sample_auth_token_secret',
            ],
        ]);
        $res->assertStatus(422);

        // 4. IPv6 Unique Local Address (fc00::1 / fd00::1) -> Rejected
        PushEndpointValidator::setDnsResolver(fn () => ['fd12:3456:789a::1']);
        $res = $this->actingAs($user)->postJson('/api/v1/push/subscriptions', [
            'endpoint' => $dummyUrl,
            'keys' => [
                'p256dh' => 'BDqG3w_sample_p256dh_key_with_proper_length_32_chars',
                'auth' => 'sample_auth_token_secret',
            ],
        ]);
        $res->assertStatus(422);

        // 5. IPv6 Multicast (ff02::1) -> Rejected
        PushEndpointValidator::setDnsResolver(fn () => ['ff02::1']);
        $res = $this->actingAs($user)->postJson('/api/v1/push/subscriptions', [
            'endpoint' => $dummyUrl,
            'keys' => [
                'p256dh' => 'BDqG3w_sample_p256dh_key_with_proper_length_32_chars',
                'auth' => 'sample_auth_token_secret',
            ],
        ]);
        $res->assertStatus(422);

        // 6. IPv4 Multicast (224.0.0.1) -> Rejected
        PushEndpointValidator::setDnsResolver(fn () => ['224.0.0.1']);
        $res = $this->actingAs($user)->postJson('/api/v1/push/subscriptions', [
            'endpoint' => $dummyUrl,
            'keys' => [
                'p256dh' => 'BDqG3w_sample_p256dh_key_with_proper_length_32_chars',
                'auth' => 'sample_auth_token_secret',
            ],
        ]);
        $res->assertStatus(422);

        // 7. IPv4-Mapped IPv6 (::ffff:127.0.0.1) -> Rejected
        PushEndpointValidator::setDnsResolver(fn () => ['::ffff:127.0.0.1']);
        $res = $this->actingAs($user)->postJson('/api/v1/push/subscriptions', [
            'endpoint' => $dummyUrl,
            'keys' => [
                'p256dh' => 'BDqG3w_sample_p256dh_key_with_proper_length_32_chars',
                'auth' => 'sample_auth_token_secret',
            ],
        ]);
        $res->assertStatus(422);

        // 8. Mixed Records: IPv4 is public, but IPv6 AAAA is internal (::1) -> Rejected
        PushEndpointValidator::setDnsResolver(fn () => ['142.250.190.46', '::1']);
        $res = $this->actingAs($user)->postJson('/api/v1/push/subscriptions', [
            'endpoint' => $dummyUrl,
            'keys' => [
                'p256dh' => 'BDqG3w_sample_p256dh_key_with_proper_length_32_chars',
                'auth' => 'sample_auth_token_secret',
            ],
        ]);
        $res->assertStatus(422);

        // 9. Mixed Records: IPv4 is private (10.0.0.1), but IPv6 AAAA is public -> Rejected
        PushEndpointValidator::setDnsResolver(fn () => ['10.0.0.1', '2607:f8b0:4005:805::200e']);
        $res = $this->actingAs($user)->postJson('/api/v1/push/subscriptions', [
            'endpoint' => $dummyUrl,
            'keys' => [
                'p256dh' => 'BDqG3w_sample_p256dh_key_with_proper_length_32_chars',
                'auth' => 'sample_auth_token_secret',
            ],
        ]);
        $res->assertStatus(422);

        // 10. Valid Public IPv4 and IPv6 answers -> Accepted
        PushEndpointValidator::setDnsResolver(fn () => ['142.250.190.46', '2607:f8b0:4005:805::200e']);
        $res = $this->actingAs($user)->postJson('/api/v1/push/subscriptions', [
            'endpoint' => $dummyUrl,
            'keys' => [
                'p256dh' => 'BDqG3w_sample_p256dh_key_with_proper_length_32_chars',
                'auth' => 'sample_auth_token_secret',
            ],
        ]);
        $res->assertStatus(201);
    }

    /**
     * Test 30: IPv6 literals, zone identifiers, and delivery consistency.
     */
    public function test_ipv6_literals_and_zone_identifiers_in_registration_and_delivery(): void
    {
        $user = $this->createUser();

        // 1. Valid public IPv6 literal in URL
        $validIpv6Url = 'https://[2607:f8b0:4005:805::200e]/push';
        $this->assertTrue(PushEndpointValidator::validate($validIpv6Url));

        $res = $this->actingAs($user)->postJson('/api/v1/push/subscriptions', [
            'endpoint' => $validIpv6Url,
            'keys' => [
                'p256dh' => 'BDqG3w_sample_p256dh_key_with_proper_length_32_chars',
                'auth' => 'sample_auth_token_secret',
            ],
        ]);
        $res->assertStatus(201);

        // 2. Loopback IPv6 literal -> Rejected
        $this->assertFalse(PushEndpointValidator::validate('https://[::1]/push'));

        // 3. Link-local IPv6 literal -> Rejected
        $this->assertFalse(PushEndpointValidator::validate('https://[fe80::1]/push'));

        // 4. Zone identifier bypass attempt -> Rejected
        $this->assertFalse(PushEndpointValidator::validate('https://[fe80::1%eth0]/push'));
        $this->assertFalse(PushEndpointValidator::validate('https://[fe80::1%25eth0]/push'));

        // 5. ULA IPv6 literal -> Rejected
        $this->assertFalse(PushEndpointValidator::validate('https://[fc00::1]/push'));

        // 6. Multicast IPv6 literal -> Rejected
        $this->assertFalse(PushEndpointValidator::validate('https://[ff02::1]/push'));

        // 7. IPv4-mapped IPv6 literal -> Rejected
        $this->assertFalse(PushEndpointValidator::validate('https://[::ffff:127.0.0.1]/push'));

        // 8. Carrier-Grade NAT (RFC 6598: 100.64.0.1) -> Rejected
        $this->assertFalse(PushEndpointValidator::validate('https://100.64.0.1/push'));

        // 9. Delivery pre-flight check consistency:
        // Create device with an invalid endpoint directly in database and assert delivery refuses it
        $badDevice = $this->createDevice($user, ['endpoint' => 'https://[fe80::1]/push']);
        $inquiry = SupportInquiry::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'subject' => 'Delivery SSRF Check',
            'category' => 'general',
            'status' => 'open',
            'reference_code' => 'INQ-202610-7799',
        ]);
        $message = $this->createSupportMessage($inquiry, $user, 'Testing delivery pre-flight.');
        $notification = new SupportReplyNotification($inquiry, $message);

        $service = app(WebPushService::class);
        $service->clearIdempotencyCache();
        WebPushService::resetFakeDispatches();

        $service->deliver($user, $notification);

        // The bad device must be revoked by delivery pre-flight check and not dispatched
        $this->assertFalse($badDevice->fresh()->is_active);
    }

    /**
     * Test 31: Distinct legitimate notifications for the same user are NOT suppressed.
     * Repeated dispatches of the same logical notification are suppressed.
     */
    public function test_distinct_notifications_for_same_user_are_not_falsely_suppressed(): void
    {
        $user = $this->createUser();
        $sender1 = $this->createUser();
        $sender2 = $this->createUser();
        $this->createDevice($user);

        $conv1 = Conversation::create([
            'uuid' => (string) Str::uuid(),
            'user_one_id' => $sender1->id,
            'user_two_id' => $user->id,
            'subject' => 'Conversation One',
            'type' => 'individual',
            'status' => 'active',
            'created_by' => $sender1->id,
        ]);
        $conv2 = Conversation::create([
            'uuid' => (string) Str::uuid(),
            'user_one_id' => $sender2->id,
            'user_two_id' => $user->id,
            'subject' => 'Conversation Two',
            'type' => 'individual',
            'status' => 'active',
            'created_by' => $sender2->id,
        ]);

        $notif1 = new DirectMessageNotification($conv1, $sender1);
        $notif2 = new DirectMessageNotification($conv2, $sender2);

        $service = app(WebPushService::class);
        $service->clearIdempotencyCache();
        WebPushService::resetFakeDispatches();

        // 1. Deliver Notification 1
        $service->deliver($user, $notif1);
        $this->assertCount(1, WebPushService::getFakeDispatches());

        // 2. Deliver Notification 2 (Distinct notification, same class, same user) -> Delivered!
        $service->deliver($user, $notif2);
        $this->assertCount(2, WebPushService::getFakeDispatches());

        // 3. Repeated dispatch of Notification 1 -> Suppressed as duplicate!
        $service->deliver($user, $notif1);
        $this->assertCount(2, WebPushService::getFakeDispatches());

        // 4. Multiple support updates for different messages on the same inquiry
        $inquiry = SupportInquiry::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'subject' => 'Multiple Replies',
            'category' => 'general',
            'status' => 'open',
            'reference_code' => 'INQ-202610-8800',
        ]);
        $msgA = $this->createSupportMessage($inquiry, $user, 'Reply A');
        $msgB = $this->createSupportMessage($inquiry, $user, 'Reply B');

        $supportNotifA = new SupportReplyNotification($inquiry, $msgA);
        $supportNotifB = new SupportReplyNotification($inquiry, $msgB);

        $service->deliver($user, $supportNotifA);
        $this->assertCount(3, WebPushService::getFakeDispatches());

        $service->deliver($user, $supportNotifB);
        $this->assertCount(4, WebPushService::getFakeDispatches());
    }

    /**
     * Test 32: Deduplication state does not leak across requests when using a singleton service.
     */
    public function test_idempotency_state_isolated_between_requests_on_singleton_service(): void
    {
        $user = $this->createUser();
        $this->createDevice($user);

        $inquiry = SupportInquiry::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'subject' => 'Singleton Test',
            'category' => 'general',
            'status' => 'open',
            'reference_code' => 'INQ-202610-8801',
        ]);
        $message = $this->createSupportMessage($inquiry, $user, 'Message for singleton isolation.');
        $notification = new SupportReplyNotification($inquiry, $message);

        // Resolve single service instance
        $service = app(WebPushService::class);
        WebPushService::resetFakeDispatches();
        $service->clearIdempotencyCache();

        // Simulate Request 1
        $request1 = Request::create('/api/v1/support/inquiries', 'POST');
        $this->app->instance('request', $request1);

        $service->deliver($user, $notification);
        $this->assertCount(1, WebPushService::getFakeDispatches());

        // Duplicate delivery in Request 1 -> Suppressed
        $service->deliver($user, $notification);
        $this->assertCount(1, WebPushService::getFakeDispatches());

        // Simulate Request 2 (e.g. next request in Octane / long-running worker)
        $request2 = Request::create('/api/v1/support/inquiries', 'POST');
        $this->app->instance('request', $request2);

        // Delivering in Request 2 must succeed because request lifecycle attributes are isolated
        $service->deliver($user, $notification);
        $this->assertCount(2, WebPushService::getFakeDispatches());
    }

    /**
     * Test 33: Transaction rollback and retry deliver correctly post-commit without orphan state.
     */
    public function test_transaction_rollback_and_retry_idempotency_behavior(): void
    {
        $user = $this->createUser();
        $this->createDevice($user);

        $inquiry = SupportInquiry::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'subject' => 'Transaction Retry Test',
            'category' => 'general',
            'status' => 'open',
            'reference_code' => 'INQ-202610-8802',
        ]);
        $message = $this->createSupportMessage($inquiry, $user, 'Testing retry post-commit.');
        $notification = new SupportReplyNotification($inquiry, $message);

        $service = app(WebPushService::class);
        $service->clearIdempotencyCache();
        WebPushService::resetFakeDispatches();

        // 1. Transaction 1 begins and rolls back
        DB::beginTransaction();
        $service->deliver($user, $notification);
        DB::rollBack();

        // No push dispatched
        $this->assertCount(0, WebPushService::getFakeDispatches());

        // 2. Transaction 2 (Retry) begins and commits
        DB::beginTransaction();
        $service->deliver($user, $notification);
        DB::commit();

        // Delivery completes post-commit
        $this->assertCount(1, WebPushService::getFakeDispatches());
    }

    /**
     * Test 34: Connect Phase 15 - Subscription registration handles flat keys and returns dual response keys.
     */
    public function test_connect_subscription_dual_format_and_response_keys(): void
    {
        $user = $this->createUser();
        PushEndpointValidator::setDnsResolver(fn () => ['142.250.190.46']);

        // 1. Register device using flat public_key / auth_token format
        $endpoint = 'https://updates.push.services.mozilla.com/wpush/v2/test-device-p15-1';
        $p256dh = 'BDqG3w_sample_p256dh_key_with_proper_length_32_chars';
        $auth = 'sample_auth_token_secret';

        $res = $this->actingAs($user)->postJson('/api/v1/push/subscriptions', [
            'endpoint' => $endpoint,
            'public_key' => $p256dh,
            'auth_token' => $auth,
            'device_name' => 'Chrome on Windows PWA',
            'device_type' => 'desktop',
        ]);
        $res->assertStatus(201);
        $res->assertJsonPath('device.device_name', 'Chrome on Windows PWA');
        $res->assertJsonPath('device.device_type', 'desktop');

        // 2. Fetch subscriptions - must return BOTH 'devices' and 'subscriptions' keys for full backward compatibility
        $listRes = $this->actingAs($user)->getJson('/api/v1/push/subscriptions');
        $listRes->assertStatus(200);
        $listRes->assertJsonStructure([
            'devices',
            'subscriptions',
        ]);
        $this->assertCount(1, $listRes->json('devices'));
        $this->assertCount(1, $listRes->json('subscriptions'));
        $this->assertEquals('Chrome on Windows PWA', $listRes->json('devices.0.device_name'));
        $this->assertEquals('Chrome on Windows PWA', $listRes->json('subscriptions.0.device_name'));
    }

    /**
     * Test 35: Connect Phase 15 - Test push notification dispatch endpoint.
     */
    public function test_connect_test_push_notification_dispatch_endpoint(): void
    {
        // 1. Unauthenticated request -> 401
        $unauthRes = $this->postJson('/api/v1/push/test');
        $unauthRes->assertStatus(401);

        // 2. Authenticated user without active devices -> 422 with actionable guidance
        $user = $this->createUser();
        WebPushService::resetFakeDispatches();

        $authRes = $this->actingAs($user)->postJson('/api/v1/push/test');
        $authRes->assertStatus(422);
        $authRes->assertJson([
            'success' => false,
        ]);

        // 3. Authenticated user with registered device -> Dispatches WebPush test notification
        $this->createDevice($user);
        $authWithDeviceRes = $this->actingAs($user)->postJson('/api/v1/push/test');
        $authWithDeviceRes->assertStatus(200);
        $authWithDeviceRes->assertJson([
            'success' => true,
        ]);
        $this->assertGreaterThanOrEqual(1, count(WebPushService::getFakeDispatches()));
    }
}
