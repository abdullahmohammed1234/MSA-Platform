<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Role;
use App\Models\Permission;
use App\Models\SupportInquiry;
use App\Models\SupportMessage;
use App\Models\User;
use App\Notifications\ImmediateNotification;
use App\Notifications\PlatformNotification;
use App\Notifications\SupportReplyNotification;
use App\Notifications\SupportStatusUpdatedNotification;
use App\Services\Notifications\CustomDatabaseChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ConnectNotificationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $member;
    protected User $otherMember;
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        // Create member user
        $this->member = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Create second member user
        $this->otherMember = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Create staff user with roles and permissions
        $this->staff = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $staffRole = Role::firstOrCreate(
            ['slug' => 'dawah-coordinator'],
            [
                'name' => 'Dawah Coordinator',
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
            ]
        );

        $supportPerm = Permission::firstOrCreate(
            ['slug' => 'manage_support'],
            [
                'name' => 'Manage Support',
                'module' => 'Support',
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
            ]
        );

        $staffRole->permissions()->syncWithoutDetaching([$supportPerm->id]);
        $this->staff->roles()->syncWithoutDetaching([$staffRole->id]);
    }

    /**
     * Unauthenticated access to notifications is rejected.
     */
    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/notifications')->assertStatus(401);
        $this->getJson('/api/v1/notifications/unread')->assertStatus(401);
        $this->postJson('/api/v1/notifications/test-id/read')->assertStatus(401);
        $this->postJson('/api/v1/notifications/read-all')->assertStatus(401);
        $this->getJson('/api/v1/notifications/preferences')->assertStatus(401);
        $this->putJson('/api/v1/notifications/preferences', [])->assertStatus(401);
    }

    /**
     * Member can list own notifications with pagination and search.
     */
    public function test_member_can_list_notifications_with_pagination_and_search(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Notification::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'user_id' => $this->member->id,
                'type' => PlatformNotification::class,
                'title' => "Campus Alert {$i}",
                'message' => "Message body {$i}",
                'data' => ['action_url' => '/app/events'],
            ]);
        }

        $res = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/v1/notifications?per_page=3');

        $res->assertStatus(200)
            ->assertJsonPath('total', 5)
            ->assertJsonPath('per_page', 3)
            ->assertJsonCount(3, 'data');

        // Test search filter
        $searchRes = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/v1/notifications?search=Alert+2');

        $searchRes->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Campus Alert 2');
    }

    /**
     * Unread count returns accurate count and latest unread items.
     */
    public function test_unread_count_is_accurate(): void
    {
        Notification::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $this->member->id,
            'type' => PlatformNotification::class,
            'title' => 'Unread Alert 1',
            'message' => 'Unread message 1',
            'read_at' => null,
        ]);

        Notification::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $this->member->id,
            'type' => PlatformNotification::class,
            'title' => 'Read Alert 2',
            'message' => 'Read message 2',
            'read_at' => now(),
        ]);

        $res = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/v1/notifications/unread');

        $res->assertStatus(200)
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(1, 'latest_unread');
    }

    /**
     * Member can mark a single notification as read by uuid.
     */
    public function test_member_can_mark_notification_as_read(): void
    {
        $notif = Notification::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $this->member->id,
            'type' => PlatformNotification::class,
            'title' => 'Mark Me Read',
            'message' => 'Pending read message',
            'read_at' => null,
        ]);

        $res = $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/v1/notifications/{$notif->uuid}/read");

        $res->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotNull($notif->fresh()->read_at);
    }

    /**
     * Member can mark all notifications as read in bulk.
     */
    public function test_member_can_mark_all_notifications_as_read(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            Notification::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'user_id' => $this->member->id,
                'type' => PlatformNotification::class,
                'title' => "Unread {$i}",
                'message' => "Message {$i}",
                'read_at' => null,
            ]);
        }

        $res = $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/v1/notifications/read-all');

        $res->assertStatus(200)
            ->assertJsonPath('success', true);

        $unreadCount = $this->member->customNotifications()->unread()->count();
        $this->assertEquals(0, $unreadCount);
    }

    /**
     * Cross-user isolation: User cannot access or mark another user's notification.
     */
    public function test_cross_user_isolation_is_enforced(): void
    {
        $otherNotif = Notification::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $this->otherMember->id,
            'type' => PlatformNotification::class,
            'title' => 'Other Member Secret Notification',
            'message' => 'Confidential data',
            'read_at' => null,
        ]);

        // Member cannot see other member's notifications in index
        $indexRes = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/v1/notifications');
        $indexRes->assertStatus(200)->assertJsonCount(0, 'data');

        // Member cannot mark other member's notification as read (403 forbidden)
        $readRes = $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/v1/notifications/{$otherNotif->uuid}/read");
        $readRes->assertStatus(403);

        // Member cannot delete other member's notification (403 forbidden)
        $delRes = $this->actingAs($this->member, 'sanctum')
            ->deleteJson("/api/v1/notifications/{$otherNotif->uuid}");
        $delRes->assertStatus(403);

        // Notice the other user's notification remains unread
        $this->assertNull($otherNotif->fresh()->read_at);
    }

    /**
     * Notification preferences can be retrieved and updated, including support_updates.
     */
    public function test_member_can_retrieve_and_update_preferences(): void
    {
        $getRes = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/v1/notifications/preferences');

        $getRes->assertStatus(200)
            ->assertJsonStructure([
                'course_completion',
                'new_announcements',
                'upcoming_training',
                'certificate_earned',
                'support_updates',
                'email_enabled',
                'in_app_enabled',
            ]);

        // Update preferences
        $updateRes = $this->actingAs($this->member, 'sanctum')
            ->putJson('/api/v1/notifications/preferences', [
                'support_updates' => false,
                'email_enabled' => false,
                'in_app_enabled' => true,
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('preferences.support_updates', false)
            ->assertJsonPath('preferences.email_enabled', false)
            ->assertJsonPath('preferences.in_app_enabled', true);

        // Preferences update rejects non-boolean values
        $invalidRes = $this->actingAs($this->member, 'sanctum')
            ->putJson('/api/v1/notifications/preferences', [
                'support_updates' => 'invalid-boolean-string',
            ]);
        $invalidRes->assertStatus(422);
    }

    /**
     * Cross-user preference isolation: User cannot modify another user's preferences.
     */
    public function test_cross_user_preference_isolation(): void
    {
        // Member updates own preferences to false
        $this->actingAs($this->member, 'sanctum')
            ->putJson('/api/v1/notifications/preferences', [
                'new_announcements' => false,
            ]);

        // Other member's preferences remain unaffected (true by default)
        $otherPrefsRes = $this->actingAs($this->otherMember, 'sanctum')
            ->getJson('/api/v1/notifications/preferences');

        $otherPrefsRes->assertStatus(200)
            ->assertJsonPath('new_announcements', true);
    }

    /**
     * Staff reply to member inquiry dispatches SupportReplyNotification to member.
     */
    public function test_staff_reply_dispatches_notification_to_member(): void
    {
        $inquiry = SupportInquiry::create([
            'user_id' => $this->member->id,
            'category' => 'general',
            'subject' => 'Prayer Room Question',
            'status' => SupportInquiry::STATUS_OPEN,
            'last_activity_at' => now(),
        ]);

        $replyRes = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/staff/support/inquiries/{$inquiry->reference_code}/messages", [
                'message' => 'Wa alaikum assalam. The prayer room is open 24/7 on Sub level 2.',
            ]);

        $replyRes->assertStatus(201);

        // Check member received in-app notification
        $memberNotifs = $this->member->customNotifications()->get();
        $this->assertCount(1, $memberNotifs);

        $notif = $memberNotifs->first();
        $this->assertStringContainsString('Support Update', $notif->title);
        $this->assertEquals('/app/support/' . $inquiry->reference_code, $notif->data['action_url']);
        $this->assertEquals('support', $notif->data['type']);

        // Payload does NOT leak raw message body or sensitive staff info
        $this->assertArrayNotHasKey('password', $notif->data);
        $this->assertArrayNotHasKey('token', $notif->data);
        $this->assertStringNotContainsString('Sub level 2', $notif->message); // Only inquiry subject is included
    }

    /**
     * Staff inquiry status update dispatches SupportStatusUpdatedNotification.
     */
    public function test_staff_status_update_dispatches_notification(): void
    {
        $inquiry = SupportInquiry::create([
            'user_id' => $this->member->id,
            'category' => 'general',
            'subject' => 'Lockers In Sub',
            'status' => SupportInquiry::STATUS_IN_REVIEW,
            'last_activity_at' => now(),
        ]);

        $statusRes = $this->actingAs($this->staff, 'sanctum')
            ->patchJson("/api/v1/staff/support/inquiries/{$inquiry->reference_code}/status", [
                'status' => SupportInquiry::STATUS_RESOLVED,
            ]);

        $statusRes->assertStatus(200);

        // Check member received status update notification
        $memberNotifs = $this->member->customNotifications()->get();
        $this->assertCount(1, $memberNotifs);

        $notif = $memberNotifs->first();
        $this->assertStringContainsString('Inquiry Updated', $notif->title);
        $this->assertStringContainsString('Resolved', $notif->message);
        $this->assertEquals('/app/support/' . $inquiry->reference_code, $notif->data['action_url']);
    }

    /**
     * When user disables support_updates, inquiry notifications are not delivered.
     */
    public function test_notification_preferences_suppress_support_notifications_when_opted_out(): void
    {
        // Member opts out of support_updates
        $this->member->notificationPreferences()->update([
            'support_updates' => false,
        ]);

        $inquiry = SupportInquiry::create([
            'user_id' => $this->member->id,
            'category' => 'general',
            'subject' => 'Test Inquiry Opt-Out',
            'status' => SupportInquiry::STATUS_OPEN,
            'last_activity_at' => now(),
        ]);

        $message = SupportMessage::create([
            'inquiry_id' => $inquiry->id,
            'user_id' => $this->staff->id,
            'is_staff_reply' => true,
            'message' => 'Staff response here.',
        ]);

        // Directly notify
        $this->member->notify(new SupportReplyNotification($inquiry, $message));

        // In-app notification should NOT be stored because user opted out
        $this->assertEquals(0, $this->member->customNotifications()->count());
    }

    /**
     * Connect notifications execute synchronously without pushing jobs to a background queue.
     */
    public function test_connect_notification_is_synchronous_and_does_not_queue_jobs(): void
    {
        Queue::fake();

        $inquiry = SupportInquiry::create([
            'user_id' => $this->member->id,
            'category' => 'general',
            'subject' => 'Synchronous Test Inquiry',
            'status' => SupportInquiry::STATUS_OPEN,
            'last_activity_at' => now(),
        ]);

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/staff/support/inquiries/{$inquiry->reference_code}/messages", [
                'message' => 'This is a synchronous reply test.',
            ])
            ->assertStatus(201);

        // Verification: No queued jobs pushed to the queue
        Queue::assertNothingPushed();

        // Verification: In-app notification immediately persisted in the database
        $memberNotifs = $this->member->customNotifications()->get();
        $this->assertCount(1, $memberNotifs);
        $this->assertEquals('/app/support/' . $inquiry->reference_code, $memberNotifs->first()->data['action_url']);
    }

    /**
     * Connect notification classes extend ImmediateNotification and do NOT implement ShouldQueue,
     * while platform notifications remain queued via BaseNotification.
     */
    public function test_connect_notification_contract_and_type_hierarchy(): void
    {
        $inquiry = SupportInquiry::create([
            'user_id' => $this->member->id,
            'category' => 'general',
            'subject' => 'Type Hierarchy Test',
            'status' => SupportInquiry::STATUS_OPEN,
            'last_activity_at' => now(),
        ]);

        $message = SupportMessage::create([
            'inquiry_id' => $inquiry->id,
            'user_id' => $this->staff->id,
            'is_staff_reply' => true,
            'message' => 'Staff response.',
        ]);

        $replyNotif = new SupportReplyNotification($inquiry, $message);
        $statusNotif = new SupportStatusUpdatedNotification($inquiry, 'open', 'resolved');
        $platformNotif = new PlatformNotification('Alert', 'Platform alert message');

        // Connect notifications must be ImmediateNotification and NOT ShouldQueue
        $this->assertInstanceOf(ImmediateNotification::class, $replyNotif);
        $this->assertNotInstanceOf(ShouldQueue::class, $replyNotif);

        $this->assertInstanceOf(ImmediateNotification::class, $statusNotif);
        $this->assertNotInstanceOf(ShouldQueue::class, $statusNotif);

        // Platform notifications must implement ShouldQueue
        $this->assertInstanceOf(ShouldQueue::class, $platformNotif);
    }

    /**
     * Notification delivery channels respect independent preferences without cross-channel suppression.
     */
    public function test_notification_delivery_channels_respect_independent_preferences(): void
    {
        $inquiry = SupportInquiry::create([
            'user_id' => $this->member->id,
            'category' => 'general',
            'subject' => 'Channel Independence Test',
            'status' => SupportInquiry::STATUS_OPEN,
            'last_activity_at' => now(),
        ]);

        $message = SupportMessage::create([
            'inquiry_id' => $inquiry->id,
            'user_id' => $this->staff->id,
            'is_staff_reply' => true,
            'message' => 'Staff test message.',
        ]);

        $notif = new SupportReplyNotification($inquiry, $message);

        // Case 1: Default (all channels enabled, topic enabled)
        $this->assertEquals([CustomDatabaseChannel::class, 'mail', \App\Services\Notifications\WebPushChannel::class], $notif->via($this->member));

        // Case 2: In-app disabled, email enabled, push disabled -> only 'mail' (email NOT suppressed)
        $this->member->notificationPreferences()->update([
            'in_app_enabled' => false,
            'email_enabled' => true,
            'push_enabled' => false,
            'support_updates' => true,
        ]);
        $this->member->refresh();
        $this->assertEquals(['mail'], $notif->via($this->member));

        // Case 3: Email disabled, in-app enabled, push disabled -> only database channel (in-app NOT suppressed)
        $this->member->notificationPreferences()->update([
            'in_app_enabled' => true,
            'email_enabled' => false,
            'push_enabled' => false,
            'support_updates' => true,
        ]);
        $this->member->refresh();
        $this->assertEquals([CustomDatabaseChannel::class], $notif->via($this->member));

        // Case 4: Push enabled, in-app & email disabled -> only WebPushChannel
        $this->member->notificationPreferences()->update([
            'in_app_enabled' => false,
            'email_enabled' => false,
            'push_enabled' => true,
            'support_updates' => true,
        ]);
        $this->member->refresh();
        $this->assertEquals([\App\Services\Notifications\WebPushChannel::class], $notif->via($this->member));

        // Case 5: Topic opted out -> empty array (all channels suppressed)
        $this->member->notificationPreferences()->update([
            'in_app_enabled' => true,
            'email_enabled' => true,
            'push_enabled' => true,
            'support_updates' => false,
        ]);
        $this->member->refresh();
        $this->assertEquals([], $notif->via($this->member));
    }

    /**
     * Repeated status update to the same status does not emit duplicate notifications.
     */
    public function test_no_duplicate_notification_on_repeated_identical_status_update(): void
    {
        $inquiry = SupportInquiry::create([
            'user_id' => $this->member->id,
            'category' => 'general',
            'subject' => 'Duplicate Status Test',
            'status' => SupportInquiry::STATUS_RESOLVED,
            'last_activity_at' => now(),
        ]);

        // Attempt to update to the same status (RESOLVED -> RESOLVED)
        $this->actingAs($this->staff, 'sanctum')
            ->patchJson("/api/v1/staff/support/inquiries/{$inquiry->reference_code}/status", [
                'status' => SupportInquiry::STATUS_RESOLVED,
            ])
            ->assertStatus(200);

        // Zero notifications emitted
        $this->assertEquals(0, $this->member->customNotifications()->count());
    }

    /**
     * No self-notification emitted when staff replies to their own inquiry.
     */
    public function test_no_self_notification_when_staff_replies_to_own_inquiry(): void
    {
        // Staff creates an inquiry for themselves
        $inquiry = SupportInquiry::create([
            'user_id' => $this->staff->id,
            'category' => 'general',
            'subject' => 'Staff Own Inquiry',
            'status' => SupportInquiry::STATUS_OPEN,
            'last_activity_at' => now(),
        ]);

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/staff/support/inquiries/{$inquiry->reference_code}/messages", [
                'message' => 'Replying to my own inquiry.',
            ])
            ->assertStatus(201);

        // Staff user receives NO notification for their own action
        $this->assertEquals(0, $this->staff->customNotifications()->count());
    }

    /**
     * No notification emitted after a failed or rolled-back operation.
     */
    public function test_no_notification_on_failed_or_rolled_back_operation(): void
    {
        $inquiry = SupportInquiry::create([
            'user_id' => $this->member->id,
            'category' => 'general',
            'subject' => 'Failed Op Test',
            'status' => SupportInquiry::STATUS_OPEN,
            'last_activity_at' => now(),
        ]);

        // Post empty message -> fails validation (422)
        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/staff/support/inquiries/{$inquiry->reference_code}/messages", [
                'message' => '   ',
            ])
            ->assertStatus(422);

        // Member receives NO notification
        $this->assertEquals(0, $this->member->customNotifications()->count());
    }
}
