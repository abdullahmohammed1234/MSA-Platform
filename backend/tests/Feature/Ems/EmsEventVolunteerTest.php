<?php

namespace Tests\Feature\Ems;

use App\Ems\Models\Event;
use App\Ems\Models\EventVolunteer;
use App\Ems\Support\EmsRoles;

class EmsEventVolunteerTest extends EmsTestCase
{
    public function test_attendee_can_submit_public_volunteer_application(): void
    {
        $event = Event::factory()->create([
            'name' => 'Annual Gala 2026',
            'slug' => 'annual-gala-2026',
            'status' => 'published',
            'is_public' => true,
        ]);

        $response = $this->postJson("/api/v1/ems/public/events/{$event->slug}/volunteers", [
            'name' => 'Aisha Khan',
            'email' => 'aisha@example.com',
            'phone' => '778-555-0199',
            'interests' => ['Logistics', 'Registration Desk'],
            'availability' => 'Full Day (8am - 6pm)',
            'experience' => 'Managed check-ins at 3 previous events.',
            'notes' => 'Excited to help out!',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.volunteer.email', 'aisha@example.com')
            ->assertJsonPath('data.volunteer.status', 'pending');

        $this->assertDatabaseHas('ems_event_volunteers', [
            'event_id' => $event->id,
            'name' => 'Aisha Khan',
            'email' => 'aisha@example.com',
            'status' => 'pending',
        ]);
    }

    public function test_duplicate_pending_volunteer_application_is_prevented(): void
    {
        $event = Event::factory()->create(['slug' => 'test-event-vol-dup']);

        EventVolunteer::create([
            'event_id' => $event->id,
            'name' => 'Bilal Ahmed',
            'email' => 'bilal@example.com',
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/v1/ems/public/events/{$event->slug}/volunteers", [
            'name' => 'Bilal Ahmed',
            'email' => 'bilal@example.com',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_list_and_update_volunteer_status(): void
    {
        $admin = $this->emsUser(EmsRoles::SUPER_ADMIN);
        $event = Event::factory()->create();

        $v1 = EventVolunteer::create([
            'event_id' => $event->id,
            'name' => 'Volunteer One',
            'email' => 'v1@example.com',
            'status' => 'pending',
        ]);

        $v2 = EventVolunteer::create([
            'event_id' => $event->id,
            'name' => 'Volunteer Two',
            'email' => 'v2@example.com',
            'status' => 'approved',
        ]);

        // List
        $listResp = $this->actingAsEms($admin)
            ->getJson("/api/v1/ems/events/{$event->id}/volunteers");

        $listResp->assertStatus(200)
            ->assertJsonPath('data.metrics.total', 2)
            ->assertJsonPath('data.metrics.pending', 1)
            ->assertJsonPath('data.metrics.approved', 1);

        // Update status
        $updateResp = $this->actingAsEms($admin)
            ->patchJson("/api/v1/ems/events/{$event->id}/volunteers/{$v1->id}/status", [
                'status' => 'approved',
                'admin_notes' => 'Assigned to Registration Desk A.',
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.admin_notes', 'Assigned to Registration Desk A.');

        $this->assertDatabaseHas('ems_event_volunteers', [
            'id' => $v1->id,
            'status' => 'approved',
            'admin_notes' => 'Assigned to Registration Desk A.',
        ]);
    }

    public function test_admin_can_export_volunteers_csv(): void
    {
        $admin = $this->emsUser(EmsRoles::SUPER_ADMIN);
        $event = Event::factory()->create();

        EventVolunteer::create([
            'event_id' => $event->id,
            'name' => 'CSV Volunteer',
            'email' => 'csv@example.com',
            'status' => 'pending',
        ]);

        $response = $this->actingAsEms($admin)
            ->get("/api/v1/ems/events/{$event->id}/volunteers/export");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
