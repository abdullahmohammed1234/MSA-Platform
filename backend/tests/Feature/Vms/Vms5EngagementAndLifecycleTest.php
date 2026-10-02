<?php

namespace Tests\Feature\Vms;

use App\Models\User;
use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Signup;
use App\Volunteering\Services\VmsNotificationDispatcher;
use App\Volunteering\Services\VolunteerSignupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Vms5EngagementAndLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private VolunteerSignupService $service;
    private VmsNotificationDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = app(VmsNotificationDispatcher::class);
        $this->service = app(VolunteerSignupService::class);
    }

    /** @test */
    public function valid_state_transitions_are_permitted(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'State Transition Event',
            'slug' => 'state-trans-' . uniqid(),
            'status' => 'open',
        ]);

        $signup = Signup::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Valid Transition Volunteer',
            'email' => 'validtrans@sfu.ca',
            'status' => 'signed_up',
        ]);

        // signed_up -> confirmed
        $signup = $this->service->updateStatus($signup, 'confirmed');
        $this->assertEquals('confirmed', $signup->status);

        // confirmed -> completed
        $signup = $this->service->updateStatus($signup, 'completed');
        $this->assertEquals('completed', $signup->status);
    }

    /** @test */
    public function invalid_state_transitions_are_rejected(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Invalid Transition Event',
            'slug' => 'invalid-trans-' . uniqid(),
            'status' => 'open',
        ]);

        $signup = Signup::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Cancelled Volunteer',
            'email' => 'cancelledvol@sfu.ca',
            'status' => 'cancelled',
        ]);

        // cancelled -> completed must fail
        $this->expectException(ValidationException::class);
        $this->service->updateStatus($signup, 'completed');
    }

    /** @test */
    public function completed_state_cannot_transition_back_to_confirmed(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Completed Lock Event',
            'slug' => 'completed-lock-' . uniqid(),
            'status' => 'open',
        ]);

        $signup = Signup::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Completed Volunteer',
            'email' => 'completedvol@sfu.ca',
            'status' => 'completed',
        ]);

        // completed -> confirmed must fail
        $this->expectException(ValidationException::class);
        $this->service->updateStatus($signup, 'confirmed');
    }

    /** @test */
    public function waitlisted_signup_cannot_directly_jump_to_completed(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Waitlist Jump Event',
            'slug' => 'waitlist-jump-' . uniqid(),
            'status' => 'open',
        ]);

        $signup = Signup::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Waitlisted Volunteer',
            'email' => 'waitlistvol@sfu.ca',
            'status' => 'waitlisted',
        ]);

        // waitlisted -> completed must fail
        $this->expectException(ValidationException::class);
        $this->service->updateStatus($signup, 'completed');
    }

    /** @test */
    public function present_attendance_elevates_signed_up_to_confirmed_and_records_attended_at(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Attendance Elevate Event',
            'slug' => 'attendance-elevate-' . uniqid(),
            'status' => 'open',
        ]);

        $signup = Signup::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Present Volunteer',
            'email' => 'presentvol@sfu.ca',
            'status' => 'signed_up',
            'attendance_status' => 'not_marked',
        ]);

        $updated = $this->service->updateAttendance($signup, 'present');

        $this->assertEquals('present', $updated->attendance_status);
        $this->assertEquals('confirmed', $updated->status);
        $this->assertNotNull($updated->attended_at);
    }

    /** @test */
    public function absent_attendance_transitions_signed_up_to_no_show(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'No Show Event',
            'slug' => 'no-show-' . uniqid(),
            'status' => 'open',
        ]);

        $signup = Signup::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Absent Volunteer',
            'email' => 'absentvol@sfu.ca',
            'status' => 'signed_up',
            'attendance_status' => 'not_marked',
        ]);

        $updated = $this->service->updateAttendance($signup, 'absent');

        $this->assertEquals('absent', $updated->attendance_status);
        $this->assertEquals('no_show', $updated->status);
    }

    /** @test */
    public function attendance_cannot_be_marked_for_cancelled_signups(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Cancelled Attendance Event',
            'slug' => 'cancelled-att-' . uniqid(),
            'status' => 'open',
        ]);

        $signup = Signup::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Cancelled Volunteer',
            'email' => 'cancelledatt@sfu.ca',
            'status' => 'cancelled',
            'attendance_status' => 'not_marked',
        ]);

        $this->expectException(ValidationException::class);
        $this->service->updateAttendance($signup, 'present');
    }

    /** @test */
    public function post_event_followup_notification_is_idempotent(): void
    {
        $opportunity = Opportunity::create([
            'title' => 'Followup Notification Event',
            'slug' => 'followup-notif-' . uniqid(),
            'status' => 'open',
        ]);

        $signup = Signup::create([
            'opportunity_id' => $opportunity->id,
            'name' => 'Followup Volunteer',
            'email' => 'followup@sfu.ca',
            'status' => 'completed',
        ]);

        $notification1 = $this->dispatcher->notifyPostEventFollowup($signup);
        $notification2 = $this->dispatcher->notifyPostEventFollowup($signup);

        $this->assertNotNull($notification1);
        $this->assertEquals($notification1->id, $notification2->id);

        $this->assertDatabaseHas('ems_notifications', [
            'volunteering_signup_id' => $signup->id,
            'type' => 'vms_post_event_followup',
            'idempotency_key' => "vms_post_event_followup:{$signup->id}",
        ]);
    }

    /** @test */
    public function volunteer_history_hides_admin_notes_and_processed_by(): void
    {
        $admin = User::factory()->create(['email' => 'admin@sfu.ca']);
        $volunteer = User::factory()->create(['email' => 'volprivacy@sfu.ca']);

        $opportunity = Opportunity::create([
            'title' => 'Privacy Protection Event',
            'slug' => 'privacy-prot-' . uniqid(),
            'status' => 'open',
        ]);

        $signup = Signup::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $volunteer->id,
            'name' => 'Privacy Volunteer',
            'email' => 'volprivacy@sfu.ca',
            'status' => 'completed',
            'attendance_status' => 'present',
            'admin_notes' => 'INTERNAL SECRET ADMIN NOTE',
            'processed_by' => $admin->id,
        ]);

        $this->actingAs($volunteer, 'sanctum');

        $response = $this->getJson('/api/v1/volunteering/my-history');

        $response->assertStatus(200);

        $signupJson = $response->json('data.signups.0');
        $this->assertArrayNotHasKey('admin_notes', $signupJson);
        $this->assertArrayNotHasKey('processed_by', $signupJson);
    }
}
