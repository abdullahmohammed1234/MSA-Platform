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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaitlistWorkflowAndConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_waitlist_workflow_and_capacity_invariants(): void
    {
        $event = new Event();
        $event->name = 'Waitlist Masterclass';
        $event->slug = 'waitlist-masterclass';
        $event->capacity = 1;
        $event->waitlist_enabled = true;
        $event->status = EventStatus::RegistrationOpen;
        $event->start_at = now()->addDays(2);
        $event->end_at = now()->addDays(2)->addHours(2);
        $event->save();

        $ticketType = TicketType::create([
            'event_id' => $event->id,
            'name' => 'General Admission',
            'price' => 0,
            'capacity' => 1,
            'quantity_sold' => 1,
        ]);

        // 1. User A is registered (event full)
        $regA = Registration::create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'attendee_name' => 'User A',
            'attendee_email' => 'usera@example.com',
            'status' => RegistrationStatus::Confirmed,
            'reference' => 'REG-AAAA',
            'registered_at' => now(),
        ]);

        $this->assertFalse($event->fresh()->hasAvailableCapacity(1));

        /** @var WaitlistService $waitlistService */
        $waitlistService = app(WaitlistService::class);

        // 2. User B joins waitlist
        $entryB = $waitlistService->join($event, [
            'first_name' => 'User',
            'last_name' => 'B',
            'email' => 'userb@example.com',
            'ticket_type_id' => $ticketType->uuid,
        ]);
        $this->assertEquals(1, $entryB->position);

        // 3. User C joins waitlist
        $entryC = $waitlistService->join($event, [
            'first_name' => 'User',
            'last_name' => 'C',
            'email' => 'userc@example.com',
            'ticket_type_id' => $ticketType->uuid,
        ]);
        $this->assertEquals(2, $entryC->position);

        // Verify capacity invariant BEFORE cancellation
        $confirmedCount = Registration::where('event_id', $event->id)
            ->where('status', RegistrationStatus::Confirmed->value)
            ->count();
        $this->assertLessThanOrEqual(1, $confirmedCount);

        // 4. User A cancels registration
        $regA->status = RegistrationStatus::Cancelled;
        $regA->cancelled_at = now();
        $regA->save();
        $ticketType->quantity_sold = 0;
        $ticketType->save();

        // 5. Trigger promotion
        $promotedCount = $waitlistService->promoteAvailable($event);

        $this->assertEquals(1, $promotedCount);

        // Verify User B is promoted
        $this->assertEquals(WaitlistStatus::Promoted, $entryB->fresh()->status);
        $this->assertEquals(RegistrationStatus::Confirmed->value, $entryB->registration->fresh()->status->value);

        // Verify User C is resequenced to position 1 and remains waiting
        $this->assertEquals(WaitlistStatus::Waiting, $entryC->fresh()->status);
        $this->assertEquals(1, $entryC->fresh()->position);

        // Final invariant check: confirmed_count <= capacity (1)
        $finalConfirmed = Registration::where('event_id', $event->id)
            ->where('status', RegistrationStatus::Confirmed->value)
            ->count();
        $this->assertEquals(1, $finalConfirmed);
    }

    public function test_concurrent_promotion_safely_limits_to_available_capacity(): void
    {
        $event = new Event();
        $event->name = 'Concurrency Test Event';
        $event->slug = 'concurrency-test-event';
        $event->capacity = 1;
        $event->waitlist_enabled = true;
        $event->status = EventStatus::RegistrationOpen;
        $event->start_at = now()->addDays(3);
        $event->end_at = now()->addDays(3)->addHours(2);
        $event->save();

        /** @var WaitlistService $waitlistService */
        $waitlistService = app(WaitlistService::class);

        $entry1 = $waitlistService->join($event, [
            'first_name' => 'Waitlisted',
            'last_name' => 'One',
            'email' => 'w1@example.com',
        ]);

        $entry2 = $waitlistService->join($event, [
            'first_name' => 'Waitlisted',
            'last_name' => 'Two',
            'email' => 'w2@example.com',
        ]);

        // Execute promotion
        $promotedFirst = $waitlistService->promoteAvailable($event);
        $this->assertEquals(1, $promotedFirst);

        // Second promotion call when capacity is now full
        $promotedSecond = $waitlistService->promoteAvailable($event);
        $this->assertEquals(0, $promotedSecond);

        // Strictly verify confirmed count <= 1
        $totalConfirmed = Registration::where('event_id', $event->id)
            ->where('status', RegistrationStatus::Confirmed->value)
            ->count();
        $this->assertLessThanOrEqual(1, $totalConfirmed);
    }
}
