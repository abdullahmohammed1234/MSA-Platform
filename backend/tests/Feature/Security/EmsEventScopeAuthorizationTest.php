<?php

namespace Tests\Feature\Security;

use App\Ems\Models\Event;
use App\Ems\Models\EventStaff;
use App\Ems\Models\EventVolunteer;
use App\Ems\Support\EmsRoles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Ems\EmsTestCase;

class EmsEventScopeAuthorizationTest extends EmsTestCase
{
    private function staffOn(Event $event)
    {
        $user = $this->emsUser(EmsRoles::EVENT_STAFF);
        EventStaff::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role' => 'check_in_staff',
        ]);

        return $user;
    }

    private function volunteerOn(Event $event): EventVolunteer
    {
        return EventVolunteer::create([
            'event_id' => $event->id,
            'name' => 'Private Volunteer',
            'email' => 'private.volunteer@example.com',
            'phone' => '604-555-0100',
            'status' => 'pending',
        ]);
    }

    public function test_staff_from_another_event_cannot_read_update_or_export_volunteers(): void
    {
        $event = $this->event();
        $volunteer = $this->volunteerOn($event);
        $outsider = $this->staffOn($this->event());

        $this->actingAsEms($outsider)
            ->getJson("/api/v1/ems/events/{$event->id}/volunteers")
            ->assertForbidden();

        $this->actingAsEms($outsider)
            ->get("/api/v1/ems/events/{$event->id}/volunteers/export")
            ->assertForbidden();

        $this->actingAsEms($outsider)
            ->patchJson("/api/v1/ems/events/{$event->id}/volunteers/{$volunteer->id}/status", [
                'status' => 'declined',
            ])
            ->assertForbidden();

        $this->assertSame('pending', $volunteer->fresh()->status);
    }

    public function test_staff_on_the_event_can_list_but_not_change_volunteer_status(): void
    {
        $event = $this->event();
        $volunteer = $this->volunteerOn($event);
        $staff = $this->staffOn($event);

        $this->actingAsEms($staff)
            ->getJson("/api/v1/ems/events/{$event->id}/volunteers")
            ->assertOk()
            ->assertJsonPath('data.metrics.total', 1);

        $this->actingAsEms($staff)
            ->patchJson("/api/v1/ems/events/{$event->id}/volunteers/{$volunteer->id}/status", [
                'status' => 'approved',
            ])
            ->assertForbidden();

        $this->assertSame('pending', $volunteer->fresh()->status);
    }

    public function test_organizer_of_the_event_can_change_volunteer_status(): void
    {
        $event = $this->event();
        $volunteer = $this->volunteerOn($event);
        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $event->update(['organizer_id' => $organizer->id, 'created_by' => $organizer->id]);

        $this->actingAsEms($organizer)
            ->patchJson("/api/v1/ems/events/{$event->id}/volunteers/{$volunteer->id}/status", [
                'status' => 'approved',
            ])
            ->assertOk();

        $this->assertSame('approved', $volunteer->fresh()->status);
    }

    public function test_document_viewer_cannot_rotate_the_qr_access_token(): void
    {
        Storage::fake('ems_documents');
        config(['filesystems.disks.ems_documents.root' => Storage::disk('ems_documents')->path('')]);

        $event = $this->event();
        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $pdf = UploadedFile::fake()->createWithContent('menu.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF");

        $created = $this->actingAsEms($admin)
            ->postJson($this->url("events/{$event->uuid}/documents"), [
                'file' => $pdf,
                'name' => 'Menu',
                'document_type' => 'other',
            ])
            ->assertCreated();
        $docUuid = $created->json('data.document.uuid');
        $token = $created->json('data.raw_token');

        $viewer = $this->staffOn($event);

        $this->actingAsEms($viewer)
            ->getJson($this->url("events/{$event->uuid}/documents/{$docUuid}/qr"))
            ->assertForbidden();

        $this->get("/event-documents/{$docUuid}/{$token}")->assertOk();

        $this->actingAsEms($admin)
            ->getJson($this->url("events/{$event->uuid}/documents/{$docUuid}/qr"))
            ->assertOk();

        $this->get("/event-documents/{$docUuid}/{$token}")->assertNotFound();
    }
}
