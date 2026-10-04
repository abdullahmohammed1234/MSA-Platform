<?php

namespace Tests\Feature\Ems;

use App\Ems\Models\Event;
use App\Ems\Models\EventDocument;
use App\Ems\Support\EmsRoles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class EmsEventDocumentsTest extends EmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('ems_documents');
        config(['filesystems.disks.ems_documents.root' => Storage::disk('ems_documents')->path('')]);
    }

    private function createValidPdfFile(string $name = 'test.pdf', int $sizeKb = 50): UploadedFile
    {
        $content = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
        $padding = str_repeat(' ', max(0, ($sizeKb * 1024) - strlen($content)));

        return UploadedFile::fake()->createWithContent($name, $content . $padding);
    }

    public function test_admin_can_upload_event_document_pdf(): void
    {
        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = $this->event();
        $file = $this->createValidPdfFile('itinerary.pdf');

        $response = $this->actingAsEms($admin)
            ->postJson($this->url("events/{$event->uuid}/documents"), [
                'file' => $file,
                'name' => 'Event Itinerary',
                'document_type' => 'itinerary',
                'description' => 'Detailed schedule for the event.',
                'sort_order' => 1,
            ]);

        $this->assertSuccessEnvelope($response);
        $response->assertStatus(201);
        $response->assertJsonPath('data.document.name', 'Event Itinerary');
        $response->assertJsonPath('data.document.document_type', 'itinerary');
        $response->assertJsonPath('data.document.original_filename', 'itinerary.pdf');

        $docUuid = $response->json('data.document.uuid');
        $rawToken = $response->json('data.raw_token');
        $qrUrl = $response->json('data.qr_url');

        $this->assertNotEmpty($rawToken);
        $this->assertStringContainsString('/event-documents/' . $docUuid . '/' . $rawToken, $qrUrl);

        $document = EventDocument::where('uuid', $docUuid)->firstOrFail();
        $this->assertSame(hash('sha256', $rawToken), $document->access_token_hash);
        $this->assertNotEquals($rawToken, $document->access_token_hash);
        $this->assertTrue(Storage::disk('ems_documents')->exists($document->storage_path));
    }

    public function test_non_pdf_file_uploads_are_rejected(): void
    {
        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = $this->event();
        $txtFile = UploadedFile::fake()->create('script.txt', 10, 'text/plain');

        $response = $this->actingAsEms($admin)
            ->postJson($this->url("events/{$event->uuid}/documents"), [
                'file' => $txtFile,
                'name' => 'Invalid Doc',
                'document_type' => 'other',
            ]);

        $this->assertErrorEnvelope($response);
        $response->assertStatus(422);
    }

    public function test_spoofed_executable_pdf_headers_are_rejected(): void
    {
        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = $this->event();
        $fakePdf = UploadedFile::fake()->createWithContent('malicious.pdf', '<?php echo "evil"; ?>');

        $response = $this->actingAsEms($admin)
            ->postJson($this->url("events/{$event->uuid}/documents"), [
                'file' => $fakePdf,
                'name' => 'Fake PDF',
                'document_type' => 'other',
            ]);

        $this->assertErrorEnvelope($response);
        $response->assertStatus(422);
    }

    public function test_secure_document_access_endpoint_serves_private_pdf_inline(): void
    {
        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = $this->event();
        $file = $this->createValidPdfFile('menu.pdf');

        $uploadRes = $this->actingAsEms($admin)
            ->postJson($this->url("events/{$event->uuid}/documents"), [
                'file' => $file,
                'name' => 'Dinner Menu',
                'document_type' => 'menu',
            ]);

        $docUuid = $uploadRes->json('data.document.uuid');
        $rawToken = $uploadRes->json('data.raw_token');

        // Access via public web endpoint /event-documents/{uuid}/{token}
        $accessRes = $this->get("/event-documents/{$docUuid}/{$rawToken}");

        $accessRes->assertStatus(200);
        $accessRes->assertHeader('Content-Type', 'application/pdf');
        $accessRes->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('inline;', (string) $accessRes->headers->get('Content-Disposition'));
        $this->assertStringContainsString('%PDF-1.4', $accessRes->getContent());
    }

    public function test_invalid_access_token_returns_404(): void
    {
        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = $this->event();
        $file = $this->createValidPdfFile('map.pdf');

        $uploadRes = $this->actingAsEms($admin)
            ->postJson($this->url("events/{$event->uuid}/documents"), [
                'file' => $file,
                'name' => 'Venue Map',
                'document_type' => 'map',
            ]);

        $docUuid = $uploadRes->json('data.document.uuid');

        $accessRes = $this->get("/event-documents/{$docUuid}/invalid-guess-token-123");
        $accessRes->assertStatus(404);
    }

    public function test_invalid_document_uuid_returns_404(): void
    {
        $accessRes = $this->get('/event-documents/00000000-0000-0000-0000-000000000000/sometoken123');
        $accessRes->assertStatus(404);
    }

    public function test_inactive_document_returns_404(): void
    {
        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = $this->event();
        $file = $this->createValidPdfFile('info.pdf');

        $uploadRes = $this->actingAsEms($admin)
            ->postJson($this->url("events/{$event->uuid}/documents"), [
                'file' => $file,
                'name' => 'General Info',
                'document_type' => 'information',
            ]);

        $docUuid = $uploadRes->json('data.document.uuid');
        $rawToken = $uploadRes->json('data.raw_token');

        // Deactivate document
        $this->actingAsEms($admin)
            ->patchJson($this->url("events/{$event->uuid}/documents/{$docUuid}"), [
                'is_active' => false,
            ])
            ->assertStatus(200);

        $accessRes = $this->get("/event-documents/{$docUuid}/{$rawToken}");
        $accessRes->assertStatus(404);
    }

    public function test_soft_deleted_document_returns_404(): void
    {
        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = $this->event();
        $file = $this->createValidPdfFile('schedule.pdf');

        $uploadRes = $this->actingAsEms($admin)
            ->postJson($this->url("events/{$event->uuid}/documents"), [
                'file' => $file,
                'name' => 'Schedule',
                'document_type' => 'schedule',
            ]);

        $docUuid = $uploadRes->json('data.document.uuid');
        $rawToken = $uploadRes->json('data.raw_token');

        // Delete document
        $this->actingAsEms($admin)
            ->deleteJson($this->url("events/{$event->uuid}/documents/{$docUuid}"))
            ->assertStatus(200);

        $accessRes = $this->get("/event-documents/{$docUuid}/{$rawToken}");
        $accessRes->assertStatus(404);
    }

    public function test_admin_can_replace_pdf_file_and_keep_qr_link_stable(): void
    {
        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = $this->event();
        $v1File = $this->createValidPdfFile('menu_v1.pdf');

        $uploadRes = $this->actingAsEms($admin)
            ->postJson($this->url("events/{$event->uuid}/documents"), [
                'file' => $v1File,
                'name' => 'Dinner Menu',
                'document_type' => 'menu',
            ]);

        $docUuid = $uploadRes->json('data.document.uuid');
        $rawToken = $uploadRes->json('data.raw_token');

        // Replace PDF file
        $v2File = UploadedFile::fake()->createWithContent('menu_v2.pdf', "%PDF-1.4 REPLACED_PDF_VERSION_2 %%EOF");
        $replaceRes = $this->actingAsEms($admin)
            ->postJson($this->url("events/{$event->uuid}/documents/{$docUuid}/replace"), [
                'file' => $v2File,
            ]);

        $this->assertSuccessEnvelope($replaceRes);
        $replaceRes->assertJsonPath('data.original_filename', 'menu_v2.pdf');

        // Old QR token link still serves the new PDF content!
        $accessRes = $this->get("/event-documents/{$docUuid}/{$rawToken}");
        $accessRes->assertStatus(200);
        $this->assertStringContainsString('REPLACED_PDF_VERSION_2', $accessRes->getContent());
    }

    public function test_admin_can_rotate_access_token_invalidating_old_qr(): void
    {
        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = $this->event();
        $file = $this->createValidPdfFile('program.pdf');

        $uploadRes = $this->actingAsEms($admin)
            ->postJson($this->url("events/{$event->uuid}/documents"), [
                'file' => $file,
                'name' => 'Event Program',
                'document_type' => 'program',
            ]);

        $docUuid = $uploadRes->json('data.document.uuid');
        $oldToken = $uploadRes->json('data.raw_token');

        // Verify old token works before rotation
        $this->get("/event-documents/{$docUuid}/{$oldToken}")->assertStatus(200);

        // Rotate token
        $rotateRes = $this->actingAsEms($admin)
            ->postJson($this->url("events/{$event->uuid}/documents/{$docUuid}/rotate-access"));

        $this->assertSuccessEnvelope($rotateRes);
        $newToken = $rotateRes->json('data.raw_token');
        $this->assertNotEquals($oldToken, $newToken);

        // Old token must return 404
        $this->get("/event-documents/{$docUuid}/{$oldToken}")->assertStatus(404);

        // New token must return 200
        $this->get("/event-documents/{$docUuid}/{$newToken}")->assertStatus(200);
    }

    public function test_cross_event_idor_protection(): void
    {
        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $eventA = $this->event();
        $eventB = $this->event();

        $file = $this->createValidPdfFile('docA.pdf');
        $uploadRes = $this->actingAsEms($admin)
            ->postJson($this->url("events/{$eventA->uuid}/documents"), [
                'file' => $file,
                'name' => 'Event A Document',
                'document_type' => 'other',
            ]);

        $docUuid = $uploadRes->json('data.document.uuid');

        // Admin tries to access/edit Event A document under Event B URL context
        $response = $this->actingAsEms($admin)
            ->patchJson($this->url("events/{$eventB->uuid}/documents/{$docUuid}"), [
                'name' => 'Hacked Name',
            ]);

        $response->assertStatus(404);
    }

    public function test_unauthorized_users_cannot_manage_documents(): void
    {
        $event = $this->event();
        $file = $this->createValidPdfFile('doc.pdf');

        // Unauthenticated request
        $this->postJson($this->url("events/{$event->uuid}/documents"), [
            'file' => $file,
            'name' => 'Unauthorized Upload',
            'document_type' => 'other',
        ])->assertStatus(401);

        // Non-admin user (attendee)
        $attendee = $this->emsUser(EmsRoles::ATTENDEE);
        $this->actingAsEms($attendee)
            ->postJson($this->url("events/{$event->uuid}/documents"), [
                'file' => $file,
                'name' => 'Unauthorized Upload',
                'document_type' => 'other',
            ])->assertStatus(403);
    }

    public function test_full_end_to_end_document_lifecycle(): void
    {
        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = $this->event();

        // 1. Upload
        $file1 = $this->createValidPdfFile('itinerary_v1.pdf');
        $createRes = $this->actingAsEms($admin)
            ->postJson($this->url("events/{$event->uuid}/documents"), [
                'file' => $file1,
                'name' => 'Full Itinerary',
                'document_type' => 'itinerary',
                'description' => 'E2E test doc',
            ]);
        $createRes->assertStatus(201);
        $docUuid = $createRes->json('data.document.uuid');
        $token1 = $createRes->json('data.raw_token');

        // 2. Fetch QR
        $qrRes = $this->actingAsEms($admin)
            ->getJson($this->url("events/{$event->uuid}/documents/{$docUuid}/qr?token={$token1}"));
        $qrRes->assertStatus(200);
        $this->assertNotEmpty($qrRes->json('data.qr_data_uri'));

        // 3. Access PDF publicly
        $this->get("/event-documents/{$docUuid}/{$token1}")->assertStatus(200);

        // 4. Replace file
        $file2 = UploadedFile::fake()->createWithContent('itinerary_v2.pdf', "%PDF-1.4 NEW_CONTENT %%EOF");
        $this->actingAsEms($admin)
            ->postJson($this->url("events/{$event->uuid}/documents/{$docUuid}/replace"), [
                'file' => $file2,
            ])->assertStatus(200);

        // 5. Access updated file via same QR token
        $access2 = $this->get("/event-documents/{$docUuid}/{$token1}");
        $access2->assertStatus(200);
        $this->assertStringContainsString('NEW_CONTENT', $access2->getContent());

        // 6. Rotate token
        $rotateRes = $this->actingAsEms($admin)
            ->postJson($this->url("events/{$event->uuid}/documents/{$docUuid}/rotate-access"));
        $token2 = $rotateRes->json('data.raw_token');

        $this->get("/event-documents/{$docUuid}/{$token1}")->assertStatus(404);
        $this->get("/event-documents/{$docUuid}/{$token2}")->assertStatus(200);

        // 7. Delete document
        $this->actingAsEms($admin)
            ->deleteJson($this->url("events/{$event->uuid}/documents/{$docUuid}"))
            ->assertStatus(200);

        // 8. Access after deletion returns 404
        $this->get("/event-documents/{$docUuid}/{$token2}")->assertStatus(404);
    }
}
