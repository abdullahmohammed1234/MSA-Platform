<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\NotificationStatus;
use App\Ems\Enums\NotificationType;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Mail\EventNotificationMail;
use App\Ems\Mail\RegistrationEmailFailedAlertMail;
use App\Ems\Models\Event;
use App\Ems\Models\EventNotification;
use App\Ems\Models\Registration;
use App\Ems\Services\Notifications\EventCommunicationService;
use App\Ems\Services\Notifications\NotificationFailureAlertService;
use App\Ems\Services\Notifications\QueuedEventNotificationDispatcher;
use App\Ems\Support\EmsRoles;
use Database\Seeders\Ems\EmsEmailTemplateSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use App\Ems\Jobs\SendEventNotificationJob;
use Throwable;

class EmsRegistrationEmailReliabilityTest extends EmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ems.notifications.enabled' => true,
            'ems.notifications.bcc_archive_address' => 'archive@example.com',
            'ems.notifications.admin_alert_recipients' => 'admin@example.com',
            'queue.default' => 'sync',
            'mail.default' => 'array',
        ]);

        $this->seed(EmsEmailTemplateSeeder::class);
    }

    public function test_immediate_email_success(): void
    {
        Mail::fake();

        $event = $this->event([
            'status' => EventStatus::RegistrationOpen,
            'start_at' => now()->addDays(5),
        ]);

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_email' => 'test@example.com',
        ]);

        app(EventCommunicationService::class)->sendRegistrationBundle($registration);

        // Verify ledger record state
        $notification = EventNotification::query()
            ->where('registration_id', $registration->id)
            ->where('type', NotificationType::RegistrationConfirmed->value)
            ->firstOrFail();

        $this->assertSame(NotificationStatus::Sent->value, $notification->status->value);
        $this->assertNotNull($notification->sent_at);
        $this->assertNull($notification->failed_at);

        // Verify recipient received mail
        Mail::assertSent(EventNotificationMail::class, function ($mail) use ($registration) {
            return $mail->hasTo($registration->attendee_email);
        });
    }

    public function test_free_registration_email_failure_does_not_fail_registration(): void
    {
        // 1. Setup Mockery expectations on Mail
        Mail::shouldReceive('to')
            ->once()
            ->with('fail@example.com')
            ->andReturnSelf();
        
        Mail::shouldReceive('send')
            ->once()
            ->with(\Mockery::type(EventNotificationMail::class))
            ->andThrow(new \RuntimeException("SMTP connection failed"));

        // Expect the admin alert to be sent
        Mail::shouldReceive('to')
            ->once()
            ->with(['admin@example.com'])
            ->andReturnSelf();

        Mail::shouldReceive('send')
            ->once()
            ->with(\Mockery::type(RegistrationEmailFailedAlertMail::class));

        // 2. Execute
        $event = $this->event([
            'status' => EventStatus::RegistrationOpen,
        ]);

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_email' => 'fail@example.com',
        ]);

        app(EventCommunicationService::class)->sendRegistrationBundle($registration);

        // 3. Verify
        $notification = EventNotification::query()
            ->where('registration_id', $registration->id)
            ->firstOrFail();

        $this->assertSame(NotificationStatus::Failed->value, $notification->status->value);
        $this->assertNotNull($notification->failed_at);
        $this->assertNotNull($notification->alert_sent_at);
        $this->assertSame(1, $notification->retry_count);
        $this->assertSame('SMTP connection failed', $notification->error);

        // Registration state must remain confirmed
        $this->assertSame(RegistrationStatus::Confirmed->value, $registration->fresh()->status->value);
    }

    public function test_transaction_rollback_prevents_email_dispatch(): void
    {
        Mail::fake();

        $event = $this->event(['status' => EventStatus::RegistrationOpen]);

        try {
            DB::transaction(function () use ($event) {
                $registration = Registration::factory()->create([
                    'event_id' => $event->id,
                    'status' => RegistrationStatus::Confirmed,
                    'attendee_email' => 'rollback@example.com',
                ]);

                app(EventCommunicationService::class)->sendRegistrationBundle($registration);

                throw new \RuntimeException("Force transaction rollback");
            });
        } catch (\RuntimeException $e) {
            $this->assertSame("Force transaction rollback", $e->getMessage());
        }

        // Verify that NO email was sent
        Mail::assertNothingSent();

        // Verify that NO EventNotification record was committed
        $this->assertSame(0, EventNotification::query()->count());
    }

    public function test_bcc_applied_only_to_registration_confirmed(): void
    {
        $event = $this->event(['status' => EventStatus::RegistrationOpen]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_email' => 'attendee@example.com',
        ]);

        // 1. Test registration_confirmed (has BCC)
        $regNotification = new EventNotification([
            'recipient_email' => 'attendee@example.com',
            'type' => 'registration_confirmed',
            'subject' => 'Registration Confirmed',
            'body' => 'Body content',
        ]);
        $regMail = new EventNotificationMail($regNotification);
        $envelope = $regMail->envelope();
        $this->assertNotEmpty($envelope->bcc);
        $this->assertSame('archive@example.com', $envelope->bcc[0]->address);

        // 2. Test payment_confirmation (no BCC)
        $payNotification = new EventNotification([
            'recipient_email' => 'attendee@example.com',
            'type' => 'payment_confirmation',
            'subject' => 'Payment Confirmed',
            'body' => 'Body content',
        ]);
        $payMail = new EventNotificationMail($payNotification);
        $this->assertEmpty($payMail->envelope()->bcc);

        // 3. Test with empty configuration
        config(['ems.notifications.bcc_archive_address' => '']);
        $regMailEmptyBcc = new EventNotificationMail($regNotification);
        $this->assertEmpty($regMailEmptyBcc->envelope()->bcc);
    }

    public function test_failure_alert_deduplication_and_loop_prevention(): void
    {
        // Setup a notification that has alert_sent_at populated
        $event = $this->event(['status' => EventStatus::RegistrationOpen]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_email' => 'fail@example.com',
        ]);

        $notification = EventNotification::query()->create([
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'recipient_email' => $registration->attendee_email,
            'type' => 'registration_confirmed',
            'subject' => 'Test',
            'body' => 'Test',
            'status' => NotificationStatus::Failed->value,
            'alert_sent_at' => now(),
        ]);

        // Since alert_sent_at is NOT null, it should NOT try to send any mail alert
        Mail::shouldReceive('to')->never();

        app(NotificationFailureAlertService::class)->sendAlert($notification, "Another failure");
    }

    public function test_alert_failure_does_not_loop_or_break_flow(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->with(['admin@example.com'])
            ->andReturnSelf();

        // Simulate failing to send the alert email
        Mail::shouldReceive('send')
            ->once()
            ->with(\Mockery::type(RegistrationEmailFailedAlertMail::class))
            ->andThrow(new \RuntimeException("Alert SMTP failed"));

        $event = $this->event(['status' => EventStatus::RegistrationOpen]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_email' => 'fail@example.com',
        ]);

        $notification = EventNotification::query()->create([
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'recipient_email' => $registration->attendee_email,
            'type' => 'registration_confirmed',
            'subject' => 'Test',
            'body' => 'Test',
            'status' => NotificationStatus::Failed->value,
        ]);

        // Send alert. Should catch the exception, log it, and complete without throwing.
        app(NotificationFailureAlertService::class)->sendAlert($notification, "Original error");

        // The notification should remain intact, and alert_sent_at remains null since sendAlert failed
        $this->assertNull($notification->fresh()->alert_sent_at);
    }

    public function test_admin_manual_retry_success(): void
    {
        Mail::fake();

        $event = $this->event(['status' => EventStatus::RegistrationOpen]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_email' => 'retry@example.com',
        ]);

        $notification = EventNotification::query()->create([
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'recipient_email' => $registration->attendee_email,
            'type' => 'registration_confirmed',
            'subject' => 'Retry test',
            'body' => 'Retry test',
            'status' => NotificationStatus::Failed->value,
            'retry_count' => 1,
        ]);

        $admin = $this->emsUser(EmsRoles::SUPER_ADMIN);

        // Call the retry API endpoint via controller
        $response = $this->actingAsEms($admin)->postJson($this->url("events/{$event->uuid}/notifications/{$notification->uuid}/retry"));

        $this->assertSuccessEnvelope($response);

        // Verify status goes to Sent (since connection is sync in tests)
        $this->assertSame(NotificationStatus::Sent->value, $notification->fresh()->status->value);
        $this->assertNotNull($notification->fresh()->sent_at);

        Mail::assertSent(EventNotificationMail::class);
    }

    public function test_admin_manual_retry_failure(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->with('fail-retry@example.com')
            ->andReturnSelf();
        
        Mail::shouldReceive('send')
            ->once()
            ->with(\Mockery::type(EventNotificationMail::class))
            ->andThrow(new \RuntimeException("SMTP down on retry"));

        // Expect no new admin alert if alert_sent_at is already set
        Mail::shouldReceive('to')->never();

        $event = $this->event(['status' => EventStatus::RegistrationOpen]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_email' => 'fail-retry@example.com',
        ]);

        $notification = EventNotification::query()->create([
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'recipient_email' => $registration->attendee_email,
            'type' => 'registration_confirmed',
            'subject' => 'Retry test',
            'body' => 'Retry test',
            'status' => NotificationStatus::Failed->value,
            'retry_count' => 1,
            'alert_sent_at' => now(), // Alert already sent
        ]);

        $admin = $this->emsUser(EmsRoles::SUPER_ADMIN);

        // Call the retry API endpoint via controller (should fail in the job synchronously)
        try {
            $this->actingAsEms($admin)->postJson($this->url("events/{$event->uuid}/notifications/{$notification->uuid}/retry"));
        } catch (\Throwable $e) {
            // Exceptions in sync queue bubble up in tests
            $this->assertSame("SMTP down on retry", $e->getMessage());
        }

        // Verify status remains Failed, error logged, retry_count incremented to 2
        $this->assertSame(NotificationStatus::Failed->value, $notification->fresh()->status->value);
        $this->assertSame(2, $notification->fresh()->retry_count);
        $this->assertSame('SMTP down on retry', $notification->fresh()->error);
    }
}
