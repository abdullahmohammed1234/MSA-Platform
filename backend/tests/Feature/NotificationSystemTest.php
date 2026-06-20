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
}
