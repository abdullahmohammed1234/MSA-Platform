<?php

namespace Tests\Feature\Ems;

use App\Ems\Models\Event;
use App\Ems\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventVisibilityAndRemainingTicketsTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_lifecycle_and_registration_availability_states(): void
    {
        $event = new Event([
            'name' => 'Tech Workshop',
            'slug' => 'tech-workshop',
            'start_at' => now()->addDays(3),
            'end_at' => now()->addDays(3)->addHours(2),
            'capacity' => 50,
            'show_remaining_tickets' => true,
            'is_public' => true,
        ]);
        $event->status = \App\Ems\Enums\EventStatus::RegistrationOpen;
        $event->save();

        $this->assertEquals('UPCOMING', $event->lifecycle_state);
        $this->assertEquals('OPEN', $event->registration_availability);

        // Test show_remaining_tickets attribute
        $this->assertTrue($event->show_remaining_tickets);
    }

    public function test_attendee_csv_export_endpoint_authorizes_correctly(): void
    {
        \App\Models\Role::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $event = Event::create([
            'name' => 'Community Iftar',
            'slug' => 'community-iftar',
            'start_at' => now()->addDays(10),
            'capacity' => 100,
            'status' => \App\Ems\Enums\EventStatus::RegistrationOpen,
            'is_public' => true,
        ]);

        Registration::create([
            'reference' => 'REG-1001',
            'event_id' => $event->id,
            'attendee_name' => 'John Doe',
            'attendee_email' => 'john@example.com',
            'status' => 'confirmed',
            'quantity' => 1,
            'amount_due' => 0,
            'currency' => 'CAD',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->get("/api/v1/ems/events/{$event->uuid}/attendees/export-csv");

        $response->assertStatus(200)
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('Registration Number', $response->streamedContent());
        $this->assertStringContainsString('John Doe', $response->streamedContent());
    }
}
