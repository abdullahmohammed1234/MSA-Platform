<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\WaitlistStatus;
use App\Ems\Models\Event;
use App\Ems\Models\Registration;
use App\Ems\Models\TicketType;
use App\Ems\Models\WaitlistEntry;
use App\Ems\Services\WaitlistService;
use Illuminate\Support\Str;

class SoldOutWaitlistRegistrationTest extends EmsTestCase
{
    private function publicUrl(string $path = ''): string
    {
        return '/api/v1/ems/public/' . ltrim($path, '/');
    }

    public function test_open_event_allows_normal_registration_and_serializes_open_state(): void
    {
        $category = $this->category(['is_active' => true]);
        $event = Event::factory()->create([
            'category_id' => $category->id,
            'name' => 'Open Workshop',
            'slug' => 'open-workshop-' . Str::lower(Str::random(6)),
            'capacity' => 10,
            'waitlist_enabled' => true,
            'status' => EventStatus::RegistrationOpen,
            'is_public' => true,
            'start_at' => now()->addDays(5),
            'end_at' => now()->addDays(5)->addHours(2),
        ]);

        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'price' => 0,
            'quantity' => 10,
            'is_active' => true,
        ]);

        // 1. Verify Public API payload serialization
        $response = $this->getJson($this->publicUrl("events/{$event->slug}"));
        $this->assertSuccessEnvelope($response);
        $response->assertJsonPath('data.registration_availability', 'OPEN');
        $response->assertJsonPath('data.lifecycle_state', 'UPCOMING');
        $response->assertJsonPath('data.is_accepting_registrations', true);
        $response->assertJsonPath('data.is_accepting_waitlist', false);
        $response->assertJsonPath('data.is_sold_out', false);
        $response->assertJsonPath('data.registration_label', 'Registration Open');

        // 2. Normal registration succeeds
        $regResponse = $this->postJson($this->publicUrl("events/{$event->slug}/register"), [
            'first_name' => 'Regular',
            'last_name' => 'Attendee',
            'email' => 'attendee@example.com',
            'quantity' => 1,
            'ticket_type_id' => $ticketType->uuid,
        ]);
        $this->assertSuccessEnvelope($regResponse);

        $this->assertEquals(1, $event->fresh()->occupiedSeats());

        // 3. Waitlist join is rejected while capacity is available
        $waitlistResponse = $this->postJson($this->publicUrl("events/{$event->slug}/waitlist"), [
            'first_name' => 'Waitlist',
            'last_name' => 'Applicant',
            'email' => 'waitlist@example.com',
            'quantity' => 1,
            'ticket_type_id' => $ticketType->uuid,
        ]);
        $waitlistResponse->assertStatus(409);
        $waitlistResponse->assertJsonPath('success', false);
        $this->assertStringContainsString('Capacity is available', $waitlistResponse->json('message'));
    }

    public function test_sold_out_event_with_waitlist_enabled_allows_waitlist_registration_and_preserves_capacity(): void
    {
        $category = $this->category(['is_active' => true]);
        $event = Event::factory()->create([
            'category_id' => $category->id,
            'name' => 'Popular Gala',
            'slug' => 'popular-gala-' . Str::lower(Str::random(6)),
            'capacity' => 1,
            'waitlist_enabled' => true,
            'status' => EventStatus::RegistrationOpen,
            'is_public' => true,
            'start_at' => now()->addDays(7),
            'end_at' => now()->addDays(7)->addHours(3),
        ]);

        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'VIP Access',
            'price' => 0,
            'quantity' => 1,
            'is_active' => true,
        ]);

        // 1 confirmed registration fills the capacity to 100%
        Registration::create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'attendee_name' => 'Confirmed Guest',
            'attendee_email' => 'confirmed@example.com',
            'status' => RegistrationStatus::Confirmed,
            'quantity' => 1,
            'reference' => 'REG-0001',
            'registered_at' => now(),
        ]);

        $this->assertTrue($event->fresh()->isSoldOut());

        // Verify public API returns SOLD_OUT availability and indicates waitlist is open
        $detailResponse = $this->getJson($this->publicUrl("events/{$event->slug}"));
        $this->assertSuccessEnvelope($detailResponse);
        $detailResponse->assertJsonPath('data.registration_availability', 'SOLD_OUT');
        $detailResponse->assertJsonPath('data.is_sold_out', true);
        $detailResponse->assertJsonPath('data.waitlist_enabled', true);
        $detailResponse->assertJsonPath('data.is_accepting_waitlist', true);
        $detailResponse->assertJsonPath('data.registration_label', 'Join Waitlist');

        // Normal registration MUST be rejected
        $normalReg = $this->postJson($this->publicUrl("events/{$event->slug}/register"), [
            'first_name' => 'Late',
            'last_name' => 'Person',
            'email' => 'late@example.com',
            'quantity' => 1,
            'ticket_type_id' => $ticketType->uuid,
        ]);
        $normalReg->assertStatus(409);
        $this->assertStringContainsString('sold out', strtolower($normalReg->json('message')));

        // Waitlist registration MUST succeed
        $waitlistReg = $this->postJson($this->publicUrl("events/{$event->slug}/waitlist"), [
            'first_name' => 'Hopeful',
            'last_name' => 'Waitlister',
            'email' => 'waitlist@example.com',
            'quantity' => 1,
            'ticket_type_id' => $ticketType->uuid,
        ]);
        $this->assertSuccessEnvelope($waitlistReg);
        $waitlistReg->assertJsonPath('data.position', 1);

        // Strict capacity invariant check: Confirmed capacity MUST remain 1
        $this->assertEquals(1, $event->fresh()->occupiedSeats());
        $this->assertEquals(0, $event->fresh()->remainingCapacity());

        $waitlistEntry = WaitlistEntry::where('event_id', $event->id)->firstOrFail();
        $this->assertEquals('waitlist@example.com', $waitlistEntry->attendee_email);
        $this->assertEquals(WaitlistStatus::Waiting, $waitlistEntry->status);
        $this->assertEquals(1, $waitlistEntry->position);

        // Registration model invariant
        $reg = $waitlistEntry->registration;
        $this->assertNotNull($reg);
        $this->assertEquals(RegistrationStatus::Waitlisted, $reg->status);
        $this->assertEquals(0, $reg->amount_due);
        $this->assertEquals(1, $reg->waitlist_position);
    }

    public function test_sold_out_event_with_waitlist_disabled_rejects_waitlist_and_shows_sold_out(): void
    {
        $category = $this->category(['is_active' => true]);
        $event = Event::factory()->create([
            'category_id' => $category->id,
            'name' => 'Sold Out Exclusive',
            'slug' => 'sold-out-no-wl-' . Str::lower(Str::random(6)),
            'capacity' => 1,
            'waitlist_enabled' => false,
            'status' => EventStatus::RegistrationOpen,
            'is_public' => true,
        ]);

        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'price' => 0,
            'quantity' => 1,
            'is_active' => true,
        ]);

        Registration::create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'attendee_name' => 'First Guest',
            'attendee_email' => 'first@example.com',
            'status' => RegistrationStatus::Confirmed,
            'quantity' => 1,
            'reference' => 'REG-FIRST',
            'registered_at' => now(),
        ]);

        // 1. Verify Public API serialization
        $response = $this->getJson($this->publicUrl("events/{$event->slug}"));
        $this->assertSuccessEnvelope($response);
        $response->assertJsonPath('data.registration_availability', 'SOLD_OUT');
        $response->assertJsonPath('data.is_sold_out', true);
        $response->assertJsonPath('data.waitlist_enabled', false);
        $response->assertJsonPath('data.is_accepting_waitlist', false);
        $response->assertJsonPath('data.registration_label', 'Sold Out');

        // 2. Joining waitlist MUST fail
        $waitlistResponse = $this->postJson($this->publicUrl("events/{$event->slug}/waitlist"), [
            'first_name' => 'Waitlist',
            'last_name' => 'Denied',
            'email' => 'denied@example.com',
            'quantity' => 1,
        ]);
        $waitlistResponse->assertStatus(409);
        $this->assertStringContainsString('not enabled', $waitlistResponse->json('message'));
    }

    public function test_closed_event_rejects_registration_and_waitlist(): void
    {
        $category = $this->category(['is_active' => true]);
        $event = Event::factory()->create([
            'category_id' => $category->id,
            'name' => 'Closed Event',
            'slug' => 'closed-event-' . Str::lower(Str::random(6)),
            'capacity' => 10,
            'waitlist_enabled' => true,
            'status' => EventStatus::RegistrationClosed,
            'is_public' => true,
        ]);

        $response = $this->getJson($this->publicUrl("events/{$event->slug}"));
        $this->assertSuccessEnvelope($response);
        $response->assertJsonPath('data.registration_availability', 'CLOSED');
        $response->assertJsonPath('data.is_accepting_registrations', false);
        $response->assertJsonPath('data.is_accepting_waitlist', false);
        $response->assertJsonPath('data.registration_label', 'Registration Closed');

        $regResponse = $this->postJson($this->publicUrl("events/{$event->slug}/register"), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);
        $regResponse->assertStatus(409);

        $wlResponse = $this->postJson($this->publicUrl("events/{$event->slug}/waitlist"), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);
        $wlResponse->assertStatus(409);
        $this->assertStringContainsString('closed', strtolower($wlResponse->json('message')));
    }

    public function test_ended_event_rejects_registration_and_waitlist(): void
    {
        $category = $this->category(['is_active' => true]);
        $event = Event::factory()->create([
            'category_id' => $category->id,
            'name' => 'Ended Event',
            'slug' => 'ended-event-' . Str::lower(Str::random(6)),
            'capacity' => 1,
            'waitlist_enabled' => true,
            'status' => EventStatus::Live,
            'is_public' => true,
            'start_at' => now()->subDays(3),
            'end_at' => now()->subDays(2),
        ]);

        $response = $this->getJson($this->publicUrl("events/{$event->slug}"));
        $this->assertSuccessEnvelope($response);
        $response->assertJsonPath('data.lifecycle_state', 'ENDED');
        $response->assertJsonPath('data.registration_availability', 'CLOSED');
        $response->assertJsonPath('data.is_accepting_registrations', false);
        $response->assertJsonPath('data.is_accepting_waitlist', false);
        $response->assertJsonPath('data.registration_label', 'Completed');

        $wlResponse = $this->postJson($this->publicUrl("events/{$event->slug}/waitlist"), [
            'first_name' => 'Late',
            'last_name' => 'Arrival',
            'email' => 'late@example.com',
        ]);
        $wlResponse->assertStatus(409);
        $this->assertStringContainsString('concluded', strtolower($wlResponse->json('message')));
    }

    public function test_duplicate_registration_protection_on_waitlist(): void
    {
        $category = $this->category(['is_active' => true]);
        $event = Event::factory()->create([
            'category_id' => $category->id,
            'name' => 'Duplicate Guard Event',
            'slug' => 'dup-guard-' . Str::lower(Str::random(6)),
            'capacity' => 1,
            'waitlist_enabled' => true,
            'status' => EventStatus::RegistrationOpen,
            'is_public' => true,
        ]);

        Registration::create([
            'event_id' => $event->id,
            'attendee_name' => 'Existing Confirmed',
            'attendee_email' => 'confirmed@example.com',
            'status' => RegistrationStatus::Confirmed,
            'quantity' => 1,
            'reference' => 'REG-CONFIRMED',
            'registered_at' => now(),
        ]);

        // User with confirmed registration CANNOT join waitlist
        $response = $this->postJson($this->publicUrl("events/{$event->slug}/waitlist"), [
            'first_name' => 'Existing',
            'last_name' => 'Confirmed',
            'email' => 'confirmed@example.com',
            'quantity' => 1,
        ]);
        $response->assertStatus(409);
        $this->assertStringContainsString('already have a confirmed registration', $response->json('message'));

        // New user joins waitlist
        $firstJoin = $this->postJson($this->publicUrl("events/{$event->slug}/waitlist"), [
            'first_name' => 'Waitlist',
            'last_name' => 'User',
            'email' => 'waitlister@example.com',
            'quantity' => 1,
        ]);
        $this->assertSuccessEnvelope($firstJoin);
        $firstUuid = $firstJoin->json('data.uuid');

        // Same user submitting duplicate waitlist request returns existing entry idempotently
        $secondJoin = $this->postJson($this->publicUrl("events/{$event->slug}/waitlist"), [
            'first_name' => 'Waitlist',
            'last_name' => 'User',
            'email' => 'waitlister@example.com',
            'quantity' => 1,
        ]);
        $this->assertSuccessEnvelope($secondJoin);
        $this->assertEquals($firstUuid, $secondJoin->json('data.uuid'));
        $this->assertEquals(1, WaitlistEntry::where('event_id', $event->id)->count());
    }

    public function test_live_event_with_open_and_sold_out_waitlist_behavior(): void
    {
        $category = $this->category(['is_active' => true]);
        $event = Event::factory()->create([
            'category_id' => $category->id,
            'name' => 'Live Summit',
            'slug' => 'live-summit-' . Str::lower(Str::random(6)),
            'capacity' => 1,
            'waitlist_enabled' => true,
            'status' => EventStatus::Live,
            'is_public' => true,
            'start_at' => now()->subHours(1),
            'end_at' => now()->addHours(3),
        ]);

        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'price' => 0,
            'quantity' => 1,
            'is_active' => true,
        ]);

        // 1. Live + OPEN allows normal registration
        $response = $this->getJson($this->publicUrl("events/{$event->slug}"));
        $this->assertSuccessEnvelope($response);
        $response->assertJsonPath('data.lifecycle_state', 'LIVE');
        $response->assertJsonPath('data.registration_availability', 'OPEN');
        $response->assertJsonPath('data.is_accepting_registrations', true);

        // Fill capacity
        $regResponse = $this->postJson($this->publicUrl("events/{$event->slug}/register"), [
            'first_name' => 'Live',
            'last_name' => 'Attendee',
            'email' => 'live@example.com',
            'quantity' => 1,
            'ticket_type_id' => $ticketType->uuid,
        ]);
        $this->assertSuccessEnvelope($regResponse);

        // 2. Live + SOLD_OUT + waitlist enabled allows waitlist
        $response2 = $this->getJson($this->publicUrl("events/{$event->slug}"));
        $response2->assertJsonPath('data.registration_availability', 'SOLD_OUT');
        $response2->assertJsonPath('data.is_accepting_waitlist', true);
        $response2->assertJsonPath('data.registration_label', 'Join Waitlist');

        $waitResponse = $this->postJson($this->publicUrl("events/{$event->slug}/waitlist"), [
            'first_name' => 'Live',
            'last_name' => 'Waitlister',
            'email' => 'livewait@example.com',
            'quantity' => 1,
            'ticket_type_id' => $ticketType->uuid,
        ]);
        $this->assertSuccessEnvelope($waitResponse);
        $this->assertEquals(1, WaitlistEntry::where('event_id', $event->id)->count());
    }
}
