<?php

namespace Tests\Feature\Ems;

use App\Ems\Models\Event;
use App\Ems\Models\Registration;
use App\Ems\Models\EventFeedback;
use App\Ems\Enums\RegistrationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmsEventFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendee_can_submit_public_event_feedback(): void
    {
        $event = Event::factory()->create(['slug' => 'test-community-gala']);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed->value,
            'attendee_email' => 'attendee@sfu.ca',
        ]);

        $response = $this->postJson("/api/v1/ems/public/events/{$event->slug}/feedback", [
            'overall_rating' => 5,
            'program_rating' => 4,
            'organization_rating' => 5,
            'venue_rating' => 5,
            'text_feedback' => 'Great organization and inspiring talks!',
            'email' => 'attendee@sfu.ca',
            'registration_uuid' => $registration->uuid,
            'is_anonymous' => false,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('ems_event_feedbacks', [
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'overall_rating' => 5,
        ]);
    }

    public function test_duplicate_feedback_submission_is_prevented(): void
    {
        $event = Event::factory()->create(['slug' => 'duplicate-feedback-test']);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed->value,
            'attendee_email' => 'dup@sfu.ca',
        ]);

        $payload = [
            'overall_rating' => 4,
            'program_rating' => 4,
            'organization_rating' => 4,
            'venue_rating' => 4,
            'email' => 'dup@sfu.ca',
            'registration_uuid' => $registration->uuid,
        ];

        $this->postJson("/api/v1/ems/public/events/{$event->slug}/feedback", $payload)->assertStatus(201);

        // Second submission should fail with 409
        $this->postJson("/api/v1/ems/public/events/{$event->slug}/feedback", $payload)->assertStatus(409);
    }
}
