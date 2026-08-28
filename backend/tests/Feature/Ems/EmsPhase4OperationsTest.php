<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\TicketStatus;
use App\Ems\Models\CheckIn;
use App\Ems\Models\Event;
use App\Ems\Models\EventStaff;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use App\Ems\Models\TicketType;
use App\Ems\Support\EmsRoles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class EmsPhase4OperationsTest extends EmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'queue.default' => 'sync',
            'ems.tickets.enabled' => true,
            'ems.tickets.qr_enabled' => true,
        ]);
    }

    protected function liveEvent(array $attributes = []): Event
    {
        $category = $this->category(['is_active' => true]);

        return Event::factory()->create(array_merge([
            'category_id' => $category->id,
            'name' => 'Eid Dinner',
            'slug' => 'eid-dinner-' . Str::lower(Str::random(4)),
            'capacity' => 100,
            'status' => \App\Ems\Enums\EventStatus::Live,
        ], $attributes));
    }

    protected function organizerFor(Event $event)
    {
        $user = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $event->update(['organizer_id' => $user->id, 'created_by' => $user->id]);

        return $user;
    }

    protected function staffFor(Event $event)
    {
        $user = $this->emsUser(EmsRoles::EVENT_STAFF);
        EventStaff::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role' => 'check_in_staff',
            'assigned_by' => $event->organizer_id,
        ]);

        return $user;
    }

    protected function freeTicketType(Event $event): TicketType
    {
        return TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'General Admission',
            'price' => 0,
            'quantity' => 200,
            'is_active' => true,
        ]);
    }

    protected function confirmedAttendee(Event $event, TicketType $ticketType, array $attrs = []): array
    {
        $registration = Registration::factory()->create(array_merge([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'attendee_name' => 'Fatima Khan',
            'attendee_email' => 'fatima@example.com',
        ], $attrs));

        $ticket = Ticket::factory()->create([
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'ticket_type_id' => $ticketType->id,
            'holder_name' => $registration->attendee_name,
            'holder_email' => $registration->attendee_email,
            'status' => TicketStatus::Issued,
            'qr_payload' => 'http://localhost:5173/tickets/PLACEHOLDER',
        ]);

        $ticket->qr_payload = 'http://localhost:5173/tickets/' . $ticket->code;
        $ticket->qr_generated_at = now();
        $ticket->save();

        return compact('registration', 'ticket');
    }

    public function test_csv_import_preview_and_commit_creates_tickets(): void
    {
        $event = $this->liveEvent();
        $user = $this->organizerFor($event);
        $this->freeTicketType($event);

        $csv = "Full Name,Email,Phone\nAisha Rahman,aisha@import.test,555-0100\n";
        $file = UploadedFile::fake()->createWithContent('attendees.csv', $csv);

        $preview = $this->actingAsEms($user)->post(
            $this->url("events/{$event->uuid}/import/preview"),
            [
                'file' => $file,
                'column_mapping' => json_encode([
                    'name' => 'Full Name',
                    'email' => 'Email',
                    'phone' => 'Phone',
                ]),
            ],
            ['Accept' => 'application/json']
        );

        $this->assertSuccessEnvelope($preview);
        $preview->assertJsonPath('data.valid', 1);
        $importUuid = $preview->json('data.import_uuid');

        $commit = $this->actingAsEms($user)->postJson(
            $this->url("events/{$event->uuid}/import"),
            ['import_uuid' => $importUuid]
        );

        $this->assertSuccessEnvelope($commit);
        $this->assertDatabaseHas('ems_registrations', [
            'event_id' => $event->id,
            'attendee_email' => 'aisha@import.test',
        ]);
        $this->assertDatabaseHas('ems_tickets', [
            'event_id' => $event->id,
            'holder_email' => 'aisha@import.test',
        ]);

        $ticket = Ticket::query()->where('holder_email', 'aisha@import.test')->first();
        $this->assertNotNull($ticket?->qr_payload);
    }

    public function test_import_validation_rejects_invalid_rows(): void
    {
        $event = $this->liveEvent();
        $user = $this->organizerFor($event);
        $this->freeTicketType($event);

        $csv = "Full Name,Email\n,not-an-email\nValid Person,valid@example.com\n";
        $file = UploadedFile::fake()->createWithContent('bad.csv', $csv);

        $preview = $this->actingAsEms($user)->post(
            $this->url("events/{$event->uuid}/import/preview"),
            [
                'file' => $file,
                'column_mapping' => json_encode([
                    'name' => 'Full Name',
                    'email' => 'Email',
                ]),
            ],
            ['Accept' => 'application/json']
        );

        $this->assertSuccessEnvelope($preview);
        $preview->assertJsonPath('data.invalid', 1);
        $preview->assertJsonPath('data.valid', 1);
    }

    public function test_qr_check_in_and_duplicate_detection(): void
    {
        $event = $this->liveEvent();
        $user = $this->organizerFor($event);
        $ticketType = $this->freeTicketType($event);
        ['ticket' => $ticket] = $this->confirmedAttendee($event, $ticketType);

        $first = $this->actingAsEms($user)->postJson(
            $this->url("events/{$event->uuid}/check-in"),
            ['code' => $ticket->qr_payload]
        );
        $this->assertSuccessEnvelope($first);
        $first->assertJsonPath('data.code', 'checked_in');
        $this->assertDatabaseHas('ems_check_ins', ['ticket_id' => $ticket->id]);
        $this->assertSame(TicketStatus::Redeemed, $ticket->fresh()->status);

        $second = $this->actingAsEms($user)->postJson(
            $this->url("events/{$event->uuid}/check-in"),
            ['code' => $ticket->code]
        );
        $second->assertStatus(409);
        $second->assertJsonPath('data.code', 'already_checked_in');
    }

    public function test_wrong_event_detection(): void
    {
        $eventA = $this->liveEvent(['name' => 'Ramadan Dinner']);
        $eventB = $this->liveEvent(['name' => 'Eid Dinner']);
        $user = $this->organizerFor($eventB);
        // Ensure user can also see event A as organizer of B only — staff on B.
        $eventB->update(['organizer_id' => $user->id, 'created_by' => $user->id]);

        $typeA = $this->freeTicketType($eventA);
        ['ticket' => $ticket] = $this->confirmedAttendee($eventA, $typeA, [
            'attendee_email' => 'cross@example.com',
        ]);

        $response = $this->actingAsEms($user)->postJson(
            $this->url("events/{$eventB->uuid}/validate-ticket"),
            ['code' => $ticket->code]
        );

        $response->assertStatus(409);
        $response->assertJsonPath('data.code', 'wrong_event');
        $response->assertJsonPath('message', 'Wrong Event');
    }

    public function test_manual_check_in_and_undo(): void
    {
        $event = $this->liveEvent();
        $user = $this->organizerFor($event);
        $ticketType = $this->freeTicketType($event);
        ['registration' => $registration, 'ticket' => $ticket] = $this->confirmedAttendee($event, $ticketType, [
            'attendee_email' => 'manual@example.com',
        ]);

        $checkIn = $this->actingAsEms($user)->postJson(
            $this->url("events/{$event->uuid}/manual-check-in"),
            ['registration_uuid' => $registration->uuid]
        );
        $this->assertSuccessEnvelope($checkIn);

        $checkInUuid = $checkIn->json('data.check_in.uuid');

        $undo = $this->actingAsEms($user)->postJson(
            $this->url("events/{$event->uuid}/undo-check-in"),
            ['check_in_uuid' => $checkInUuid, 'reason' => 'Scanned wrong guest']
        );
        $this->assertSuccessEnvelope($undo);

        $this->assertDatabaseMissing('ems_check_ins', ['uuid' => $checkInUuid]);
        $this->assertSame(TicketStatus::Issued, $ticket->fresh()->status);
        $this->assertDatabaseHas('ems_check_in_audits', [
            'event_id' => $event->id,
            'action' => 'undo',
        ]);
    }

    public function test_walk_in_free_registers_and_checks_in(): void
    {
        $event = $this->liveEvent();
        $user = $this->organizerFor($event);
        $ticketType = $this->freeTicketType($event);

        $response = $this->actingAsEms($user)->postJson(
            $this->url("events/{$event->uuid}/walk-in"),
            [
                'attendee_name' => 'Walk In Guest',
                'attendee_email' => 'walkin@example.com',
                'ticket_type_id' => $ticketType->uuid,
                'check_in' => true,
            ]
        );

        $this->assertSuccessEnvelope($response);
        $response->assertCreated();
        $this->assertDatabaseHas('ems_registrations', [
            'attendee_email' => 'walkin@example.com',
            'event_id' => $event->id,
        ]);
        $this->assertNotNull($response->json('data.check_in'));
        $this->assertSame(1, CheckIn::query()->where('event_id', $event->id)->count());
    }

    public function test_event_staff_can_check_in_but_cannot_update_event(): void
    {
        $event = $this->liveEvent();
        $organizer = $this->organizerFor($event);
        $staff = $this->staffFor($event);
        $ticketType = $this->freeTicketType($event);
        ['ticket' => $ticket] = $this->confirmedAttendee($event, $ticketType, [
            'attendee_email' => 'staff-scan@example.com',
        ]);

        $checkIn = $this->actingAsEms($staff)->postJson(
            $this->url("events/{$event->uuid}/check-in"),
            ['code' => $ticket->code]
        );
        $this->assertSuccessEnvelope($checkIn);

        $update = $this->actingAsEms($staff)->putJson(
            $this->url("events/{$event->uuid}"),
            ['name' => 'Hacked Name']
        );
        $update->assertForbidden();

        $undo = $this->actingAsEms($staff)->postJson(
            $this->url("events/{$event->uuid}/undo-check-in"),
            ['ticket_code' => $ticket->code, 'reason' => 'Nope']
        );
        $undo->assertForbidden();

        // Organizer still works for comparison.
        $this->assertNotNull($organizer->id);
    }

    public function test_attendee_list_search_and_operations_summary(): void
    {
        $event = $this->liveEvent();
        $user = $this->organizerFor($event);
        $ticketType = $this->freeTicketType($event);
        $this->confirmedAttendee($event, $ticketType, [
            'attendee_name' => 'Zayd Ahmed',
            'attendee_email' => 'zayd@example.com',
        ]);

        $list = $this->actingAsEms($user)->getJson(
            $this->url("events/{$event->uuid}/attendees") . '?search=Zayd'
        );
        $this->assertSuccessEnvelope($list);
        $this->assertGreaterThanOrEqual(1, count($list->json('data')));

        $ops = $this->actingAsEms($user)->getJson(
            $this->url("events/{$event->uuid}/operations")
        );
        $this->assertSuccessEnvelope($ops);
        $ops->assertJsonPath('data.registered_count', 1);
        $ops->assertJsonPath('data.checked_in_count', 0);
    }

    public function test_attendee_attendance_status_uses_registered_attending_and_no_show(): void
    {
        $liveEvent = $this->liveEvent([
            'start_at' => now()->addHour(),
            'end_at' => now()->addHours(3),
        ]);
        $user = $this->organizerFor($liveEvent);
        $ticketType = $this->freeTicketType($liveEvent);
        ['registration' => $upcoming, 'ticket' => $ticket] = $this->confirmedAttendee($liveEvent, $ticketType, [
            'attendee_email' => 'upcoming@example.com',
        ]);

        $upcomingList = $this->actingAsEms($user)->getJson(
            $this->url("events/{$liveEvent->uuid}/attendees")
        );
        $this->assertSuccessEnvelope($upcomingList);
        $upcomingList->assertJsonPath('data.0.check_in_status', 'not_checked_in');
        $upcomingList->assertJsonPath('data.0.check_in_status_label', 'Not Checked In');
        $upcomingList->assertJsonPath('data.0.registration_status_label', 'Registered');

        CheckIn::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $liveEvent->id,
            'ticket_id' => $ticket->id,
            'registration_id' => $upcoming->id,
            'checked_in_at' => now(),
        ]);

        $attendingList = $this->actingAsEms($user)->getJson(
            $this->url("events/{$liveEvent->uuid}/attendees")
        );
        $attendingList->assertJsonPath('data.0.check_in_status', 'checked_in');
        $attendingList->assertJsonPath('data.0.check_in_status_label', 'Attending');

        $pastEvent = $this->liveEvent([
            'name' => 'Past Dinner',
            'slug' => 'past-dinner-' . Str::lower(Str::random(4)),
            'status' => \App\Ems\Enums\EventStatus::Completed,
            'start_at' => now()->subDays(2),
            'end_at' => now()->subDay(),
        ]);
        $pastEvent->update(['organizer_id' => $user->id, 'created_by' => $user->id]);
        $pastTicketType = $this->freeTicketType($pastEvent);
        $this->confirmedAttendee($pastEvent, $pastTicketType, [
            'attendee_email' => 'noshow@example.com',
        ]);

        $noShowList = $this->actingAsEms($user)->getJson(
            $this->url("events/{$pastEvent->uuid}/attendees")
        );
        $this->assertSuccessEnvelope($noShowList);
        $noShowList->assertJsonPath('data.0.check_in_status', 'no_show');
        $noShowList->assertJsonPath('data.0.check_in_status_label', "Didn't come");

        $filtered = $this->actingAsEms($user)->getJson(
            $this->url("events/{$pastEvent->uuid}/attendees") . '?check_in_status=no_show'
        );
        $this->assertSuccessEnvelope($filtered);
        $this->assertCount(1, $filtered->json('data'));

        $emptyOnLive = $this->actingAsEms($user)->getJson(
            $this->url("events/{$liveEvent->uuid}/attendees") . '?check_in_status=no_show'
        );
        $this->assertSuccessEnvelope($emptyOnLive);
        $this->assertCount(0, $emptyOnLive->json('data'));
    }

    public function test_staff_cannot_import_attendees(): void
    {
        $event = $this->liveEvent();
        $this->organizerFor($event);
        $staff = $this->staffFor($event);
        $this->freeTicketType($event);

        $csv = "Name,Email\nX,x@test.com\n";
        $file = UploadedFile::fake()->createWithContent('x.csv', $csv);

        $preview = $this->actingAsEms($staff)->post(
            $this->url("events/{$event->uuid}/import/preview"),
            ['file' => $file],
            ['Accept' => 'application/json']
        );
        $preview->assertForbidden();
    }

    public function test_paid_public_cancellation_is_blocked_but_free_works(): void
    {
        $event = $this->liveEvent();
        $freeUser = $this->emsUser();
        $paidUser = $this->emsUser();
        $ticketType = $this->freeTicketType($event);
        
        // 1. Create a free registration
        $freeReg = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'user_id' => $freeUser->id,
            'attendee_name' => $freeUser->name,
            'attendee_email' => $freeUser->email,
            'status' => \App\Ems\Enums\RegistrationStatus::Confirmed,
            'type' => \App\Ems\Enums\RegistrationType::Free,
        ]);

        // 2. Create a paid registration
        $paidTicketType = TicketType::factory()->paid(15)->create(['event_id' => $event->id, 'name' => 'Paid Entry VIP']);
        $paidReg = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $paidTicketType->id,
            'user_id' => $paidUser->id,
            'attendee_name' => $paidUser->name,
            'attendee_email' => $paidUser->email,
            'status' => \App\Ems\Enums\RegistrationStatus::Confirmed,
            'type' => \App\Ems\Enums\RegistrationType::Paid,
            'amount_due' => 15,
        ]);

        // 3. Attempt to cancel free registration (should succeed)
        $this->actingAsEms($freeUser)->postJson("/api/v1/ems/public/registrations/{$freeReg->uuid}/cancel")
            ->assertOk();

        $this->assertSame(\App\Ems\Enums\RegistrationStatus::Cancelled, $freeReg->fresh()->status);

        // 4. Attempt to cancel paid registration (should be blocked)
        $this->actingAsEms($paidUser)->postJson("/api/v1/ems/public/registrations/{$paidReg->uuid}/cancel")
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->assertSame(\App\Ems\Enums\RegistrationStatus::Confirmed, $paidReg->fresh()->status);
    }

    public function test_free_waitlist_promotion_auto_confirms_and_issues_tickets(): void
    {
        $event = $this->liveEvent(['capacity' => 1, 'waitlist_enabled' => true]);
        $firstUser = $this->emsUser();
        $ticketType = $this->freeTicketType($event);
        
        // Confirm first attendee, which consumes the capacity
        $this->confirmedAttendee($event, $ticketType, [
            'user_id' => $firstUser->id,
            'attendee_email' => $firstUser->email,
        ]);

        // Join waitlist for attendee 2 (free)
        $waitlistService = app(\App\Ems\Services\WaitlistService::class);
        $entry = $waitlistService->join($event, [
            'first_name' => 'Waitlist',
            'last_name' => 'Free',
            'email' => 'waitlistfree@example.com',
            'ticket_type_id' => $ticketType->uuid,
            'quantity' => 1,
        ]);

        $registration = $entry->registration;
        $this->assertSame(\App\Ems\Enums\RegistrationStatus::Waitlisted, $registration->status);

        // Free up capacity by cancelling the first attendee
        $firstReg = Registration::where('attendee_email', $firstUser->email)->first();
        $this->actingAsEms($firstUser)->postJson("/api/v1/ems/public/registrations/{$firstReg->uuid}/cancel")
            ->assertOk();

        // Check that capacity is freed and promotion is automatically run
        $this->assertSame(\App\Ems\Enums\RegistrationStatus::Confirmed, $registration->fresh()->status);
        $this->assertSame(1, $registration->tickets()->count());
        $this->assertSame(TicketStatus::Issued, $registration->tickets()->first()->status);
    }

    public function test_paid_waitlist_promotion_sets_expiration(): void
    {
        $event = $this->liveEvent(['capacity' => 1, 'waitlist_enabled' => true]);
        $firstUser = $this->emsUser();
        $freeType = $this->freeTicketType($event);
        $paidType = TicketType::factory()->paid(20)->create(['event_id' => $event->id, 'name' => 'Paid VIP Entry']);

        // Consume capacity
        $this->confirmedAttendee($event, $freeType, [
            'user_id' => $firstUser->id,
            'attendee_email' => $firstUser->email,
        ]);

        // Join waitlist for paid ticket
        $waitlistService = app(\App\Ems\Services\WaitlistService::class);
        $entry = $waitlistService->join($event, [
            'first_name' => 'Waitlist',
            'last_name' => 'Paid',
            'email' => 'waitlistpaid@example.com',
            'ticket_type_id' => $paidType->uuid,
            'quantity' => 1,
        ]);

        $registration = $entry->registration;
        $this->assertSame(\App\Ems\Enums\RegistrationStatus::Waitlisted, $registration->status);

        // Cancel first attendee to free capacity
        $firstReg = Registration::where('attendee_email', $firstUser->email)->first();
        $this->actingAsEms($firstUser)->postJson("/api/v1/ems/public/registrations/{$firstReg->uuid}/cancel")
            ->assertOk();

        // Registration should be promoted to Pending with promoted_expires_at set
        $registration = $registration->fresh();
        $this->assertSame(\App\Ems\Enums\RegistrationStatus::Pending, $registration->status);
        $this->assertNotNull($registration->promoted_expires_at);
        
        $expectedExpiry = now()->addHours((int) config('ems.waitlist.promotion_expiry_hours', 24));
        $this->assertTrue($registration->promoted_expires_at->diffInMinutes($expectedExpiry) < 5);
    }

    public function test_expiration_job_cancels_stale_promotion_and_promotes_next(): void
    {
        $event = $this->liveEvent(['capacity' => 1, 'waitlist_enabled' => true]);
        $user = $this->organizerFor($event);
        $freeType = $this->freeTicketType($event);
        $paidType = TicketType::factory()->paid(20)->create(['event_id' => $event->id, 'name' => 'Paid VIP Entry']);

        // Attendee 1 is promoted and pending payment (expired)
        $expiredReg = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $paidType->id,
            'attendee_name' => 'Expired Guest',
            'attendee_email' => 'expired@example.com',
            'status' => \App\Ems\Enums\RegistrationStatus::Pending,
            'type' => \App\Ems\Enums\RegistrationType::Paid,
            'promoted_expires_at' => now()->subHour(),
        ]);
        $expiredEntry = \App\Ems\Models\WaitlistEntry::create([
            'event_id' => $event->id,
            'ticket_type_id' => $paidType->id,
            'registration_id' => $expiredReg->id,
            'attendee_name' => 'Expired Guest',
            'attendee_email' => 'expired@example.com',
            'status' => \App\Ems\Enums\WaitlistStatus::Promoted,
            'position' => 1,
            'quantity' => 1,
        ]);

        // Attendee 2 is waiting on waitlist (free)
        $waitlistService = app(\App\Ems\Services\WaitlistService::class);
        $entry2 = $waitlistService->join($event, [
            'first_name' => 'Next',
            'last_name' => 'Inline',
            'email' => 'next@example.com',
            'ticket_type_id' => $freeType->uuid,
            'quantity' => 1,
        ]);

        $this->assertSame(\App\Ems\Enums\RegistrationStatus::Waitlisted, $entry2->registration->status);

        // Run the expiration job
        \App\Ems\Jobs\ExpireStalePromotionsJob::dispatchSync();

        // Expired registration should be Cancelled
        $this->assertSame(\App\Ems\Enums\RegistrationStatus::Cancelled, $expiredReg->fresh()->status);
        $this->assertSame(\App\Ems\Enums\WaitlistStatus::Expired, $expiredEntry->fresh()->status);

        // Next inline (free) should be promoted and confirmed
        $this->assertSame(\App\Ems\Enums\RegistrationStatus::Confirmed, $entry2->registration->fresh()->status);
        $this->assertSame(1, $entry2->registration->tickets()->count());
    }

    public function test_csv_import_chunking_and_rollback(): void
    {
        $event = $this->liveEvent();
        $user = $this->organizerFor($event);
        $this->freeTicketType($event);

        // Mock TicketIssuer to fail for fail@chunk2.com
        $ticketsMock = $this->createMock(\App\Ems\Contracts\TicketIssuer::class);
        $ticketsMock->method('issueFor')->willReturnCallback(function($registration) {
            if ($registration->attendee_email === 'fail@chunk2.com') {
                throw new \Exception('Mocked ticketing failure');
            }
            return collect();
        });
        $this->app->instance(\App\Ems\Contracts\TicketIssuer::class, $ticketsMock);

        // Build 30 valid rows
        $rows = [];
        for ($i = 1; $i <= 30; $i++) {
            $email = ($i === 28) ? 'fail@chunk2.com' : "person{$i}@test.com";
            $rows[] = [
                'row_number' => $i,
                'name' => "Person {$i}",
                'email' => $email,
                'phone' => null,
                'ticket_type' => null,
                'ticket_type_id' => null,
                'ticket_type_db_id' => null,
                'is_member' => false,
                'registration_status' => 'confirmed',
                'payment_status' => 'paid',
                'errors' => [],
                'warnings' => [],
            ];
        }

        $import = \App\Ems\Models\AttendeeImport::create([
            'event_id' => $event->id,
            'imported_by' => $user->id,
            'original_filename' => 'bulk.csv',
            'source' => 'excel_csv',
            'status' => \App\Ems\Enums\AttendeeImportStatus::Previewed,
            'column_mapping' => [],
            'summary' => [
                'valid' => 30,
                'valid_rows' => $rows,
            ]
        ]);

        $service = app(\App\Ems\Services\Operations\AttendeeImportService::class);
        $result = $service->processImport($import);

        // Chunk 1 (25 rows) should be committed
        $this->assertSame(25, \App\Ems\Models\Registration::query()->where('attendee_email', 'like', '%@test.com')->count());

        // Chunk 2 (5 rows containing fail@chunk2.com) should be fully rolled back
        for ($i = 26; $i <= 30; $i++) {
            $email = ($i === 28) ? 'fail@chunk2.com' : "person{$i}@test.com";
            $this->assertDatabaseMissing('ems_registrations', ['attendee_email' => $email]);
        }
    }

    public function test_expiration_job_is_registered_in_scheduler(): void
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $events = collect($schedule->events());

        $hasJob = $events->contains(function (\Illuminate\Console\Scheduling\Event $event) {
            return str_contains($event->description, 'EMS: Expire stale waitlist promotions')
                || str_contains($event->command, 'ExpireStalePromotionsJob');
        });

        $this->assertTrue($hasJob);
    }

    public function test_promoted_expires_at_composite_index_exists(): void
    {
        $indexes = \Illuminate\Support\Facades\Schema::getIndexes('ems_registrations');
        
        $compositeIndex = collect($indexes)->first(function ($idx) {
            return $idx['name'] === 'ems_regs_status_expiry_idx';
        });

        $this->assertNotNull($compositeIndex, 'Composite index ems_regs_status_expiry_idx does not exist.');
        $this->assertSame(['status', 'promoted_expires_at'], $compositeIndex['columns']);
    }

    public function test_concurrent_expiration_safety(): void
    {
        $event = $this->liveEvent(['capacity' => 1, 'waitlist_enabled' => true]);
        $paidType = TicketType::factory()->paid(20)->create(['event_id' => $event->id, 'name' => 'Paid VIP Entry']);

        // 1. Create a stale promoted registration
        $expiredReg = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $paidType->id,
            'attendee_name' => 'Expired Guest',
            'attendee_email' => 'expired@example.com',
            'status' => \App\Ems\Enums\RegistrationStatus::Pending,
            'type' => \App\Ems\Enums\RegistrationType::Paid,
            'promoted_expires_at' => now()->subHour(),
        ]);
        
        $expiredEntry = \App\Ems\Models\WaitlistEntry::create([
            'event_id' => $event->id,
            'ticket_type_id' => $paidType->id,
            'registration_id' => $expiredReg->id,
            'attendee_name' => 'Expired Guest',
            'attendee_email' => 'expired@example.com',
            'status' => \App\Ems\Enums\WaitlistStatus::Promoted,
            'position' => 1,
            'quantity' => 1,
        ]);

        $waitlistService = app(\App\Ems\Services\WaitlistService::class);

        // 2. Invoke expireStale twice (simulating concurrent executions)
        $firstRunResult = $waitlistService->expireStale();
        $secondRunResult = $waitlistService->expireStale();

        // 3. Verify exact single expiration
        $this->assertSame(1, $firstRunResult);
        $this->assertSame(0, $secondRunResult);

        // 4. Verify consistent state
        $expiredReg = $expiredReg->fresh();
        $expiredEntry = $expiredEntry->fresh();

        $this->assertSame(\App\Ems\Enums\RegistrationStatus::Cancelled, $expiredReg->status);
        $this->assertSame(\App\Ems\Enums\WaitlistStatus::Expired, $expiredEntry->status);
        $this->assertNull($expiredReg->promoted_expires_at);
        $this->assertNotNull($expiredReg->cancelled_at);
    }

    public function test_concurrent_promotion_and_expiration_lifecycle(): void
    {
        $event = $this->liveEvent(['capacity' => 1, 'waitlist_enabled' => true]);
        $freeType = $this->freeTicketType($event);

        // 1. Create a stale promoted registration (Paid)
        $paidType = TicketType::factory()->paid(20)->create(['event_id' => $event->id, 'name' => 'Paid VIP Entry']);
        $expiredReg = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $paidType->id,
            'status' => \App\Ems\Enums\RegistrationStatus::Pending,
            'type' => \App\Ems\Enums\RegistrationType::Paid,
            'promoted_expires_at' => now()->subHour(),
        ]);
        $expiredEntry = \App\Ems\Models\WaitlistEntry::create([
            'event_id' => $event->id,
            'ticket_type_id' => $paidType->id,
            'registration_id' => $expiredReg->id,
            'attendee_name' => 'Expired Guest',
            'attendee_email' => 'expired@example.com',
            'status' => \App\Ems\Enums\WaitlistStatus::Promoted,
            'position' => 1,
            'quantity' => 1,
        ]);

        // 2. Attendee 2 waiting on waitlist (Free)
        $waitlistService = app(\App\Ems\Services\WaitlistService::class);
        $entry2 = $waitlistService->join($event, [
            'first_name' => 'Next',
            'last_name' => 'Inline',
            'email' => 'next@example.com',
            'ticket_type_id' => $freeType->uuid,
            'quantity' => 1,
        ]);

        // 3. Expiration job cleans up the stale promotion
        $waitlistService->expireStale();

        // 4. Verify that expired is cancelled and capacity is freed for attendee 2
        $this->assertSame(\App\Ems\Enums\RegistrationStatus::Cancelled, $expiredReg->fresh()->status);
        $this->assertSame(\App\Ems\Enums\RegistrationStatus::Confirmed, $entry2->registration->fresh()->status);
        $this->assertSame(1, $entry2->registration->tickets()->count());
    }
}

