<?php

namespace Tests\Feature\Ems;

use App\Ems\Models\AttendeeImport;
use App\Ems\Models\Event;
use App\Ems\Models\Registration;
use App\Ems\Support\EmsPermissions;
use App\Ems\Support\EmsRoles;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AttendeeImportHeaderInspectionTest extends EmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    protected function liveEvent(array $attributes = []): Event
    {
        $category = $this->category(['is_active' => true]);

        return Event::factory()->create(array_merge([
            'category_id' => $category->id,
            'name' => 'Tech Workshop',
            'status' => \App\Ems\Enums\EventStatus::Live,
            'capacity' => 100,
        ], $attributes));
    }

    public function test_guest_cannot_inspect_headers(): void
    {
        $event = $this->liveEvent();
        $file = UploadedFile::fake()->createWithContent('attendees.csv', "Name,Email,Phone\nJohn,john@example.com,555");

        $response = $this->postJson("/api/v1/ems/events/{$event->uuid}/import/inspect-headers", [
            'file' => $file,
        ]);

        $response->assertUnauthorized();
    }

    public function test_user_without_import_permission_cannot_inspect_headers(): void
    {
        $event = $this->liveEvent();
        $user = $this->emsUser(EmsRoles::EVENT_STAFF); // Staff does not have IMPORTS_CREATE
        $file = UploadedFile::fake()->createWithContent('attendees.csv', "Name,Email,Phone\nJohn,john@example.com,555");

        $response = $this->actingAs($user)
            ->postJson("/api/v1/ems/events/{$event->uuid}/import/inspect-headers", [
                'file' => $file,
            ]);

        $response->assertForbidden();
    }

    public function test_authorized_organizer_can_inspect_csv_headers(): void
    {
        $event = $this->liveEvent();
        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $event->update(['organizer_id' => $organizer->id]);

        $file = UploadedFile::fake()->createWithContent('attendees.csv', "Full Name,Email Address,Mobile Phone,Ticket Type\nAlice,alice@example.com,1234567,General");

        $response = $this->actingAs($organizer)
            ->postJson("/api/v1/ems/events/{$event->uuid}/import/inspect-headers", [
                'file' => $file,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'headers' => ['Full Name', 'Email Address', 'Mobile Phone', 'Ticket Type'],
                ],
            ]);
    }

    public function test_authorized_organizer_can_inspect_xlsx_headers(): void
    {
        $event = $this->liveEvent();
        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $event->update(['organizer_id' => $organizer->id]);

        // Create an in-memory XLSX file using PhpSpreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Participant Name', 'Participant Email', 'Status', 'Payment Reference'],
            ['Bob Smith', 'bob@example.com', 'Confirmed', 'TX12345'],
        ]);

        $tempPath = tempnam(sys_get_temp_dir(), 'xlsx_test_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);
        $spreadsheet->disconnectWorksheets();

        $file = new UploadedFile($tempPath, 'participants.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->actingAs($organizer)
            ->postJson("/api/v1/ems/events/{$event->uuid}/import/inspect-headers", [
                'file' => $file,
            ]);

        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'headers' => ['Participant Name', 'Participant Email', 'Status', 'Payment Reference'],
                ],
            ]);
    }

    public function test_headers_are_sanitized_and_truncated(): void
    {
        $event = $this->liveEvent();
        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $event->update(['organizer_id' => $organizer->id]);

        $longHeader = str_repeat('A', 150);
        $dirtyHeader = "  Clean Name \x00\x1F\x7F ";
        $csvContent = "{$dirtyHeader},{$longHeader},,ValidHeader\nval1,val2,val3,val4";

        $file = UploadedFile::fake()->createWithContent('dirty.csv', $csvContent);

        $response = $this->actingAs($organizer)
            ->postJson("/api/v1/ems/events/{$event->uuid}/import/inspect-headers", [
                'file' => $file,
            ]);

        $response->assertOk();
        $headers = $response->json('data.headers');

        $this->assertContains('Clean Name', $headers);
        $this->assertContains(substr($longHeader, 0, 120), $headers);
        $this->assertContains('ValidHeader', $headers);
        $this->assertNotContains('', $headers);
    }

    public function test_header_inspection_does_not_persist_or_create_side_effects(): void
    {
        $event = $this->liveEvent();
        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $event->update(['organizer_id' => $organizer->id]);

        $file = UploadedFile::fake()->createWithContent('attendees.csv', "Name,Email\nJohn,john@example.com");

        $response = $this->actingAs($organizer)
            ->postJson("/api/v1/ems/events/{$event->uuid}/import/inspect-headers", [
                'file' => $file,
            ]);

        $response->assertOk();

        // 1. Zero AttendeeImport rows created
        $this->assertSame(0, AttendeeImport::query()->count());

        // 2. Zero Registrations created
        $this->assertSame(0, Registration::query()->count());

        // 3. Zero files stored in storage disk
        $disk = config('ems.storage.disk', 'local');
        $this->assertEmpty(Storage::disk($disk)->allFiles());
    }

    public function test_invalid_file_extension_fails_validation(): void
    {
        $event = $this->liveEvent();
        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $event->update(['organizer_id' => $organizer->id]);

        $file = UploadedFile::fake()->createWithContent('payload.exe', 'MZ binary content');

        $response = $this->actingAs($organizer)
            ->postJson("/api/v1/ems/events/{$event->uuid}/import/inspect-headers", [
                'file' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }
}
