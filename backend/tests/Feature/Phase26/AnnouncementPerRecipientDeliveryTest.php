<?php

namespace Tests\Feature\Phase26;

use App\Jobs\Announcements\RecoverAnnouncementDeliveriesJob;
use App\Models\ApplicationAccess;
use App\Models\AnnouncementDelivery;
use App\Models\AnnouncementDispatch;
use App\Models\CMS\Announcement;
use App\Models\Notification as InAppNotification;
use App\Models\NewsletterSubscriber;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Platform\Models\PlatformAlert;
use App\Services\Announcements\AnnouncementDeliveryService;
use App\Services\CMS\AnnouncementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Tests\TestCase;

/**
 * Durable per-recipient announcement delivery. SQLite runs these serially: they
 * prove the claim/lease/state logic, not InnoDB row-lock behaviour (see the
 * MySQL concurrency suite for real parallel workers).
 */
class AnnouncementPerRecipientDeliveryTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, int> */
    public static array $delivered = [];

    /** @var list<string> */
    public static array $failFor = [];

    private User $sender;
    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        self::$delivered = [];
        self::$failFor = [];

        Mail::extend('p26-recording', fn () => new class extends AbstractTransport {
            protected function doSend(SentMessage $message): void
            {
                foreach ($message->getEnvelope()->getRecipients() as $address) {
                    /** @var Address $address */
                    $email = $address->getAddress();
                    if (in_array($email, AnnouncementPerRecipientDeliveryTest::$failFor, true)) {
                        throw new TransportException('Simulated SMTP rejection');
                    }
                    AnnouncementPerRecipientDeliveryTest::$delivered[$email] = (AnnouncementPerRecipientDeliveryTest::$delivered[$email] ?? 0) + 1;
                }
            }

            public function __toString(): string
            {
                return 'p26-recording://';
            }
        });
        config([
            'mail.mailers.p26-recording' => ['transport' => 'p26-recording'],
            'mail.default' => 'p26-recording',
            'queue.default' => 'sync',
        ]);

        $manage = Permission::create(['name' => 'Manage Notifications', 'slug' => 'manage_notifications', 'module' => 'Admin', 'uuid' => (string) Str::uuid()]);
        $send = Permission::create(['name' => 'Send Notifications', 'slug' => 'send_notifications', 'module' => 'Admin', 'uuid' => (string) Str::uuid()]);
        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin', 'uuid' => (string) Str::uuid()]);
        $adminRole->permissions()->sync([$manage->id, $send->id]);
        $viewerRole = Role::create(['name' => 'Notification Viewer', 'slug' => 'notification-viewer', 'uuid' => (string) Str::uuid()]);
        $viewerRole->permissions()->sync([$manage->id]);

        $this->sender = User::factory()->create(['is_active' => true, 'email_verified_at' => now(), 'email' => 'sender@example.test']);
        $this->sender->roles()->sync([$adminRole->id]);
        $this->viewer = User::factory()->create(['is_active' => true, 'email_verified_at' => now(), 'email' => 'viewer@example.test']);
        $this->viewer->roles()->sync([$viewerRole->id]);
        ApplicationAccess::create(['user_id' => $this->viewer->id, 'application' => 'admin-portal']);
    }

    private function member(string $email): User
    {
        return User::factory()->create(['is_active' => true, 'email' => $email]);
    }

    private function publish(string $title = 'Eid Prayer Times'): Announcement
    {
        return app(AnnouncementService::class)->create([
            'title' => $title,
            'content' => '<p>Details</p>',
            'status' => 'published',
        ], null);
    }

    private function dispatchFor(Announcement $announcement, string $kind = AnnouncementDispatch::KIND_INITIAL): AnnouncementDispatch
    {
        return AnnouncementDispatch::query()->where('announcement_id', $announcement->id)->where('kind', $kind)->latest('id')->firstOrFail();
    }

    private function emailRow(AnnouncementDispatch $dispatch, User $user): AnnouncementDelivery
    {
        return AnnouncementDelivery::query()
            ->where('dispatch_id', $dispatch->id)
            ->where('recipient_type', AnnouncementDelivery::RECIPIENT_USER)
            ->where('recipient_id', $user->id)
            ->where('channel', AnnouncementDelivery::CHANNEL_EMAIL)
            ->firstOrFail();
    }

    public function test_one_failed_recipient_does_not_resend_to_successful_ones(): void
    {
        $a = $this->member('a@example.test');
        $b = $this->member('b@example.test');
        $c = $this->member('c@example.test');
        self::$failFor = ['b@example.test'];

        $announcement = $this->publish();
        $dispatch = $this->dispatchFor($announcement);

        $this->assertSame(AnnouncementDelivery::STATUS_SENT, $this->emailRow($dispatch, $a)->status);
        $this->assertSame(AnnouncementDelivery::STATUS_FAILED, $this->emailRow($dispatch, $b)->status);
        $this->assertNotNull($this->emailRow($dispatch, $b)->next_attempt_at);
        $this->assertSame(AnnouncementDispatch::STATUS_SENDING, $dispatch->fresh()->status);

        // Transient failure clears; the retry after backoff reaches only B.
        self::$failFor = [];
        $this->travel(2)->minutes();
        (new RecoverAnnouncementDeliveriesJob())->handle(app(AnnouncementDeliveryService::class));

        $this->assertSame(1, self::$delivered['a@example.test']);
        $this->assertSame(1, self::$delivered['b@example.test']);
        $this->assertSame(1, self::$delivered['c@example.test']);
        $this->assertSame(AnnouncementDispatch::STATUS_COMPLETED, $dispatch->fresh()->status);

        // Each user has exactly one in-app notification for this announcement.
        foreach ([$a, $b, $c] as $user) {
            $this->assertSame(1, InAppNotification::query()->where('user_id', $user->id)->count());
        }
    }

    public function test_retry_before_backoff_elapses_does_not_attempt_again(): void
    {
        $b = $this->member('b@example.test');
        self::$failFor = ['b@example.test'];

        $dispatch = $this->dispatchFor($this->publish());
        (new RecoverAnnouncementDeliveriesJob())->handle(app(AnnouncementDeliveryService::class));

        $this->assertSame(1, $this->emailRow($dispatch, $b)->attempts);
    }

    public function test_permanent_failure_is_observable_and_recoverable_only_by_an_admin(): void
    {
        config(['announcements.delivery.max_attempts' => 2]);
        $b = $this->member('b@example.test');
        self::$failFor = ['b@example.test'];

        $dispatch = $this->dispatchFor($this->publish());
        $this->travel(2)->minutes();
        (new RecoverAnnouncementDeliveriesJob())->handle(app(AnnouncementDeliveryService::class));

        $row = $this->emailRow($dispatch, $b);
        $this->assertSame(AnnouncementDelivery::STATUS_PERMANENTLY_FAILED, $row->status);
        $this->assertSame(2, $row->attempts);
        $this->assertSame(AnnouncementDispatch::STATUS_COMPLETED_WITH_FAILURES, $dispatch->fresh()->status);
        $this->assertSame(1, PlatformAlert::query()->count());

        // Automatic sweeps never touch a permanent failure.
        $this->travel(2)->hours();
        (new RecoverAnnouncementDeliveriesJob())->handle(app(AnnouncementDeliveryService::class));
        $this->assertSame(2, $row->fresh()->attempts);

        // Visible to a viewer without recipient email addresses.
        $show = $this->actingAs($this->viewer, 'sanctum')
            ->getJson("/api/v1/admin/notifications/announcement-dispatches/{$dispatch->uuid}")
            ->assertOk()
            ->assertJsonPath('data.status', AnnouncementDispatch::STATUS_COMPLETED_WITH_FAILURES)
            ->assertJsonPath('data.failed_deliveries.0.recipient_id', $b->id);
        $this->assertStringNotContainsString('@', $show->getContent());

        // Viewing permission alone cannot trigger recovery.
        $this->actingAs($this->viewer, 'sanctum')
            ->postJson("/api/v1/admin/notifications/announcement-dispatches/{$dispatch->uuid}/retry-failed")
            ->assertForbidden();

        self::$failFor = [];
        $this->actingAs($this->sender, 'sanctum')
            ->postJson("/api/v1/admin/notifications/announcement-dispatches/{$dispatch->uuid}/retry-failed")
            ->assertOk()
            ->assertJsonPath('data.retried', 1);

        $row->refresh();
        $this->assertSame(AnnouncementDelivery::STATUS_SENT, $row->status);
        $this->assertSame(1, $row->manual_retries);
        $this->assertSame(1, self::$delivered['b@example.test']);
        $this->assertSame(AnnouncementDispatch::STATUS_COMPLETED, $dispatch->fresh()->status);

        $this->actingAs($this->sender, 'sanctum')
            ->postJson("/api/v1/admin/notifications/announcement-dispatches/{$dispatch->uuid}/retry-failed")
            ->assertStatus(409);
    }

    public function test_claim_is_exclusive_and_a_stale_lease_is_reclaimed_once(): void
    {
        Queue::fake();
        $this->member('a@example.test');
        $announcement = $this->publish();
        $dispatch = AnnouncementDispatch::query()->where('announcement_id', $announcement->id)->firstOrFail();
        app(AnnouncementDeliveryService::class)->prepare($dispatch);

        $row = AnnouncementDelivery::query()->where('dispatch_id', $dispatch->id)->firstOrFail();

        $this->assertTrue(AnnouncementDelivery::claim($row->id));
        $this->assertFalse(AnnouncementDelivery::claim($row->id), 'a held lease cannot be claimed');

        $this->travel(11)->minutes();
        $this->assertTrue(AnnouncementDelivery::claim($row->id), 'an abandoned claim is reclaimable');
        $this->assertFalse(AnnouncementDelivery::claim($row->id));
        $this->assertSame(2, $row->fresh()->attempts);
    }

    public function test_slow_worker_cannot_overwrite_a_reclaimed_row(): void
    {
        $a = $this->member('a@example.test');
        $dispatch = $this->dispatchFor($this->publish());
        $row = $this->emailRow($dispatch, $a);

        // Simulate the row being reclaimed (attempt 2) after an earlier worker (attempt 1) stalled.
        $row->forceFill(['status' => AnnouncementDelivery::STATUS_SENDING, 'attempts' => 2, 'last_attempt_at' => now()])->save();

        $finish = new \ReflectionMethod(AnnouncementDeliveryService::class, 'finish');
        $finish->invoke(app(AnnouncementDeliveryService::class), $row, 1, ['status' => AnnouncementDelivery::STATUS_FAILED]);

        $this->assertSame(AnnouncementDelivery::STATUS_SENDING, $row->fresh()->status);
    }

    public function test_crash_after_in_app_write_does_not_duplicate_the_in_app_notification(): void
    {
        $a = $this->member('a@example.test');
        $dispatch = $this->dispatchFor($this->publish());
        $inApp = AnnouncementDelivery::query()
            ->where('dispatch_id', $dispatch->id)
            ->where('recipient_id', $a->id)
            ->where('channel', AnnouncementDelivery::CHANNEL_IN_APP)
            ->firstOrFail();
        $this->assertSame(1, InAppNotification::query()->where('user_id', $a->id)->count());

        // Worker wrote the notification, then died before recording success.
        $inApp->forceFill(['status' => AnnouncementDelivery::STATUS_SENDING, 'sent_at' => null, 'last_attempt_at' => now()->subHour()])->save();
        $dispatch->forceFill(['status' => AnnouncementDispatch::STATUS_SENDING, 'completed_at' => null])->save();

        (new RecoverAnnouncementDeliveriesJob())->handle(app(AnnouncementDeliveryService::class));

        $this->assertSame(AnnouncementDelivery::STATUS_SENT, $inApp->fresh()->status);
        $this->assertSame(1, InAppNotification::query()->where('user_id', $a->id)->count());
    }

    public function test_republish_and_edits_never_start_another_automatic_send(): void
    {
        $this->member('a@example.test');
        $announcement = $this->publish();

        app(AnnouncementService::class)->update($announcement, ['status' => 'draft'], null);
        app(AnnouncementService::class)->update($announcement->fresh(), ['status' => 'published'], null);
        app(AnnouncementService::class)->update($announcement->fresh(), ['title' => 'Eid Prayer Times (corrected)'], null);

        $this->assertSame(1, AnnouncementDispatch::query()->where('announcement_id', $announcement->id)->count());
        $this->assertSame(1, self::$delivered['a@example.test']);
    }

    public function test_intentional_resend_is_a_separate_auditable_dispatch(): void
    {
        $this->member('a@example.test');
        $announcement = $this->publish();

        $this->actingAs($this->viewer, 'sanctum')
            ->postJson("/api/v1/admin/notifications/announcements/{$announcement->id}/resend")
            ->assertForbidden();

        $uuid = $this->actingAs($this->sender, 'sanctum')
            ->postJson("/api/v1/admin/notifications/announcements/{$announcement->id}/resend")
            ->assertStatus(202)
            ->json('data.dispatch_uuid');

        $resend = AnnouncementDispatch::query()->where('uuid', $uuid)->firstOrFail();
        $this->assertSame(AnnouncementDispatch::KIND_RESEND, $resend->kind);
        $this->assertSame($this->sender->id, $resend->created_by);
        $this->assertSame(AnnouncementDispatch::STATUS_COMPLETED, $resend->status);
        $this->assertSame(2, self::$delivered['a@example.test'], 'initial + intentional resend');
        $this->assertDatabaseHas('audit_logs', ['action' => 'announcement.resend', 'user_id' => $this->sender->id]);
    }

    public function test_concurrent_resend_requests_start_only_one_dispatch(): void
    {
        $this->member('a@example.test');
        $announcement = $this->publish();
        Queue::fake();

        $this->actingAs($this->sender, 'sanctum')
            ->postJson("/api/v1/admin/notifications/announcements/{$announcement->id}/resend")
            ->assertStatus(202);
        $this->actingAs($this->sender, 'sanctum')
            ->postJson("/api/v1/admin/notifications/announcements/{$announcement->id}/resend")
            ->assertStatus(409);

        $this->assertSame(1, AnnouncementDispatch::query()->where('kind', AnnouncementDispatch::KIND_RESEND)->count());
    }

    public function test_unpublished_announcement_cannot_be_resent(): void
    {
        $draft = app(AnnouncementService::class)->create(['title' => 'Draft', 'content' => 'x', 'status' => 'draft'], null);

        $this->actingAs($this->sender, 'sanctum')
            ->postJson("/api/v1/admin/notifications/announcements/{$draft->id}/resend")
            ->assertStatus(422);
    }

    public function test_subscribers_are_tracked_and_unsubscribes_before_send_are_honoured(): void
    {
        Queue::fake();
        $active = NewsletterSubscriber::query()->create(['uuid' => (string) Str::uuid(), 'email' => 'reader@example.test', 'is_active' => true, 'subscribed_at' => now()]);
        $leaving = NewsletterSubscriber::query()->create(['uuid' => (string) Str::uuid(), 'email' => 'leaving@example.test', 'is_active' => true, 'subscribed_at' => now()]);

        $announcement = $this->publish();
        $dispatch = $this->dispatchFor($announcement);
        $service = app(AnnouncementDeliveryService::class);
        $service->prepare($dispatch);
        $leaving->update(['is_active' => false]);
        $service->sendDue($dispatch->fresh());

        $rows = AnnouncementDelivery::query()->where('dispatch_id', $dispatch->id)
            ->where('recipient_type', AnnouncementDelivery::RECIPIENT_SUBSCRIBER)->get()->keyBy('recipient_id');
        $this->assertSame(AnnouncementDelivery::STATUS_SENT, $rows[$active->id]->status);
        $this->assertSame(AnnouncementDelivery::STATUS_SKIPPED, $rows[$leaving->id]->status);
        $this->assertSame(1, self::$delivered['reader@example.test']);
        $this->assertArrayNotHasKey('leaving@example.test', self::$delivered);
    }

    public function test_dispatch_interrupted_during_preparation_is_resumed_by_the_sweep(): void
    {
        $this->member('a@example.test');
        // The preparation job is queued but its worker never runs (lost job / crashed worker).
        config(['queue.default' => 'database']);
        $announcement = $this->publish();
        $dispatch = $this->dispatchFor($announcement);
        $this->assertSame(AnnouncementDispatch::STATUS_PREPARING, $dispatch->status);
        $this->assertArrayNotHasKey('a@example.test', self::$delivered);

        config(['queue.default' => 'sync']);
        $this->travel(6)->minutes();
        (new RecoverAnnouncementDeliveriesJob())->handle(app(AnnouncementDeliveryService::class));

        $this->assertSame(AnnouncementDispatch::STATUS_COMPLETED, $dispatch->fresh()->status);
        $this->assertSame(1, self::$delivered['a@example.test']);
    }

    public function test_retry_and_resend_are_guarded_by_both_route_middleware_and_policy(): void
    {
        foreach (['announcement-dispatches.retry-failed', 'announcements.resend'] as $name) {
            $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('api.admin.notifications.'.$name);
            $this->assertNotNull($route, $name);
            $this->assertContains('permission:send_notifications', $route->gatherMiddleware(), $name);
            $this->assertContains('app.access:admin-portal', $route->gatherMiddleware(), $name);
        }

        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($this->viewer)->denies('send', InAppNotification::class));
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($this->viewer)->allows('manage', InAppNotification::class));
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($this->sender)->allows('send', InAppNotification::class));
    }

    public function test_hourly_email_cap_defers_the_rest_without_blocking_in_app_delivery(): void
    {
        config(['announcements.delivery.email_hourly_cap' => 2]);
        foreach (['a', 'b', 'c', 'd'] as $name) {
            $this->member("{$name}@example.test");
        }

        $dispatch = $this->dispatchFor($this->publish());
        $emailRows = fn () => AnnouncementDelivery::query()->where('dispatch_id', $dispatch->id)->where('channel', AnnouncementDelivery::CHANNEL_EMAIL);

        $this->assertSame(2, array_sum(self::$delivered), 'only the hourly cap is sent');
        $this->assertSame(2, $emailRows()->where('status', AnnouncementDelivery::STATUS_SENT)->count());
        $this->assertSame(0, $emailRows()->where('attempts', '>', 0)->where('status', '!=', AnnouncementDelivery::STATUS_SENT)->count(), 'deferred rows were never attempted');
        $this->assertSame(0, AnnouncementDelivery::query()->where('dispatch_id', $dispatch->id)
            ->where('channel', AnnouncementDelivery::CHANNEL_IN_APP)->where('status', '!=', AnnouncementDelivery::STATUS_SENT)->count());
        $this->assertSame(AnnouncementDispatch::STATUS_SENDING, $dispatch->fresh()->status);

        // Within the same hour the sweep sends nothing more.
        $this->travel(10)->minutes();
        (new RecoverAnnouncementDeliveriesJob())->handle(app(AnnouncementDeliveryService::class));
        $this->assertSame(2, array_sum(self::$delivered));

        // Each later hour releases another slice until every recipient has exactly one.
        for ($hour = 0; $hour < 3; $hour++) {
            $this->travel(61)->minutes();
            (new RecoverAnnouncementDeliveriesJob())->handle(app(AnnouncementDeliveryService::class));
        }

        $this->assertSame(AnnouncementDispatch::STATUS_COMPLETED, $dispatch->fresh()->status);
        $this->assertSame($emailRows()->count(), array_sum(self::$delivered));
        $this->assertSame([1], array_values(array_unique(self::$delivered)));
    }

    public function test_throttled_sending_job_requeues_itself_with_a_delay(): void
    {
        config(['announcements.delivery.email_hourly_cap' => 1]);
        $this->member('a@example.test');
        $this->member('b@example.test');
        $dispatch = $this->dispatchFor($this->publish());

        Queue::fake();
        $this->travel(5)->minutes();
        (new \App\Jobs\Announcements\SendAnnouncementDeliveriesJob($dispatch->id))->handle(app(AnnouncementDeliveryService::class));

        Queue::assertPushed(\App\Jobs\Announcements\SendAnnouncementDeliveriesJob::class, function ($job) {
            return $job->delay !== null && now()->diffInMinutes($job->delay) >= 9;
        });
    }
}
