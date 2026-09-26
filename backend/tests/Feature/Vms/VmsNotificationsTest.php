<?php

namespace Tests\Feature\Vms;

use App\Ems\Models\Event;
use App\Ems\Models\EventNotification;
use App\Models\User;
use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Shift;
use App\Volunteering\Models\Signup;
use App\Volunteering\Models\Team;
use App\Volunteering\Services\VolunteerOpportunityService;
use App\Volunteering\Services\VolunteerSignupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VmsNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ems.notifications.enabled', true);
        Config::set('vms.notifications.enabled', true);
    }

    public function test_signup_creates_confirmation_and_admin_notification_in_ledger(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Jumuah Setup Team',
            'slug' => 'jumuah-setup-team',
            'status' => 'open',
            'start_at' => now()->addDays(2),
        ]);

        $signupService = app(VolunteerSignupService::class);
        $signup = $signupService->registerSignup([
            'opportunity_id' => $opportunity->id,
            'name' => 'Ahmad Volunteer',
            'email' => 'ahmad@sfu.ca',
            'phone' => '778-123-4567',
        ]);

        $this->assertEquals('signed_up', $signup->status);

        // Check confirmation email ledger entry
        $this->assertDatabaseHas('ems_notifications', [
            'volunteering_signup_id' => $signup->id,
            'type' => 'vms_signup_confirmed',
            'recipient_email' => 'ahmad@sfu.ca',
            'idempotency_key' => "vms_signup_confirmed:{$signup->id}",
        ]);

        // Check admin email ledger entry
        $this->assertDatabaseHas('ems_notifications', [
            'volunteering_signup_id' => $signup->id,
            'type' => 'vms_admin_signup_received',
        ]);
    }

    public function test_full_capacity_signup_joins_waitlist_and_promotion_notifies(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Ramadan Catering',
            'slug' => 'ramadan-catering',
            'status' => 'open',
            'capacity' => 1,
        ]);

        $signupService = app(VolunteerSignupService::class);

        // First volunteer takes capacity
        $vol1 = $signupService->registerSignup([
            'opportunity_id' => $opportunity->id,
            'name' => 'Vol One',
            'email' => 'vol1@sfu.ca',
        ]);
        $this->assertEquals('signed_up', $vol1->status);

        // Second volunteer joins waitlist
        $vol2 = $signupService->registerSignup([
            'opportunity_id' => $opportunity->id,
            'name' => 'Vol Two',
            'email' => 'vol2@sfu.ca',
            'join_waitlist' => true,
        ]);

        $this->assertEquals('waitlisted', $vol2->status);

        $this->assertDatabaseHas('ems_notifications', [
            'volunteering_signup_id' => $vol2->id,
            'type' => 'vms_waitlist_joined',
            'recipient_email' => 'vol2@sfu.ca',
        ]);

        // Vol 1 cancels -> Vol 2 promoted automatically
        $signupService->cancelSignup($vol1);

        $this->assertDatabaseHas('ems_notifications', [
            'volunteering_signup_id' => $vol1->id,
            'type' => 'vms_signup_cancelled',
        ]);

        $vol2Fresh = Signup::find($vol2->id);
        $this->assertEquals('signed_up', $vol2Fresh->status);

        $this->assertDatabaseHas('ems_notifications', [
            'volunteering_signup_id' => $vol2->id,
            'type' => 'vms_waitlist_promoted',
            'recipient_email' => 'vol2@sfu.ca',
        ]);
    }

    public function test_updating_shift_details_notifies_affected_volunteers(): void
    {
        $admin = User::factory()->create();

        $opportunity = Opportunity::create([
            'title' => 'Campus Cleanup',
            'slug' => 'campus-cleanup',
            'status' => 'open',
            'location' => 'SUB Room 2000',
            'start_at' => now()->addDays(3),
        ]);

        $signupService = app(VolunteerSignupService::class);
        $signup = $signupService->registerSignup([
            'opportunity_id' => $opportunity->id,
            'name' => 'Sara Helper',
            'email' => 'sara@sfu.ca',
        ]);

        $oppService = app(VolunteerOpportunityService::class);
        $oppService->updateOpportunity($opportunity, [
            'location' => 'AQ 3000',
            'start_at' => now()->addDays(4)->toIso8601String(),
        ], $admin->id);

        $this->assertDatabaseHas('ems_notifications', [
            'volunteering_signup_id' => $signup->id,
            'type' => 'vms_shift_updated',
            'recipient_email' => 'sara@sfu.ca',
        ]);
    }

    public function test_cancelling_opportunity_notifies_active_volunteers(): void
    {
        $admin = User::factory()->create();

        $opportunity = Opportunity::create([
            'title' => 'Career Workshop',
            'slug' => 'career-workshop',
            'status' => 'open',
            'start_at' => now()->addDays(3),
        ]);

        $signupService = app(VolunteerSignupService::class);
        $signup = $signupService->registerSignup([
            'opportunity_id' => $opportunity->id,
            'name' => 'Tariq Student',
            'email' => 'tariq@sfu.ca',
        ]);

        $oppService = app(VolunteerOpportunityService::class);
        $oppService->updateOpportunity($opportunity, [
            'status' => 'cancelled',
            'cancellation_reason' => 'Speaker unavailable',
        ], $admin->id);

        $this->assertDatabaseHas('ems_notifications', [
            'volunteering_signup_id' => $signup->id,
            'type' => 'vms_opportunity_cancelled',
            'recipient_email' => 'tariq@sfu.ca',
        ]);
    }

    public function test_send_reminders_command_is_idempotent(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Friday Prayer Ushering',
            'slug' => 'friday-prayer-ushering',
            'status' => 'open',
            'start_at' => now()->addHours(20), // Within 24h window
        ]);

        $shift = Shift::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Main Shift',
            'start_at' => now()->addHours(20),
            'end_at' => now()->addHours(22),
            'status' => 'open',
        ]);

        $signup = Signup::create([
            'opportunity_id' => $opportunity->id,
            'shift_id' => $shift->id,
            'name' => 'Usher One',
            'email' => 'usher1@sfu.ca',
            'status' => 'signed_up',
        ]);

        // First execution dispatches reminder
        Artisan::call('vms:send-reminders');

        $initialCount = EventNotification::where('volunteering_signup_id', $signup->id)
            ->where('type', 'vms_shift_reminder')
            ->count();

        $this->assertEquals(1, $initialCount);

        // Second execution does NOT create duplicate notification
        Artisan::call('vms:send-reminders');

        $secondCount = EventNotification::where('volunteering_signup_id', $signup->id)
            ->where('type', 'vms_shift_reminder')
            ->count();

        $this->assertEquals(1, $secondCount);
    }

    public function test_timezone_formatting_respects_event_timezone(): void
    {
        $event = Event::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Vancouver Conference',
            'slug' => 'vancouver-conf',
            'status' => 'published',
            'timezone' => 'America/Vancouver',
            'start_at' => '2026-10-15 18:30:00', // UTC 18:30 = 11:30 AM PDT
            'end_at' => '2026-10-15 21:00:00',
        ]);

        $opportunity = Opportunity::create([
            'title' => 'Conference Setup',
            'slug' => 'conference-setup',
            'event_id' => $event->id,
            'status' => 'open',
            'start_at' => '2026-10-15 18:30:00',
        ]);

        $signupService = app(VolunteerSignupService::class);
        $signup = $signupService->registerSignup([
            'opportunity_id' => $opportunity->id,
            'name' => 'Timezone Tester',
            'email' => 'tz@sfu.ca',
        ]);

        $notification = EventNotification::where('volunteering_signup_id', $signup->id)->first();
        $this->assertNotNull($notification);

        // Body or payload should contain formatted local time (11:30 AM PDT) instead of raw UTC
        $bodyText = $notification->body;
        $this->assertStringContainsString('11:30 AM', $bodyText);
    }

    public function test_ems_event_cancellation_triggers_vms_opportunity_cancellation(): void
    {
        $event = Event::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Annual MSA Symposium',
            'slug' => 'annual-symposium',
            'status' => 'published',
            'start_at' => now()->addDays(5),
        ]);

        $opportunity = Opportunity::create([
            'title' => 'Symposium Registration Desk',
            'slug' => 'symposium-desk',
            'event_id' => $event->id,
            'status' => 'open',
            'start_at' => now()->addDays(5),
        ]);

        $signupService = app(VolunteerSignupService::class);
        $signup = $signupService->registerSignup([
            'opportunity_id' => $opportunity->id,
            'name' => 'Event Volunteer',
            'email' => 'eventvol@sfu.ca',
        ]);

        $cancellationService = app(\App\Ems\Services\Notifications\EventCancellationService::class);
        $cancellationService->handleCancelled($event, 'Inclement weather condition');

        $opportunityFresh = Opportunity::find($opportunity->id);
        $this->assertEquals('cancelled', $opportunityFresh->status);

        $this->assertDatabaseHas('ems_notifications', [
            'volunteering_signup_id' => $signup->id,
            'type' => 'vms_opportunity_cancelled',
            'recipient_email' => 'eventvol@sfu.ca',
        ]);
    }
}
