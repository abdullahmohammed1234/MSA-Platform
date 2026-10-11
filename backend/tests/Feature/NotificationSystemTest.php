<?php

namespace Tests\Feature;

use App\Events\AnnouncementPublishedEvent;
use App\Events\CourseCompletedEvent;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\NotificationPreference;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use App\Notifications\CourseCompletedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Setup Admin with permissions
        $this->adminUser = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        
        $adminRole = Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
        ]);
        
        $managePerm = Permission::create([
            'name' => 'Manage Notifications',
            'slug' => 'manage_notifications',
            'module' => 'Admin',
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
        ]);
        
        $sendPerm = Permission::create([
            'name' => 'Send Notifications',
            'slug' => 'send_notifications',
            'module' => 'Admin',
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
        ]);

        $adminRole->permissions()->sync([$managePerm->id, $sendPerm->id]);
        $this->adminUser->roles()->sync([$adminRole->id]);
    }

    /**
     * Test fetching user notifications.
     */
    public function test_user_can_fetch_their_notifications()
    {
        Notification::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $this->user->id,
            'type' => CourseCompletedNotification::class,
            'title' => 'Test Course Completed',
            'message' => 'Congratulations!',
            'data' => ['course_name' => 'Test Course'],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Test Course Completed');
    }

    /**
     * Test fetching unread counts.
     */
    public function test_user_can_fetch_unread_count()
    {
        Notification::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $this->user->id,
            'type' => CourseCompletedNotification::class,
            'title' => 'Unread 1',
            'message' => 'Msg 1',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/notifications/unread');

        $response->assertStatus(200)
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(1, 'latest_unread');
    }

    /**
     * Test marking single notification as read.
     */
    public function test_user_can_mark_notification_as_read()
    {
        $notif = Notification::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $this->user->id,
            'type' => CourseCompletedNotification::class,
            'title' => 'Unread 1',
            'message' => 'Msg 1',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/notifications/{$notif->uuid}/read");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotNull($notif->refresh()->read_at);
    }

    /**
     * Test marking all notifications as read.
     */
    public function test_user_can_mark_all_notifications_as_read()
    {
        Notification::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $this->user->id,
            'type' => CourseCompletedNotification::class,
            'title' => 'Unread 1',
            'message' => 'Msg 1',
        ]);

        Notification::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $this->user->id,
            'type' => CourseCompletedNotification::class,
            'title' => 'Unread 2',
            'message' => 'Msg 2',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/notifications/read-all');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals(0, Notification::unread()->count());
    }

    /**
     * Test deleting a notification.
     */
    public function test_user_can_delete_notification()
    {
        $notif = Notification::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $this->user->id,
            'type' => CourseCompletedNotification::class,
            'title' => 'To Delete',
            'message' => 'Msg',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/notifications/{$notif->uuid}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('notifications', ['id' => $notif->id]);
    }

    /**
     * Test managing preferences.
     */
    public function test_user_can_fetch_and_update_preferences()
    {
        // Fetch
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/notifications/preferences');

        $response->assertStatus(200)
            ->assertJsonPath('email_enabled', true);

        // Update
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/v1/notifications/preferences', [
                'email_enabled' => false,
                'course_completion' => false,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('preferences.email_enabled', false)
            ->assertJsonPath('preferences.course_completion', false);
    }

    /**
     * Test admin can broadcast manual announcement.
     */
    public function test_admin_can_broadcast_announcement()
    {
        Event::fake([AnnouncementPublishedEvent::class]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/notifications/broadcast', [
                'title' => 'Platform Maintained',
                'message' => 'We have updated the server.',
                'audience' => 'All',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        Event::assertDispatched(AnnouncementPublishedEvent::class);
    }

    /**
     * Test admin logs and stats.
     */
    public function test_admin_can_view_logs_and_stats()
    {
        NotificationLog::create([
            'user_id' => $this->user->id,
            'notification_type' => CourseCompletedNotification::class,
            'channel' => 'in_app',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        // Logs
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/notifications/logs');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Stats
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/notifications/stats');

        $response->assertStatus(200)
            ->assertJsonPath('total_sent', 1)
            ->assertJsonPath('total_failed', 0);
    }

    /**
     * Test preferences check during sending.
     */
    public function test_system_honors_user_preferences()
    {
        LaravelNotification::fake();

        // Opt out of course completion
        $this->user->notificationPreferences()->update([
            'course_completion' => false
        ]);

        event(new CourseCompletedEvent($this->user, 'Intro to Islam'));

        LaravelNotification::assertNotSentTo($this->user, CourseCompletedNotification::class);
    }

    /**
     * Test User A cannot read or delete User B notification (IDOR isolation).
     */
    public function test_user_cannot_access_or_mutate_other_users_notification()
    {
        $otherUser = User::factory()->create(['is_active' => true]);

        $notif = Notification::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $otherUser->id,
            'type' => CourseCompletedNotification::class,
            'title' => 'Other User Private Notification',
            'message' => 'Private message',
        ]);

        // Attempt read
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/notifications/{$notif->uuid}/read");
        $response->assertStatus(403);

        // Attempt delete
        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/notifications/{$notif->uuid}");
        $response->assertStatus(403);
    }

    /**
     * Test EMS registration event dispatches in-app notification to canonical user.
     */
    public function test_ems_registration_event_dispatches_in_app_notification()
    {
        $event = \App\Ems\Models\Event::factory()->create(['name' => 'MSA Test Gathering']);

        $registration = new \App\Ems\Models\Registration();
        $registration->uuid = (string) \Illuminate\Support\Str::uuid();
        $registration->event_id = $event->id;
        $registration->user_id = $this->user->id;
        $registration->reference = 'REG-TEST-100';
        $registration->attendee_name = $this->user->name;
        $registration->attendee_email = $this->user->email;
        $registration->type = \App\Ems\Enums\RegistrationType::Free;
        $registration->status = \App\Ems\Enums\RegistrationStatus::Confirmed;
        $registration->save();

        event(new \App\Ems\Events\RegistrationCreated($registration, $this->user));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'title' => 'Registration Confirmed: MSA Test Gathering',
        ]);
    }

    /**
     * Test Store order fulfillment status update dispatches in-app notification.
     */
    public function test_store_order_fulfillment_update_dispatches_in_app_notification()
    {
        $order = \App\Store\Models\StoreOrder::create([
            'user_id' => $this->user->id,
            'order_number' => 'ORD-TEST-999',
            'subtotal_cents' => 2500,
            'tax_cents' => 125,
            'total_cents' => 2625,
            'currency' => 'CAD',
            'payment_status' => \App\Store\Enums\StorePaymentStatus::Paid,
            'fulfillment_status' => \App\Store\Enums\StoreFulfillmentStatus::Pending,
            'customer_name' => $this->user->name,
            'customer_email' => $this->user->email,
        ]);

        $service = app(\App\Store\Services\StoreOrderService::class);
        $service->updateFulfillmentStatus($order, \App\Store\Enums\StoreFulfillmentStatus::Completed);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'title' => 'Store Order Update (#ORD-TEST-999)',
        ]);
    }

    /**
     * Test guest EMS registration does not notify an account even if email matches.
     */
    public function test_guest_registration_does_not_notify_unrelated_account()
    {
        $event = \App\Ems\Models\Event::factory()->create(['name' => 'MSA Guest Event']);

        $registration = new \App\Ems\Models\Registration();
        $registration->uuid = (string) \Illuminate\Support\Str::uuid();
        $registration->event_id = $event->id;
        $registration->user_id = null; // Guest registration
        $registration->reference = 'REG-GUEST-101';
        $registration->attendee_name = $this->user->name;
        $registration->attendee_email = $this->user->email; // Matching email
        $registration->type = \App\Ems\Enums\RegistrationType::Free;
        $registration->status = \App\Ems\Enums\RegistrationStatus::Confirmed;
        $registration->save();

        event(new \App\Ems\Events\RegistrationCreated($registration, null));

        $this->assertDatabaseMissing('notifications', [
            'title' => 'Registration Confirmed: MSA Guest Event',
        ]);
    }

    /**
     * Test PlatformNotification honors in_app_enabled disabled preference.
     */
    public function test_platform_notification_honors_in_app_disabled_preference()
    {
        NotificationPreference::updateOrCreate(
            ['user_id' => $this->user->id],
            ['in_app_enabled' => false, 'email_enabled' => true]
        );

        $event = \App\Ems\Models\Event::factory()->create(['name' => 'MSA Opt-Out Gathering']);

        $registration = new \App\Ems\Models\Registration();
        $registration->uuid = (string) \Illuminate\Support\Str::uuid();
        $registration->event_id = $event->id;
        $registration->user_id = $this->user->id;
        $registration->reference = 'REG-OPTOUT-102';
        $registration->attendee_name = $this->user->name;
        $registration->attendee_email = $this->user->email;
        $registration->type = \App\Ems\Enums\RegistrationType::Free;
        $registration->status = \App\Ems\Enums\RegistrationStatus::Confirmed;
        $registration->save();

        event(new \App\Ems\Events\RegistrationCreated($registration, $this->user));

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->user->id,
            'title' => 'Registration Confirmed: MSA Opt-Out Gathering',
        ]);
    }
}
