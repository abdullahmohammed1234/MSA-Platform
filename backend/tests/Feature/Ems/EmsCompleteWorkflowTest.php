<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Models\Event;
use App\Ems\Support\EmsRoles;
use Illuminate\Http\UploadedFile;

/**
 * Cross-phase workflow test: organizer setup through attendee operations.
 *
 * The focused suites test each subsystem deeply; this test protects the
 * contracts between phases by exercising them in the order a real event uses.
 */
class EmsCompleteWorkflowTest extends EmsTestCase
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

    public function test_complete_free_event_workflow_across_all_implemented_phases(): void
    {
        $organizer = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $category = $this->category([
            'name' => 'Workflow Education',
            'slug' => 'workflow-education',
            'is_active' => true,
        ]);

        // Phase 1: create a private draft and configure admission.
        $create = $this->actingAsEms($organizer)->postJson($this->url('events'), [
            'name' => 'Complete Workflow Halaqa',
            'slug' => 'complete-workflow-halaqa',
            'short_description' => 'Automated cross-phase workflow validation.',
            'category_id' => $category->id,
            'location' => 'SFU Burnaby',
            'start_at' => now()->addDay()->startOfHour()->toDateTimeString(),
            'end_at' => now()->addDay()->startOfHour()->addHours(2)->toDateTimeString(),
            'capacity' => 30,
            'is_public' => true,
        ]);
        $create->assertCreated()->assertJsonPath('data.status', EventStatus::Draft->value);

        $event = Event::where('uuid', $create->json('data.uuid'))->firstOrFail();

        $ticketType = $this->actingAsEms($organizer)->postJson(
            $this->url("events/{$event->uuid}/tickets"),
            [
                'name' => 'General Admission',
                'price' => 0,
                'currency' => 'CAD',
                'quantity' => 30,
            ]
        );
        $ticketType->assertCreated();
        $ticketTypeUuid = $ticketType->json('data.uuid');

        foreach (['publish', 'open_registration'] as $action) {
            $this->actingAsEms($organizer)
                ->postJson($this->url("events/{$event->uuid}/transitions"), ['action' => $action])
                ->assertOk();
        }

        // Phase 2/3: discover, register, issue a QR ticket, and validate it.
        $this->getJson($this->url('public/events?search=Complete%20Workflow'))
            ->assertOk()
            ->assertJsonPath('data.0.slug', $event->slug);

        $this->getJson($this->url("public/events/{$event->slug}"))
            ->assertOk()
            ->assertJsonPath('data.is_accepting_registrations', true)
            ->assertJsonPath('data.ticket_types.0.uuid', $ticketTypeUuid);

        $registration = $this->postJson($this->url("public/events/{$event->slug}/register"), [
            'first_name' => 'Workflow',
            'last_name' => 'Attendee',
            'email' => 'workflow.attendee@example.test',
            'ticket_type_id' => $ticketTypeUuid,
            'quantity' => 1,
        ]);
        $registration->assertCreated()->assertJsonPath('data.status', 'confirmed');

        $ticketCode = $registration->json('data.tickets.0.code');
        $this->assertNotEmpty($ticketCode);

        $this->getJson($this->url("public/tickets/{$ticketCode}"))
            ->assertOk()
            ->assertJsonPath('data.event.slug', $event->slug);
        $this->getJson($this->url("public/tickets/validate/{$ticketCode}"))
            ->assertOk()
            ->assertJsonPath('data.valid', true);
        $this->get($this->url("public/tickets/{$ticketCode}/qr"))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        // Phase 4: close doors, go live, scan, reject duplicate, and undo.
        foreach (['close_registration', 'mark_live'] as $action) {
            $this->actingAsEms($organizer)
                ->postJson($this->url("events/{$event->uuid}/transitions"), ['action' => $action])
                ->assertOk();
        }

        $validate = $this->actingAsEms($organizer)->postJson(
            $this->url("events/{$event->uuid}/validate-ticket"),
            ['code' => $ticketCode]
        );
        $validate->assertOk()->assertJsonPath('data.code', 'valid');

        $checkIn = $this->actingAsEms($organizer)->postJson(
            $this->url("events/{$event->uuid}/check-in"),
            ['code' => $ticketCode, 'method' => 'qr_scan']
        );
        $checkIn->assertOk()->assertJsonPath('data.code', 'checked_in');

        $this->actingAsEms($organizer)->postJson(
            $this->url("events/{$event->uuid}/check-in"),
            ['code' => $ticketCode]
        )->assertConflict()->assertJsonPath('data.code', 'already_checked_in');

        $this->actingAsEms($organizer)->getJson($this->url("events/{$event->uuid}/operations"))
            ->assertOk()
            ->assertJsonPath('data.registered_count', 1)
            ->assertJsonPath('data.checked_in_count', 1);

        $this->actingAsEms($organizer)->postJson(
            $this->url("events/{$event->uuid}/undo-check-in"),
            [
                'check_in_uuid' => $checkIn->json('data.check_in.uuid'),
                'reason' => 'Workflow verification',
            ]
        )->assertOk();

        // Walk-ins and legacy imports use the same event and ticket inventory.
        $this->actingAsEms($organizer)->postJson(
            $this->url("events/{$event->uuid}/walk-in"),
            [
                'attendee_name' => 'Workflow Walk In',
                'attendee_email' => 'workflow.walkin@example.test',
                'ticket_type_id' => $ticketTypeUuid,
                'check_in' => true,
            ]
        )->assertCreated();

        $csv = "Full Name,Email\nWorkflow Imported,workflow.imported@example.test\n";
        $preview = $this->actingAsEms($organizer)->post(
            $this->url("events/{$event->uuid}/import/preview"),
            [
                'file' => UploadedFile::fake()->createWithContent('workflow.csv', $csv),
                'column_mapping' => json_encode([
                    'name' => 'Full Name',
                    'email' => 'Email',
                ]),
            ],
            ['Accept' => 'application/json']
        );
        $preview->assertOk()->assertJsonPath('data.valid', 1);

        $this->actingAsEms($organizer)->postJson(
            $this->url("events/{$event->uuid}/import"),
            ['import_uuid' => $preview->json('data.import_uuid')]
        )->assertOk();

        $this->actingAsEms($organizer)->getJson(
            $this->url("events/{$event->uuid}/attendees") . '?search=Workflow'
        )->assertOk()->assertJsonCount(3, 'data');

        // Finish the lifecycle; archived events disappear from public access.
        foreach (['complete', 'archive'] as $action) {
            $this->actingAsEms($organizer)
                ->postJson($this->url("events/{$event->uuid}/transitions"), ['action' => $action])
                ->assertOk();
        }

        $this->getJson($this->url("public/events/{$event->slug}"))->assertNotFound();
    }
}
