<?php

namespace Tests\Feature;

use App\Enums\VolunteerRegistrationStatus;
use App\Ems\Support\EmsRoles;
use App\Mail\VolunteerApplication;
use App\Models\User;
use App\Models\VolunteerRegistration;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Ems\EmsTestCase;

class VolunteerRegistrationTest extends EmsTestCase
{
    private function adminUser(): User
    {
        return $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
    }

    private function attendeeUser(): User
    {
        return $this->emsUser(EmsRoles::ATTENDEE);
    }

    public function test_public_submission_persists_registration_to_database(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/website/volunteer', [
            'name' => 'Ahmad Volunteer',
            'email' => 'ahmad@sfu.ca',
            'student_number' => '301987654',
            'department' => 'Events & Logistics',
            'interests' => 'I want to help organize Friday Jumuah and community dinners.',
            'experience' => 'Previous high school MSA vice president.',
        ]);

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.name', 'Ahmad Volunteer');
        $response->assertJsonPath('data.email', 'ahmad@sfu.ca');

        $this->assertDatabaseHas('volunteer_registrations', [
            'name' => 'Ahmad Volunteer',
            'email' => 'ahmad@sfu.ca',
            'student_number' => '301987654',
            'department' => 'Events & Logistics',
            'status' => VolunteerRegistrationStatus::New->value,
        ]);

        Mail::assertSent(VolunteerApplication::class, function ($mail) {
            return $mail->hasTo(config('website.contact_recipient'));
        });
    }

    public function test_email_failure_does_not_rollback_database_record(): void
    {
        Mail::shouldReceive('to->send')
            ->once()
            ->andThrow(new \Exception('SMTP Connection Timed Out'));

        $response = $this->postJson('/api/v1/website/volunteer', [
            'name' => 'Fatima Volunteer',
            'email' => 'fatima@sfu.ca',
            'student_number' => '301111222',
            'department' => 'Media & Comms',
            'interests' => 'Graphic design and social media management.',
        ]);

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('volunteer_registrations', [
            'name' => 'Fatima Volunteer',
            'email' => 'fatima@sfu.ca',
            'student_number' => '301111222',
            'status' => VolunteerRegistrationStatus::New->value,
        ]);
    }

    public function test_validation_blocks_non_sfu_email_and_invalid_student_number(): void
    {
        $response = $this->postJson('/api/v1/website/volunteer', [
            'name' => 'Invalid User',
            'email' => 'user@gmail.com',
            'student_number' => '12345',
            'department' => 'General Inquiries',
            'interests' => 'General volunteering.',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'student_number']);
    }

    public function test_unauthenticated_and_unauthorized_users_cannot_access_admin_endpoints(): void
    {
        $registration = VolunteerRegistration::factory()->create([
            'name' => 'Secret Applicant',
            'email' => 'applicant@sfu.ca',
            'student_number' => '301234567',
            'department' => 'General Inquiries',
            'interests' => 'Secret interests',
            'admin_notes' => 'Top secret interview notes',
        ]);

        // Unauthenticated
        $this->getJson($this->url('volunteering-registrars'))
            ->assertUnauthorized();

        $this->getJson($this->url("volunteering-registrars/{$registration->uuid}"))
            ->assertUnauthorized();

        // Unauthorized authenticated user (Attendee role without permission)
        $attendee = $this->attendeeUser();

        $this->actingAsEms($attendee)
            ->getJson($this->url('volunteering-registrars'))
            ->assertForbidden();

        $this->actingAsEms($attendee)
            ->getJson($this->url("volunteering-registrars/{$registration->uuid}"))
            ->assertForbidden();
    }

    public function test_authorized_admin_can_list_and_filter_volunteer_registrations(): void
    {
        $admin = $this->adminUser();

        $reg1 = VolunteerRegistration::factory()->create([
            'name' => 'Zayd Smith',
            'email' => 'zayd@sfu.ca',
            'department' => 'Media & Comms',
            'status' => VolunteerRegistrationStatus::New,
        ]);

        $reg2 = VolunteerRegistration::factory()->create([
            'name' => 'Bilal Khan',
            'email' => 'bilal@sfu.ca',
            'department' => 'Events & Logistics',
            'status' => VolunteerRegistrationStatus::Contacted,
        ]);

        // List all
        $response = $this->actingAsEms($admin)
            ->getJson($this->url('volunteering-registrars'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        // Filter by status
        $filterResponse = $this->actingAsEms($admin)
            ->getJson($this->url('volunteering-registrars?status=contacted'));

        $filterResponse->assertOk();
        $filterResponse->assertJsonCount(1, 'data');
        $filterResponse->assertJsonPath('data.0.uuid', $reg2->uuid);

        // Search by query
        $searchResponse = $this->actingAsEms($admin)
            ->getJson($this->url('volunteering-registrars?search=Zayd'));

        $searchResponse->assertOk();
        $searchResponse->assertJsonCount(1, 'data');
        $searchResponse->assertJsonPath('data.0.uuid', $reg1->uuid);
    }

    public function test_authorized_admin_can_view_registration_details_and_notes(): void
    {
        $admin = $this->adminUser();

        $registration = VolunteerRegistration::factory()->create([
            'name' => 'Yusuf Ali',
            'email' => 'yusuf@sfu.ca',
            'student_number' => '301999888',
            'department' => 'Education & Outreach',
            'interests' => 'Teaching basic Quranic Arabic.',
            'admin_notes' => 'Interview scheduled for Friday after Jumuah.',
        ]);

        $response = $this->actingAsEms($admin)
            ->getJson($this->url("volunteering-registrars/{$registration->uuid}"));

        $response->assertOk();
        $response->assertJsonPath('data.uuid', $registration->uuid);
        $response->assertJsonPath('data.name', 'Yusuf Ali');
        $response->assertJsonPath('data.admin_notes', 'Interview scheduled for Friday after Jumuah.');
    }

    public function test_authorized_admin_can_update_status_notes_and_assignment(): void
    {
        $admin = $this->adminUser();
        $assignee = User::factory()->create(['name' => 'Coordinator Admin']);

        $registration = VolunteerRegistration::factory()->create([
            'name' => 'Tariq Vance',
            'email' => 'tariq@sfu.ca',
            'status' => VolunteerRegistrationStatus::New,
            'contacted_at' => null,
        ]);

        $response = $this->actingAsEms($admin)
            ->putJson($this->url("volunteering-registrars/{$registration->uuid}"), [
                'status' => 'contacted',
                'admin_notes' => 'Sent initial Discord invite link.',
                'assigned_to' => $assignee->id,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'contacted');
        $response->assertJsonPath('data.admin_notes', 'Sent initial Discord invite link.');
        $response->assertJsonPath('data.assigned_to', $assignee->id);

        $this->assertDatabaseHas('volunteer_registrations', [
            'id' => $registration->id,
            'status' => 'contacted',
            'admin_notes' => 'Sent initial Discord invite link.',
            'assigned_to' => $assignee->id,
        ]);

        $this->assertNotNull($registration->fresh()->contacted_at);
    }

    public function test_authorized_admin_can_archive_volunteer_registration(): void
    {
        $admin = $this->adminUser();

        $registration = VolunteerRegistration::factory()->create([
            'name' => 'Archived User',
            'email' => 'archived@sfu.ca',
        ]);

        $response = $this->actingAsEms($admin)
            ->deleteJson($this->url("volunteering-registrars/{$registration->uuid}"));

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertSoftDeleted('volunteer_registrations', [
            'id' => $registration->id,
        ]);
    }
}
