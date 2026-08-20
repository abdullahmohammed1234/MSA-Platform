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
}
