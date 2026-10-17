<?php

namespace Tests\Feature\Phase251;

use App\Models\NotificationLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\PlatformNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminResendTest extends TestCase
{
    use RefreshDatabase;

    private User $sender;
    private User $viewer;
    private User $recipient;

    protected function setUp(): void
    {
        parent::setUp();

        $manage = Permission::create(['name' => 'Manage Notifications', 'slug' => 'manage_notifications', 'module' => 'Admin', 'uuid' => (string) Str::uuid()]);
        $send = Permission::create(['name' => 'Send Notifications', 'slug' => 'send_notifications', 'module' => 'Admin', 'uuid' => (string) Str::uuid()]);

        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin', 'uuid' => (string) Str::uuid()]);
        $adminRole->permissions()->sync([$manage->id, $send->id]);

        $viewerRole = Role::create(['name' => 'Notification Viewer', 'slug' => 'notification-viewer', 'uuid' => (string) Str::uuid()]);
        $viewerRole->permissions()->sync([$manage->id]);

        $this->sender = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $this->sender->roles()->sync([$adminRole->id]);

        $this->viewer = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $this->viewer->roles()->sync([$viewerRole->id]);

        $this->recipient = User::factory()->create(['is_active' => true]);
    }

    private function log(string $status, array $attributes = []): NotificationLog
    {
        return NotificationLog::create(array_merge([
            'user_id' => $this->recipient->id,
            'notification_type' => 'App\\Notifications\\CourseCompletedNotification',
            'channel' => 'email',
            'status' => $status,
            'error_message' => $status === 'failed' ? 'SMTP timeout' : null,
            'sent_at' => $status === 'sent' ? now() : null,
        ], $attributes));
    }

    private function resend(User $as, NotificationLog $log)
    {
        return $this->actingAs($as, 'sanctum')->postJson("/api/v1/admin/notifications/resend/{$log->id}");
    }

    public function test_failed_log_is_requeued_once_and_marked_superseded_not_sent(): void
    {
        Notification::fake();
        $log = $this->log('failed');

        $this->resend($this->sender, $log)->assertOk()->assertJsonPath('success', true);

        $this->assertSame('resent', $log->fresh()->status);
        $this->assertNull($log->fresh()->sent_at, 'a queued resend is not a delivery');
        Notification::assertSentToTimes($this->recipient, PlatformNotification::class, 1);
    }

    public function test_second_resend_of_the_same_log_is_rejected(): void
    {
        Notification::fake();
        $log = $this->log('failed');

        $this->resend($this->sender, $log)->assertOk();
        $this->resend($this->sender, $log)->assertStatus(409);

        Notification::assertSentToTimes($this->recipient, PlatformNotification::class, 1);
    }

    public function test_successful_log_cannot_be_resent(): void
    {
        Notification::fake();
        $log = $this->log('sent');

        $this->resend($this->sender, $log)->assertStatus(409);

        $this->assertSame('sent', $log->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_read_only_permission_cannot_trigger_writes(): void
    {
        Notification::fake();
        $log = $this->log('failed');

        $this->resend($this->viewer, $log)->assertForbidden();

        $this->assertSame('failed', $log->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_in_flight_claim_blocks_a_concurrent_admin_but_stale_claim_is_reclaimable(): void
    {
        Notification::fake();
        $log = $this->log('resending');

        $this->resend($this->sender, $log)->assertStatus(409);
        Notification::assertNothingSent();

        NotificationLog::query()->whereKey($log->id)->update(['updated_at' => now()->subMinutes(11)]);
        $this->resend($this->sender, $log)->assertOk();

        $this->assertSame('resent', $log->fresh()->status);
        Notification::assertSentToTimes($this->recipient, PlatformNotification::class, 1);
    }

    public function test_dispatch_failure_restores_failed_state_without_leaking_exception_text(): void
    {
        $log = $this->log('failed');
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('smtp://user:secret@mail.internal refused'));

        $response = $this->resend($this->sender, $log)->assertStatus(500);

        $this->assertStringNotContainsString('secret', (string) $response->getContent());
        $this->assertSame('failed', $log->fresh()->status);
    }
}
