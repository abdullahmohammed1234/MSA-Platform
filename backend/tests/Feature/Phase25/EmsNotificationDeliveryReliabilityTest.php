<?php

namespace Tests\Feature\Phase25;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\NotificationStatus;
use App\Ems\Enums\NotificationType;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\ReminderAudience;
use App\Ems\Enums\ReminderOffsetUnit;
use App\Ems\Jobs\ProcessDueNotificationsJob;
use App\Ems\Jobs\SendEventNotificationJob;
use App\Ems\Mail\EventNotificationMail;
use App\Ems\Models\Event;
use App\Ems\Models\EventNotification;
use App\Ems\Models\EventReminder;
use App\Ems\Models\Registration;
use App\Ems\Models\ReminderDispatch;
use App\Ems\Services\Notifications\EventCancellationService;
use App\Ems\Services\Notifications\ReminderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\Feature\Ems\EmsTestCase;

/**
 * SQLite runs these serially; they prove the claim/lease logic, not MySQL
 * row-lock behaviour under true parallel workers.
 */
class EmsNotificationDeliveryReliabilityTest extends EmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['ems.notifications.enabled' => true]);
    }

    private function registration(?Event $event = null, string $email = 'attendee@example.com'): Registration
    {
        $event ??= $this->event(['status' => EventStatus::RegistrationOpen, 'start_at' => now()->addDays(2)]);

        return Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_email' => $email,
        ]);
    }

    private function notification(array $attributes = [], ?Registration $registration = null): EventNotification
    {
        $registration ??= $this->registration();

        return EventNotification::query()->create(array_merge([
            'event_id' => $registration->event_id,
            'registration_id' => $registration->id,
            'recipient_email' => $registration->attendee_email,
            'type' => NotificationType::RegistrationConfirmed->value,
            'subject' => 'Subject',
            'body' => 'Body',
            'status' => NotificationStatus::Pending->value,
            'scheduled_at' => now()->subMinute(),
            'idempotency_key' => 'phase25:'.Str::uuid(),
        ], $attributes));
    }

    private function useFailingMailer(): void
    {
        Mail::extend('phase25-failing', fn () => new class extends AbstractTransport {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Simulated SMTP outage');
            }

            public function __toString(): string
            {
                return 'phase25-failing://';
            }
        });

        config([
            'mail.mailers.phase25-failing' => ['transport' => 'phase25-failing'],
            'mail.default' => 'phase25-failing',
        ]);
    }

    // --- Sweeper claim (duplicate dispatch) ---------------------------------

    public function test_repeated_sweeps_dispatch_a_due_row_only_once(): void
    {
        Queue::fake();
        $notification = $this->notification();

        (new ProcessDueNotificationsJob())->handle(app(\App\Ems\Services\Notifications\QueuedEventNotificationDispatcher::class));
        (new ProcessDueNotificationsJob())->handle(app(\App\Ems\Services\Notifications\QueuedEventNotificationDispatcher::class));

        Queue::assertPushed(SendEventNotificationJob::class, 1);
        $this->assertSame('queued', $notification->fresh()->queue_status);
    }

    public function test_sweeper_skips_fresh_claims_and_recovers_stale_ones(): void
    {
        Queue::fake();
        $dispatcher = app(\App\Ems\Services\Notifications\QueuedEventNotificationDispatcher::class);

        $freshQueued = $this->notification(['queue_status' => 'queued', 'queued_at' => now()->subMinutes(5)]);
        $freshProcessing = $this->notification(['queue_status' => 'processing', 'last_attempt_at' => now()->subSeconds(30)]);

        (new ProcessDueNotificationsJob())->handle($dispatcher);
        Queue::assertNothingPushed();

        // Negative control: same rows once their claims go stale are recovered.
        $freshQueued->forceFill(['queued_at' => now()->subMinutes(31)])->save();
        $freshProcessing->forceFill(['last_attempt_at' => now()->subMinutes(6)])->save();

        (new ProcessDueNotificationsJob())->handle($dispatcher);
        Queue::assertPushed(SendEventNotificationJob::class, 2);
    }

    public function test_queue_if_due_does_not_push_a_second_job_for_an_in_flight_row(): void
    {
        Queue::fake();
        $dispatcher = app(\App\Ems\Services\Notifications\QueuedEventNotificationDispatcher::class);
        $notification = $this->notification();

        $dispatcher->queueIfDue($notification);
        $dispatcher->queueIfDue($notification->fresh());

        $notification->forceFill(['queue_status' => 'processing', 'last_attempt_at' => now()])->save();
        $dispatcher->queueIfDue($notification->fresh());

        Queue::assertPushed(SendEventNotificationJob::class, 1);
        $this->assertSame('processing', $notification->fresh()->queue_status);
    }

    public function test_claim_for_dispatch_is_exclusive(): void
    {
        $notification = $this->notification();

        $this->assertTrue(EventNotification::claimForDispatch($notification->id));
        $this->assertFalse(EventNotification::claimForDispatch($notification->id));
    }

    // --- Worker lease ---------------------------------------------------------

    public function test_job_skips_row_owned_by_another_worker_on_any_attempt(): void
    {
        Mail::fake();
        $notification = $this->notification(['queue_status' => 'processing', 'last_attempt_at' => now()->subSeconds(20)]);

        $job = new SendEventNotificationJob($notification->id);
        $job->setJob(new class extends \Illuminate\Queue\Jobs\SyncJob {
            public function __construct()
            {
            }

            public function attempts()
            {
                return 2;
            }
        });
        $job->handle();

        Mail::assertNothingSent();
        $this->assertSame(NotificationStatus::Pending, $notification->fresh()->status);
    }

    public function test_job_reclaims_abandoned_processing_row(): void
    {
        Mail::fake();
        $notification = $this->notification(['queue_status' => 'processing', 'last_attempt_at' => now()->subMinutes(10)]);

        (new SendEventNotificationJob($notification->id))->handle();

        Mail::assertSent(EventNotificationMail::class, 1);
        $this->assertSame(NotificationStatus::Sent, $notification->fresh()->status);
    }

    // --- Inline (sync) send failures ------------------------------------------

    public function test_inline_send_failure_hands_retry_to_worker_without_throwing(): void
    {
        $this->useFailingMailer();
        config(['queue.default' => 'database']);
        $notification = $this->notification(['queue_status' => 'queued', 'queued_at' => now()]);

        SendEventNotificationJob::dispatchSync($notification->id);

        $fresh = $notification->fresh();
        $this->assertSame(NotificationStatus::Failed, $fresh->status);
        $this->assertSame('retry_pending', $fresh->queue_status);
        $this->assertSame(1, $fresh->retry_count);

        $queued = DB::table('jobs')->get();
        $this->assertCount(1, $queued);
        $this->assertSame('ems-notifications', $queued->first()->queue);
        $this->assertGreaterThan(now()->timestamp, (int) $queued->first()->available_at);
    }

    public function test_inline_send_failure_without_async_queue_still_fails_permanently(): void
    {
        $this->useFailingMailer();
        config(['queue.default' => 'sync']);
        $notification = $this->notification(['queue_status' => 'queued', 'queued_at' => now()]);

        try {
            SendEventNotificationJob::dispatchSync($notification->id);
            $this->fail('Expected the transport exception to propagate on a sync-only queue.');
        } catch (TransportException) {
        }

        $this->assertSame('failed', $notification->fresh()->queue_status);
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_vms_dispatch_does_not_throw_into_caller_when_inline_send_fails(): void
    {
        $this->useFailingMailer();
        config(['queue.default' => 'sync']);

        $opportunity = \App\Volunteering\Models\Opportunity::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Iftar Setup',
            'slug' => 'phase25-'.Str::lower(Str::random(6)),
            'description' => 'Setup crew',
            'status' => 'open',
            'start_at' => now()->addDays(3),
            'end_at' => now()->addDays(3)->addHours(3),
            'capacity' => 10,
        ]);
        $signup = \App\Volunteering\Models\Signup::create([
            'uuid' => (string) Str::uuid(),
            'opportunity_id' => $opportunity->id,
            'name' => 'Volunteer',
            'email' => 'volunteer@example.com',
            'status' => 'signed_up',
        ]);

        $notification = app(\App\Volunteering\Services\VmsNotificationDispatcher::class)->dispatchNotification(
            signup: $signup,
            type: NotificationType::VmsSignupConfirmed,
            idempotencyKey: 'phase25-vms-'.$signup->id,
            recipientEmail: 'volunteer@example.com',
        );

        $this->assertSame(NotificationStatus::Failed, $notification->fresh()->status);
    }

    // --- Cancelled events -----------------------------------------------------

    public function test_reminder_notification_for_cancelled_event_is_not_sent(): void
    {
        Mail::fake();
        $event = $this->event(['status' => EventStatus::Cancelled, 'start_at' => now()->addDay()]);
        $registration = $this->registration($event);
        $reminder = $this->notification(['type' => NotificationType::EventReminder->value], $registration);
        $cancelNotice = $this->notification(['type' => NotificationType::EventCancelled->value], $registration);

        (new SendEventNotificationJob($reminder->id))->handle();
        (new SendEventNotificationJob($cancelNotice->id))->handle();

        $this->assertSame(NotificationStatus::Cancelled, $reminder->fresh()->status);
        $this->assertSame(NotificationStatus::Sent, $cancelNotice->fresh()->status);
        Mail::assertSent(EventNotificationMail::class, 1);
    }

    public function test_reminder_service_does_not_fan_out_for_cancelled_event(): void
    {
        Mail::fake();
        $event = $this->event(['status' => EventStatus::Cancelled, 'start_at' => now()->addHours(2)]);
        $this->registration($event);
        $reminder = $this->reminder($event);

        $this->assertSame(0, app(ReminderService::class)->dispatchReminder($reminder));
        $this->assertFalse($reminder->fresh()->enabled);
        $this->assertSame(0, EventNotification::query()->where('event_id', $event->id)->count());
    }

    public function test_cancelling_event_suppresses_pending_reminders(): void
    {
        Mail::fake();
        $event = $this->event(['status' => EventStatus::RegistrationOpen, 'start_at' => now()->addDays(3)]);
        $registration = $this->registration($event);
        $reminder = $this->reminder($event, now()->addDay());
        $scheduled = $this->notification([
            'type' => NotificationType::EventReminder->value,
            'status' => NotificationStatus::Scheduled->value,
            'scheduled_at' => now()->addDay(),
        ], $registration);
        $receipt = $this->notification([
            'type' => NotificationType::PaymentConfirmation->value,
            'status' => NotificationStatus::Scheduled->value,
            'scheduled_at' => now()->addDay(),
        ], $registration);

        $event->update(['status' => EventStatus::Cancelled]);
        app(EventCancellationService::class)->handleCancelled($event->fresh(), 'Venue unavailable');

        $this->assertFalse($reminder->fresh()->enabled);
        $this->assertNull($reminder->fresh()->next_run_at);
        $this->assertSame(NotificationStatus::Cancelled, $scheduled->fresh()->status);
        $this->assertSame(NotificationStatus::Scheduled, $receipt->fresh()->status);
    }

    // --- Reminder fan-out lease -----------------------------------------------

    public function test_reminder_held_by_another_run_is_not_fanned_out_twice(): void
    {
        Mail::fake();
        $event = $this->event(['status' => EventStatus::RegistrationOpen, 'start_at' => now()->addHours(3)]);
        $this->registration($event);
        $reminder = $this->reminder($event, now()->addMinutes(10));

        $this->assertSame(0, app(ReminderService::class)->dispatchReminder($reminder));
        $this->assertTrue($reminder->fresh()->enabled);
    }

    public function test_interrupted_reminder_fan_out_resumes_for_remaining_recipients_only(): void
    {
        Mail::fake();
        $event = $this->event(['status' => EventStatus::RegistrationOpen, 'start_at' => now()->addHours(3)]);
        $alreadyHandled = $this->registration($event, 'first@example.com');
        $this->registration($event, 'second@example.com');
        $reminder = $this->reminder($event, now()->subMinute());

        ReminderDispatch::query()->create([
            'reminder_id' => $reminder->id,
            'event_id' => $event->id,
            'registration_id' => $alreadyHandled->id,
            'dispatched_at' => now()->subMinutes(20),
        ]);

        $this->assertSame(1, app(ReminderService::class)->dispatchReminder($reminder));
        $this->assertFalse($reminder->fresh()->enabled);
        $this->assertSame(
            ['second@example.com'],
            EventNotification::query()->where('event_id', $event->id)->pluck('recipient_email')->all()
        );
    }

    private function reminder(Event $event, $nextRunAt = null): EventReminder
    {
        return EventReminder::query()->create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $event->id,
            'offset_value' => 1,
            'offset_unit' => ReminderOffsetUnit::Hours->value,
            'enabled' => true,
            'template_key' => NotificationType::EventReminder->value,
            'audience' => ReminderAudience::Confirmed->value,
            'next_run_at' => $nextRunAt ?? now()->subMinute(),
        ]);
    }
}
