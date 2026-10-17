<?php

namespace Tests\Feature\Phase251;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\NotificationStatus;
use App\Ems\Enums\NotificationType;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Jobs\SendEventNotificationJob;
use App\Ems\Models\Event;
use App\Ems\Models\EventNotification;
use App\Ems\Models\Registration;
use App\Ems\Services\Notifications\QueuedEventNotificationDispatcher;
use App\Ems\Support\EmsRoles;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Feature\Ems\EmsTestCase;

/**
 * Manual EMS retries must not break an in-flight worker's lease, and the global
 * retry route must keep event scoping for soft-deleted events.
 */
class EmsRetryLeaseTest extends EmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['ems.notifications.enabled' => true]);
    }

    private function failedNotification(array $attributes = [], ?Event $event = null): EventNotification
    {
        $event ??= $this->event(['status' => EventStatus::RegistrationOpen, 'start_at' => now()->addDays(2)]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_email' => 'attendee@example.com',
        ]);

        return EventNotification::query()->create(array_merge([
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'recipient_email' => 'attendee@example.com',
            'type' => NotificationType::RegistrationConfirmed->value,
            'subject' => 'Subject',
            'body' => 'Body',
            'status' => NotificationStatus::Failed->value,
            'error' => 'Simulated SMTP outage',
            'retry_count' => 1,
            'scheduled_at' => now()->subMinute(),
            'idempotency_key' => 'phase251:'.Str::uuid(),
        ], $attributes));
    }

    public function test_manual_retry_leaves_a_worker_held_lease_untouched(): void
    {
        Queue::fake();
        $notification = $this->failedNotification([
            'queue_status' => 'processing',
            'last_attempt_at' => now()->subSeconds(10),
        ]);

        app(QueuedEventNotificationDispatcher::class)->retry($notification);

        $fresh = $notification->fresh();
        $this->assertSame('processing', $fresh->queue_status);
        $this->assertSame(NotificationStatus::Failed, $fresh->status);
        Queue::assertNotPushed(SendEventNotificationJob::class);
    }

    public function test_in_flight_retry_followed_by_manual_retry_sends_once(): void
    {
        Mail::fake();
        $notification = $this->failedNotification([
            'queue_status' => 'processing',
            'last_attempt_at' => now()->subSeconds(10),
        ]);

        app(QueuedEventNotificationDispatcher::class)->retry($notification);
        (new SendEventNotificationJob($notification->id))->handle();

        Mail::assertNothingSent();
        $this->assertSame('processing', $notification->fresh()->queue_status);
    }

    public function test_manual_retry_reclaims_a_stale_lease(): void
    {
        Mail::fake();
        $notification = $this->failedNotification([
            'queue_status' => 'processing',
            'last_attempt_at' => now()->subHour(),
        ]);

        app(QueuedEventNotificationDispatcher::class)->retry($notification);

        $this->assertSame(NotificationStatus::Sent, $notification->fresh()->status);
    }

    public function test_global_retry_keeps_scope_for_soft_deleted_events(): void
    {
        Mail::fake();
        $outsider = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $event = $this->event(['status' => EventStatus::RegistrationOpen, 'start_at' => now()->addDays(2)]);
        $notification = $this->failedNotification([], $event);
        $event->delete();

        $this->actingAsEms($outsider)
            ->postJson(route('api.ems.notifications.retryGlobal', $notification->uuid))
            ->assertForbidden();

        $this->assertSame(NotificationStatus::Failed, $notification->fresh()->status);
        Mail::assertNothingSent();
    }
}
