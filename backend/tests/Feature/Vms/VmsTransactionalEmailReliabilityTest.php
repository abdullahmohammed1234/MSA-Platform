<?php

namespace Tests\Feature\Vms;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\NotificationType;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Jobs\SendEventNotificationJob;
use App\Ems\Mail\EventNotificationMail;
use App\Ems\Models\Event;
use App\Ems\Models\EventNotification;
use App\Ems\Models\Registration;
use App\Ems\Services\CheckoutService;
use App\Ems\Services\Notifications\EventCommunicationService;
use App\Ems\Services\Notifications\ReminderService;
use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Shift;
use App\Volunteering\Models\Signup;
use App\Volunteering\Services\VolunteerSignupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VmsTransactionalEmailReliabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ems.notifications.enabled', true);
        Config::set('vms.notifications.enabled', true);
    }

    /**
     * Test 1 — Volunteer confirmation is queued upon successful signup.
     */
    public function test_volunteer_signup_queues_transactional_confirmation_email(): void
    {
        Queue::fake([SendEventNotificationJob::class]);

        $opportunity = Opportunity::create([
            'title' => 'Friday Setup',
            'slug' => 'friday-setup-test',
            'status' => 'open',
            'start_at' => now()->addDays(2),
        ]);

        $signup = app(VolunteerSignupService::class)->registerSignup([
            'opportunity_id' => $opportunity->id,
            'name' => 'Immediate Volunteer',
            'email' => 'immediate@sfu.ca',
        ]);

        $this->assertEquals('signed_up', $signup->status);

        // Verify ledger row exists
        $notification = EventNotification::where('volunteering_signup_id', $signup->id)
            ->where('type', NotificationType::VmsSignupConfirmed->value)
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals('immediate@sfu.ca', $notification->recipient_email);

        // Verify SendEventNotificationJob was pushed to queue immediately after transaction commit
        Queue::assertPushed(SendEventNotificationJob::class, function ($job) use ($notification) {
            return $job->notificationId === $notification->id;
        });
    }

    /**
     * Test 2 — Email is NOT dispatched if database transaction rolls back.
     */
    public function test_transaction_rollback_does_not_queue_or_send_confirmation_email(): void
    {
        Queue::fake([SendEventNotificationJob::class]);
        Mail::fake();

        $opportunity = Opportunity::create([
            'title' => 'Rollback Test Opportunity',
            'slug' => 'rollback-test-opp',
            'status' => 'open',
        ]);

        try {
            DB::transaction(function () use ($opportunity) {
                app(VolunteerSignupService::class)->registerSignup([
                    'opportunity_id' => $opportunity->id,
                    'name' => 'Failing Volunteer',
                    'email' => 'failing@sfu.ca',
                ]);

                // Simulate unexpected failure causing rollback
                throw new \Exception('Simulated database rollback');
            });
        } catch (\Exception $e) {
            // Expected transaction failure
        }

        // Verify no signup persisted
        $this->assertDatabaseMissing('volunteering_signups', [
            'email' => 'failing@sfu.ca',
        ]);

        // Verify no notification row persisted in DB
        $this->assertDatabaseMissing('ems_notifications', [
            'recipient_email' => 'failing@sfu.ca',
        ]);

        // Verify no job was pushed to queue worker
        Queue::assertNotPushed(SendEventNotificationJob::class);
        Mail::assertNothingSent();
    }

    /**
     * Test 3 — Email IS dispatched/queued when database transaction commits.
     */
    public function test_transaction_commit_queues_email_immediately_without_waiting_for_scheduler(): void
    {
        Queue::fake([SendEventNotificationJob::class]);

        $opportunity = Opportunity::create([
            'title' => 'Commit Test Opportunity',
            'slug' => 'commit-test-opp',
            'status' => 'open',
        ]);

        DB::transaction(function () use ($opportunity) {
            app(VolunteerSignupService::class)->registerSignup([
                'opportunity_id' => $opportunity->id,
                'name' => 'Committed Volunteer',
                'email' => 'committed@sfu.ca',
            ]);
        });

        // Verify job queued immediately after commit
        Queue::assertPushed(SendEventNotificationJob::class);
    }

    /**
     * Test 4 — EMS confirmation is queued immediately after DB commit.
     */
    public function test_ems_registration_confirmation_is_queued_immediately_after_commit(): void
    {
        Queue::fake([SendEventNotificationJob::class]);

        $event = Event::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'EMS Free Gala',
            'slug' => 'ems-free-gala',
            'is_public' => true,
            'capacity' => 100,
            'start_at' => now()->addDays(5),
        ]);
        $event->status = EventStatus::RegistrationOpen;
        $event->save();

        $registration = app(CheckoutService::class)->registerFree($event, [
            'first_name' => 'EMS',
            'last_name' => 'Attendee',
            'email' => 'emsattendee@sfu.ca',
        ]);

        $this->assertEquals(RegistrationStatus::Confirmed, $registration->status);

        $notification = EventNotification::where('registration_id', $registration->id)
            ->where('type', NotificationType::RegistrationConfirmed->value)
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals('emsattendee@sfu.ca', $notification->recipient_email);

        Queue::assertPushed(SendEventNotificationJob::class, function ($job) use ($notification) {
            return $job->notificationId === $notification->id;
        });
    }

    /**
     * Test 5 — Required ticket & QR information exists on issued tickets before confirmation email is queued/dispatched.
     */
    public function test_ticket_and_qr_payload_exist_before_confirmation_email_is_dispatched(): void
    {
        $event = Event::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'EMS Ticketed Event',
            'slug' => 'ems-ticketed-event',
            'is_public' => true,
            'capacity' => 50,
            'start_at' => now()->addDays(5),
        ]);
        $event->status = EventStatus::RegistrationOpen;
        $event->save();

        $ticketType = $event->ticketTypes()->create([
            'name' => 'General Admission',
            'price' => 0.00,
            'quantity' => 50,
            'quantity_sold' => 0,
            'is_active' => true,
        ]);

        $registration = app(CheckoutService::class)->registerFree($event, [
            'first_name' => 'Ticket',
            'last_name' => 'Holder',
            'email' => 'ticketholder@sfu.ca',
            'ticket_type_id' => $ticketType->uuid,
        ]);

        // Verify tickets and QR payload are created in DB before email rendering
        $tickets = $registration->tickets()->get();
        $this->assertCount(1, $tickets);

        $ticket = $tickets->first();
        $this->assertNotEmpty($ticket->code);
        $this->assertNotEmpty($ticket->qr_payload);
        $this->assertNotNull($ticket->qr_generated_at);

        // Verify EventNotification contains rendered ticket codes
        $notification = EventNotification::where('registration_id', $registration->id)->first();
        $this->assertNotNull($notification);
        $this->assertContains($ticket->code, $notification->payload['ticket_codes'] ?? []);
    }

    /**
     * Test 6 — 15-minute scheduler does NOT re-dispatch transactional confirmation emails.
     */
    public function test_scheduler_does_not_redispatch_transactional_confirmations(): void
    {
        Mail::fake();

        $opportunity = Opportunity::create([
            'title' => 'Ushering Shift',
            'slug' => 'ushering-shift',
            'status' => 'open',
            'start_at' => now()->addDays(10), // Far in future, no reminder due
        ]);

        $signup = app(VolunteerSignupService::class)->registerSignup([
            'opportunity_id' => $opportunity->id,
            'name' => 'Scheduler Isolation',
            'email' => 'isolation@sfu.ca',
        ]);

        $initialLedgerCount = EventNotification::where('volunteering_signup_id', $signup->id)
            ->where('type', NotificationType::VmsSignupConfirmed->value)
            ->count();

        $this->assertEquals(1, $initialLedgerCount);

        // Execute scheduled reminders command
        Artisan::call('vms:send-reminders');

        $afterSchedulerCount = EventNotification::where('volunteering_signup_id', $signup->id)
            ->where('type', NotificationType::VmsSignupConfirmed->value)
            ->count();

        // Count of signup confirmation emails remains exactly 1
        $this->assertEquals(1, $afterSchedulerCount);
    }

    /**
     * Test 7 — No duplicate confirmation: one successful registration produces exactly one confirmation email.
     */
    public function test_one_successful_registration_produces_exactly_one_confirmation_email(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Single Confirmation Check',
            'slug' => 'single-confirm-check',
            'status' => 'open',
            'start_at' => now()->addDays(2),
        ]);

        $signup = app(VolunteerSignupService::class)->registerSignup([
            'opportunity_id' => $opportunity->id,
            'name' => 'Single Confirm',
            'email' => 'single@sfu.ca',
        ]);

        $confirmationsCount = EventNotification::where('volunteering_signup_id', $signup->id)
            ->where('type', NotificationType::VmsSignupConfirmed->value)
            ->count();

        $this->assertEquals(1, $confirmationsCount);
    }

    /**
     * Test 8 — Legitimate scheduled notifications remain scheduled and run via scheduler.
     */
    public function test_legitimate_scheduled_reminders_continue_to_use_scheduler(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Upcoming Shift Tomorrow',
            'slug' => 'upcoming-shift-tomorrow',
            'status' => 'open',
            'start_at' => now()->addHours(20), // Due within 24h reminder window
        ]);

        $shift = Shift::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Morning Shift',
            'start_at' => now()->addHours(20),
            'end_at' => now()->addHours(24),
            'status' => 'open',
        ]);

        $signup = Signup::create([
            'opportunity_id' => $opportunity->id,
            'shift_id' => $shift->id,
            'name' => 'Remind Me',
            'email' => 'remindme@sfu.ca',
            'status' => 'signed_up',
        ]);

        // Before running scheduler: no reminder in ledger
        $this->assertDatabaseMissing('ems_notifications', [
            'volunteering_signup_id' => $signup->id,
            'type' => NotificationType::VmsShiftReminder->value,
        ]);

        // Run scheduler command
        Artisan::call('vms:send-reminders');

        // After running scheduler: reminder is dispatched and recorded
        $this->assertDatabaseHas('ems_notifications', [
            'volunteering_signup_id' => $signup->id,
            'type' => NotificationType::VmsShiftReminder->value,
            'recipient_email' => 'remindme@sfu.ca',
        ]);
    }
}
