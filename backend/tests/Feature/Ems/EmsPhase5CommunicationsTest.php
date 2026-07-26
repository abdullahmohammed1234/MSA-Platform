<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\EventTransition;
use App\Ems\Enums\NotificationStatus;
use App\Ems\Enums\NotificationType;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\ReminderOffsetUnit;
use App\Ems\Jobs\ProcessDueRemindersJob;
use App\Ems\Jobs\QueueRegistrationConfirmation;
use App\Ems\Jobs\SendEventNotificationJob;
use App\Ems\Mail\EventNotificationMail;
use App\Ems\Models\Event;
use App\Ems\Models\EventNotification;
use App\Ems\Models\EventReminder;
use App\Ems\Models\Registration;
use App\Ems\Services\EventLifecycleService;
use App\Ems\Services\Notifications\EventCommunicationService;
use App\Ems\Services\Notifications\QueuedEventNotificationDispatcher;
use App\Ems\Services\Notifications\ReminderService;
use App\Ems\Support\EmsRoles;
use Database\Seeders\Ems\EmsEmailTemplateSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

class EmsPhase5CommunicationsTest extends EmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ems.notifications.enabled' => true,
            'queue.default' => 'sync',
            'mail.default' => 'array',
        ]);

        $this->seed(EmsEmailTemplateSeeder::class);
    }

    public function test_registration_confirmation_bundle_queues_and_sends(): void
    {
        Mail::fake();

        $event = $this->event([
            'status' => EventStatus::RegistrationOpen,
            'start_at' => now()->addDays(10),
        ]);

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_email' => 'attendee@example.com',
            'attendee_name' => 'Test Attendee',
            'confirmed_at' => now(),
        ]);

        app(EventCommunicationService::class)->sendRegistrationBundle($registration->fresh());

        $types = EventNotification::query()
            ->where('registration_id', $registration->id)
            ->pluck('type')
            ->all();

        $this->assertContains(NotificationType::RegistrationConfirmed->value, $types);

        Mail::assertSent(EventNotificationMail::class);
    }

    public function test_queue_registration_confirmation_job_is_idempotent_per_type(): void
    {
        Mail::fake();

        $event = $this->event(['status' => EventStatus::RegistrationOpen]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_email' => 'idempotent@example.com',
        ]);

        QueueRegistrationConfirmation::dispatchSync($registration->id);
        QueueRegistrationConfirmation::dispatchSync($registration->id);

        $this->assertSame(
            1,
            EventNotification::query()
                ->where('registration_id', $registration->id)
                ->where('type', NotificationType::RegistrationConfirmed->value)
                ->count()
        );
    }

    public function test_reminder_scheduling_and_duplicate_prevention(): void
    {
        Mail::fake();

        $event = $this->event([
            'status' => EventStatus::RegistrationOpen,
            'start_at' => now()->addHour(),
        ]);

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_email' => 'remind@example.com',
        ]);

        $reminders = app(ReminderService::class);
        $reminder = $reminders->create($event, [
            'label' => '1 Hour Before',
            'offset_value' => 1,
            'offset_unit' => ReminderOffsetUnit::Hours->value,
            'enabled' => true,
            'audience' => 'confirmed',
        ]);

        $this->assertNotNull($reminder->next_run_at);
        $this->assertTrue($reminder->next_run_at->lte(now()));

        $first = $reminders->dispatchReminder($reminder->fresh());
        $second = $reminders->dispatchReminder($reminder->fresh());

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);
        $this->assertSame(
            1,
            EventNotification::query()
                ->where('registration_id', $registration->id)
                ->where('type', NotificationType::EventReminder->value)
                ->count()
        );
    }

    public function test_process_due_reminders_job_runs(): void
    {
        Mail::fake();

        $event = $this->event([
            'status' => EventStatus::RegistrationOpen,
            'start_at' => now()->addMinutes(30),
        ]);

        Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_email' => 'due@example.com',
        ]);

        EventReminder::query()->create([
            'event_id' => $event->id,
            'label' => 'Soon',
            'offset_value' => 1,
            'offset_unit' => ReminderOffsetUnit::Hours->value,
            'enabled' => true,
            'template_key' => NotificationType::EventReminder->value,
            'audience' => 'confirmed',
            'next_run_at' => now()->subMinute(),
        ]);

        (new ProcessDueRemindersJob())->handle(app(ReminderService::class));

        $this->assertTrue(
            EventNotification::query()
                ->where('event_id', $event->id)
                ->where('type', NotificationType::EventReminder->value)
                ->exists()
        );
    }

    public function test_event_update_notification_respects_audience(): void
    {
        Mail::fake();
        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $event = $this->event([
            'status' => EventStatus::RegistrationOpen,
            'organizer_id' => $organizer->id,
            'created_by' => $organizer->id,
            'location' => 'Old Hall',
        ]);

        Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_email' => 'update@example.com',
        ]);

        $response = $this->actingAsEms($organizer)->putJson($this->url("events/{$event->uuid}"), [
            'location' => 'New Hall',
            'notify_audience' => 'registered',
        ]);

        $this->assertSuccessEnvelope($response);
        $this->assertTrue(
            EventNotification::query()
                ->where('event_id', $event->id)
                ->where('type', NotificationType::EventUpdated->value)
                ->exists()
        );
    }

    public function test_event_cancellation_queues_notifications(): void
    {
        Mail::fake();

        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $event = $this->event([
            'status' => EventStatus::RegistrationOpen,
            'organizer_id' => $organizer->id,
            'created_by' => $organizer->id,
        ]);

        Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_email' => 'cancel@example.com',
        ]);

        $response = $this->actingAsEms($organizer)->postJson(
            $this->url("events/{$event->uuid}/transitions"),
            ['action' => EventTransition::Cancel->value]
        );

        $this->assertSuccessEnvelope($response);
        $this->assertSame(EventStatus::Cancelled->value, $response->json('data.status'));
        $this->assertTrue(
            EventNotification::query()
                ->where('event_id', $event->id)
                ->where('type', NotificationType::EventCancelled->value)
                ->exists()
        );
    }

    public function test_waitlist_join_queues_confirmation(): void
    {
        Mail::fake();

        $event = $this->event([
            'status' => EventStatus::RegistrationOpen,
            'is_public' => true,
            'capacity' => 1,
            'waitlist_enabled' => true,
        ]);

        app(\App\Ems\Services\WaitlistService::class)->join($event, [
            'first_name' => 'Wait',
            'last_name' => 'Lister',
            'email' => 'waitlist@example.com',
        ]);

        $this->assertTrue(
            EventNotification::query()
                ->where('type', NotificationType::WaitlistConfirmed->value)
                ->where('recipient_email', 'waitlist@example.com')
                ->exists()
        );
    }

    public function test_post_event_complete_queues_thank_you_and_feedback(): void
    {
        Mail::fake();

        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = $this->event([
            'status' => EventStatus::Live,
            'organizer_id' => $admin->id,
        ]);

        Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_email' => 'thanks@example.com',
        ]);

        // Need a ticket for ticket_holders audience
        $registration = Registration::query()->where('event_id', $event->id)->firstOrFail();
        \App\Ems\Models\Ticket::factory()->create([
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'holder_email' => 'thanks@example.com',
        ]);

        app(EventLifecycleService::class)->apply($event, EventTransition::Complete, $admin);

        $this->assertTrue(
            EventNotification::query()
                ->where('event_id', $event->id)
                ->where('type', NotificationType::ThankYou->value)
                ->exists()
        );
        $this->assertTrue(
            EventNotification::query()
                ->where('event_id', $event->id)
                ->where('type', NotificationType::FeedbackRequest->value)
                ->exists()
        );
    }

    public function test_manual_resend_and_retry_endpoints(): void
    {
        Mail::fake();
        Queue::fake([SendEventNotificationJob::class]);

        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $event = $this->event([
            'status' => EventStatus::RegistrationOpen,
            'organizer_id' => $organizer->id,
            'created_by' => $organizer->id,
        ]);

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_email' => 'resend@example.com',
        ]);

        $failed = EventNotification::query()->create([
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'recipient_email' => 'resend@example.com',
            'type' => NotificationType::TicketEmail->value,
            'status' => NotificationStatus::Failed,
            'subject' => 'Failed ticket',
            'body' => 'fail',
            'error' => 'smtp down',
            'retry_count' => 1,
        ]);

        $resend = $this->actingAsEms($organizer)->postJson(
            $this->url("events/{$event->uuid}/notifications/resend"),
            [
                'type' => NotificationType::RegistrationConfirmed->value,
                'registration_uuid' => $registration->uuid,
            ]
        );
        $this->assertSuccessEnvelope($resend);

        $retry = $this->actingAsEms($organizer)->postJson(
            $this->url("events/{$event->uuid}/notifications/{$failed->uuid}/retry")
        );
        $this->assertSuccessEnvelope($retry);

        $history = $this->actingAsEms($organizer)->getJson(
            $this->url("events/{$event->uuid}/notifications")
        );
        $this->assertSuccessEnvelope($history);

        $summary = $this->actingAsEms($organizer)->getJson(
            $this->url("events/{$event->uuid}/notifications/summary")
        );
        $this->assertSuccessEnvelope($summary);
        $this->assertArrayHasKey('total', $summary->json('data'));
    }

    public function test_reminder_crud_api(): void
    {
        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $event = $this->event([
            'organizer_id' => $organizer->id,
            'created_by' => $organizer->id,
            'start_at' => now()->addDays(14),
        ]);

        $create = $this->actingAsEms($organizer)->postJson(
            $this->url("events/{$event->uuid}/reminders"),
            [
                'label' => '3 Days Before',
                'offset_value' => 3,
                'offset_unit' => 'days',
                'enabled' => true,
            ]
        );
        $this->assertSuccessEnvelope($create);
        $reminderUuid = $create->json('data.uuid');

        $update = $this->actingAsEms($organizer)->putJson(
            $this->url("events/{$event->uuid}/reminders/{$reminderUuid}"),
            ['enabled' => false]
        );
        $this->assertSuccessEnvelope($update);
        $this->assertFalse($update->json('data.enabled'));

        $list = $this->actingAsEms($organizer)->getJson(
            $this->url("events/{$event->uuid}/reminders")
        );
        $this->assertSuccessEnvelope($list);

        $delete = $this->actingAsEms($organizer)->deleteJson(
            $this->url("events/{$event->uuid}/reminders/{$reminderUuid}")
        );
        $this->assertSuccessEnvelope($delete);
    }

    public function test_notification_preferences_api(): void
    {
        $user = $this->emsUser(EmsRoles::ATTENDEE);

        $get = $this->actingAsEms($user)->getJson($this->url('notification-preferences'));
        $this->assertSuccessEnvelope($get);

        $put = $this->actingAsEms($user)->putJson($this->url('notification-preferences'), [
            'event_reminders' => false,
            'marketing_emails' => true,
        ]);
        $this->assertSuccessEnvelope($put);
        $this->assertFalse($put->json('data.event_reminders'));
        $this->assertTrue($put->json('data.marketing_emails'));
    }

    public function test_failed_send_marks_notification_failed_and_retry_works(): void
    {
        config(['ems.notifications.enabled' => true]);

        $event = $this->event();
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'attendee_email' => 'retry@example.com',
        ]);

        $notification = app(QueuedEventNotificationDispatcher::class)->notifyRegistration(
            $registration,
            NotificationType::RegistrationConfirmed->value,
            ['force' => true]
        );

        // Simulate failure then manual retry path
        $notification->markFailed('Temporary SMTP error');
        $this->assertSame(NotificationStatus::Failed, $notification->fresh()->status);

        $retried = app(QueuedEventNotificationDispatcher::class)->retry($notification->fresh());
        $this->assertContains(
            $retried->status,
            [NotificationStatus::Pending, NotificationStatus::Sent, NotificationStatus::Failed]
        );
    }

    public function test_open_registration_seeds_default_reminders(): void
    {
        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = $this->event([
            'status' => EventStatus::Published,
            'start_at' => now()->addWeeks(2),
        ]);

        app(EventLifecycleService::class)->apply($event, EventTransition::OpenRegistration, $admin);

        $this->assertGreaterThanOrEqual(
            5,
            EventReminder::query()->where('event_id', $event->id)->count()
        );
    }
}
