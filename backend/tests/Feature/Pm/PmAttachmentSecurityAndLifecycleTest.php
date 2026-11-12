<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Pm\Enums\DepartmentRole;
use App\Pm\Enums\ProjectPriority;
use App\Pm\Enums\ProjectRole;
use App\Pm\Enums\ProjectStatus;
use App\Pm\Enums\ProjectVisibility;
use App\Pm\Models\PmAttachment;
use App\Pm\Models\PmAttachmentCleanup;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmProjectMember;
use App\Pm\Models\PmTask;
use App\Pm\Services\DepartmentMembershipService;
use App\Pm\Services\ProjectAccessService;
use App\Services\ApplicationAccessService;
use Database\Seeders\PmDepartmentSeeder;
use Database\Seeders\PmRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PmAttachmentSecurityAndLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected ApplicationAccessService $appAccessService;
    protected DepartmentMembershipService $deptService;
    protected ProjectAccessService $accessService;

    protected Department $itDept;
    protected User $ownerUser;
    protected User $managerUser;
    protected User $contributorUser;
    protected User $viewerUser;
    protected User $externalUser;
    protected PmProject $project;
    protected PmTask $task;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(PmDepartmentSeeder::class);
        $this->seed(PmRolePermissionSeeder::class);

        $this->appAccessService = app(ApplicationAccessService::class);
        $this->deptService = app(DepartmentMembershipService::class);
        $this->accessService = app(ProjectAccessService::class);

        $this->itDept = Department::where('slug', 'it')->firstOrFail();
        $memberRole = Role::firstOrCreate(['slug' => 'member'], ['uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Member']);

        // Owner
        $this->ownerUser = $this->createPmsUser('Project Owner', $memberRole);
        $this->deptService->addMember($this->itDept, $this->ownerUser, DepartmentRole::Lead);

        // Manager
        $this->managerUser = $this->createPmsUser('Project Manager', $memberRole);
        $this->deptService->addMember($this->itDept, $this->managerUser, DepartmentRole::Member);

        // Contributor
        $this->contributorUser = $this->createPmsUser('Project Contributor', $memberRole);
        $this->deptService->addMember($this->itDept, $this->contributorUser, DepartmentRole::Member);

        // Viewer
        $this->viewerUser = $this->createPmsUser('Project Viewer', $memberRole);
        $this->deptService->addMember($this->itDept, $this->viewerUser, DepartmentRole::Member);

        // External User
        $this->externalUser = $this->createPmsUser('External User', $memberRole);

        // Project
        $this->project = PmProject::create([
            'department_id' => $this->itDept->id,
            'name' => 'Security Audit Project',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::High,
            'owner_id' => $this->ownerUser->id,
        ]);

        PmProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->ownerUser->id, 'project_role' => ProjectRole::Owner, 'invitation_status' => 'accepted']);
        PmProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->managerUser->id, 'project_role' => ProjectRole::Manager, 'invitation_status' => 'accepted']);
        PmProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->contributorUser->id, 'project_role' => ProjectRole::Contributor, 'invitation_status' => 'accepted']);
        PmProjectMember::create(['project_id' => $this->project->id, 'user_id' => $this->viewerUser->id, 'project_role' => ProjectRole::Viewer, 'invitation_status' => 'accepted']);

        $this->task = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Security Hardening Task',
            'status' => \App\Pm\Enums\TaskStatus::Todo,
            'priority' => ProjectPriority::High,
            'created_by' => $this->ownerUser->id,
        ]);
    }

    protected function createPmsUser(string $name, Role $role): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'is_active' => true,
        ]);
        $user->roles()->sync([$role->id]);
        $this->appAccessService->grant($user, 'projects', $user);
        return $user;
    }

    // =========================================================================
    // SEC-11-02: Strict Allowlist & MIME Validation Tests
    // =========================================================================

    public function test_allows_valid_document_and_image_uploads(): void
    {
        Sanctum::actingAs($this->contributorUser);

        // 1. PDF
        $pdf = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');
        $resPdf = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $pdf]);
        $resPdf->assertStatus(201);
        $this->assertDatabaseHas('pm_attachments', ['original_name' => 'report.pdf', 'mime_type' => 'application/pdf']);

        // 2. PNG
        $png = UploadedFile::fake()->image('diagram.png', 100, 100);
        $resPng = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $png]);
        $resPng->assertStatus(201);

        // 3. TXT
        $txt = UploadedFile::fake()->create('notes.txt', 10, 'text/plain');
        $resTxt = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $txt]);
        $resTxt->assertStatus(201);

        // 4. CSV
        $csv = UploadedFile::fake()->create('data.csv', 10, 'text/csv');
        $resCsv = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $csv]);
        $resCsv->assertStatus(201);
    }

    public function test_rejects_disallowed_file_extensions(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $disallowedFiles = [
            'payload.exe' => 'application/octet-stream',
            'script.sh' => 'text/x-shellscript',
            'shell.php' => 'application/x-php',
            'batch.bat' => 'application/octet-stream',
            'script.ps1' => 'text/plain',
        ];

        foreach ($disallowedFiles as $filename => $mime) {
            $file = UploadedFile::fake()->create($filename, 10, $mime);
            $res = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $file]);
            $res->assertStatus(422);
            $res->assertJsonFragment([
                'message' => "Files with extension '." . pathinfo($filename, PATHINFO_EXTENSION) . "' are prohibited. Only approved document, image, and data formats are permitted.",
            ]);
        }
    }

    public function test_rejects_prohibited_active_content_and_svg(): void
    {
        Sanctum::actingAs($this->contributorUser);

        // SVG
        $svgFile = UploadedFile::fake()->create('vector.svg', 10, 'image/svg+xml');
        $resSvg = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $svgFile]);
        $resSvg->assertStatus(422);

        // HTML
        $htmlFile = UploadedFile::fake()->create('page.html', 10, 'text/html');
        $resHtml = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $htmlFile]);
        $resHtml->assertStatus(422);

        // XML
        $xmlFile = UploadedFile::fake()->create('config.xml', 10, 'application/xml');
        $resXml = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $xmlFile]);
        $resXml->assertStatus(422);
    }

    public function test_rejects_double_extension_attacks(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $doubleExtensionAttacks = [
            'backdoor.php.jpg' => 'image/jpeg',
            'shell.phtml.png' => 'image/png',
            'exploit.exe.pdf' => 'application/pdf',
            'script.sh.txt' => 'text/plain',
            'trojan.bat.zip' => 'application/zip',
        ];

        foreach ($doubleExtensionAttacks as $filename => $mime) {
            $file = UploadedFile::fake()->create($filename, 20, $mime);
            $res = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $file]);
            $res->assertStatus(422);
            $this->assertTrue(
                str_contains($res->json('message'), 'Double-extension files containing executable extensions are prohibited')
            );
        }
    }

    public function test_rejects_mime_type_mismatch(): void
    {
        Sanctum::actingAs($this->contributorUser);

        // A file named invoice.pdf whose actual detected MIME is text/plain
        $mismatchedFile = UploadedFile::fake()->create('invoice.pdf', 20, 'text/plain');
        $res = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $mismatchedFile]);

        $res->assertStatus(422);
        $this->assertTrue(str_contains($res->json('message'), 'MIME type mismatch'));
    }

    // =========================================================================
    // SEC-11-03: Soft Delete, Physical Retention, Restore & Force Delete Tests
    // =========================================================================

    public function test_soft_delete_retains_physical_file_on_disk(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $pdf = UploadedFile::fake()->create('architecture.pdf', 50, 'application/pdf');
        $uploadRes = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $pdf]);
        $uploadRes->assertStatus(201);

        $attachmentUuid = $uploadRes->json('attachment.uuid');
        $attachment = PmAttachment::where('uuid', $attachmentUuid)->firstOrFail();
        $filePath = $attachment->file_path;

        // Verify physical file was written
        $this->assertTrue(Storage::disk('local')->exists($filePath));

        // Soft delete the attachment as contributor (uploader)
        $deleteRes = $this->deleteJson("/api/v1/pm/attachments/{$attachmentUuid}");
        $deleteRes->assertStatus(200);

        // Verify database row is soft-deleted
        $this->assertSoftDeleted('pm_attachments', ['uuid' => $attachmentUuid]);

        // CRITICAL SEC-11-03: The physical file MUST still exist on disk!
        $this->assertTrue(Storage::disk('local')->exists($filePath), 'Physical file must remain on disk after soft delete.');

        // Verify download route returns 404 for soft-deleted attachment
        $downloadRes = $this->getJson("/api/v1/pm/attachments/{$attachmentUuid}/download");
        $downloadRes->assertStatus(404);
    }

    public function test_restore_endpoint_restores_download_capability(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $pdf = UploadedFile::fake()->create('roadmap.pdf', 50, 'application/pdf');
        $uploadRes = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $pdf]);
        $attachmentUuid = $uploadRes->json('attachment.uuid');

        // Soft delete
        $this->deleteJson("/api/v1/pm/attachments/{$attachmentUuid}")->assertStatus(200);
        $this->assertSoftDeleted('pm_attachments', ['uuid' => $attachmentUuid]);

        // Contributor (non-manager/non-owner) cannot restore
        $forbiddenRestore = $this->postJson("/api/v1/pm/attachments/{$attachmentUuid}/restore");
        $forbiddenRestore->assertStatus(403);

        // Project Manager can restore
        Sanctum::actingAs($this->managerUser);
        $restoreRes = $this->postJson("/api/v1/pm/attachments/{$attachmentUuid}/restore");
        $restoreRes->assertStatus(200);
        $restoreRes->assertJsonFragment(['message' => 'Attachment restored successfully.']);

        // Database record is active again
        $attachment = PmAttachment::where('uuid', $attachmentUuid)->firstOrFail();
        $this->assertNull($attachment->deleted_at);

        // Download is accessible again
        Sanctum::actingAs($this->contributorUser);
        $downloadRes = $this->getJson("/api/v1/pm/attachments/{$attachmentUuid}/download");
        $downloadRes->assertStatus(200);
    }

    public function test_force_delete_permanently_removes_physical_file_and_record(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $pdf = UploadedFile::fake()->create('contract.pdf', 50, 'application/pdf');
        $uploadRes = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $pdf]);
        $attachmentUuid = $uploadRes->json('attachment.uuid');
        $attachment = PmAttachment::where('uuid', $attachmentUuid)->firstOrFail();
        $filePath = $attachment->file_path;

        $this->assertTrue(Storage::disk('local')->exists($filePath));

        // Contributor cannot force-delete
        $forbiddenForce = $this->deleteJson("/api/v1/pm/attachments/{$attachmentUuid}/force");
        $forbiddenForce->assertStatus(403);

        // Project Owner permanently force-deletes
        Sanctum::actingAs($this->ownerUser);
        $forceRes = $this->deleteJson("/api/v1/pm/attachments/{$attachmentUuid}/force");
        $forceRes->assertStatus(200);
        $forceRes->assertJsonFragment(['message' => 'Attachment permanently deleted.']);

        // Database row is completely gone
        $this->assertDatabaseMissing('pm_attachments', ['uuid' => $attachmentUuid]);

        // Physical file on disk is deleted
        $this->assertFalse(Storage::disk('local')->exists($filePath), 'Physical file must be unlinked upon permanent force delete.');
    }

    public function test_force_delete_handles_missing_file_safely(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $pdf = UploadedFile::fake()->create('missing.pdf', 50, 'application/pdf');
        $uploadRes = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $pdf]);
        $attachmentUuid = $uploadRes->json('attachment.uuid');
        $attachment = PmAttachment::where('uuid', $attachmentUuid)->firstOrFail();

        // Delete physical file manually behind the scenes
        Storage::disk('local')->delete($attachment->file_path);

        // Force delete should not throw an exception; it should cleanly remove the database record
        Sanctum::actingAs($this->managerUser);
        $forceRes = $this->deleteJson("/api/v1/pm/attachments/{$attachmentUuid}/force");
        $forceRes->assertStatus(200);

        $this->assertDatabaseMissing('pm_attachments', ['uuid' => $attachmentUuid]);
    }

    protected function createFakeDocx(string $filename = 'contract.docx'): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new \ZipArchive();
        $zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"></w:document>');
        $zip->close();

        $content = file_get_contents($tempPath);
        @unlink($tempPath);

        return UploadedFile::fake()->createWithContent($filename, $content);
    }

    protected function createFakeArbitraryZip(string $filename = 'fake.docx'): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new \ZipArchive();
        $zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('random.txt', 'This is just a regular text file inside a zip.');
        $zip->close();

        $content = file_get_contents($tempPath);
        @unlink($tempPath);

        return UploadedFile::fake()->createWithContent($filename, $content);
    }

    public function test_rejects_filenames_with_trailing_dots_or_spaces(): void
    {
        Sanctum::actingAs($this->contributorUser);

        // Trailing dot
        $dotFile = UploadedFile::fake()->create('report.pdf.', 20, 'application/pdf');
        $resDot = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $dotFile]);
        $resDot->assertStatus(422);

        // Trailing space
        $spaceFile = UploadedFile::fake()->create('report.pdf ', 20, 'application/pdf');
        $resSpace = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $spaceFile]);
        $resSpace->assertStatus(422);
    }

    public function test_accepts_uppercase_and_mixed_case_allowed_extensions(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $pdfUpper = UploadedFile::fake()->create('REPORT.PDF', 20, 'application/pdf');
        $res = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $pdfUpper]);
        $res->assertStatus(201);
    }

    public function test_rejects_mixed_case_double_extension_attacks(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $mixedAttack = UploadedFile::fake()->create('backdoor.PHP.pdf', 20, 'application/pdf');
        $res = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $mixedAttack]);

        $res->assertStatus(422);
        $this->assertTrue(str_contains($res->json('message'), 'Double-extension files'));
    }

    public function test_accepts_valid_office_docx_structure(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $validDocx = $this->createFakeDocx('valid_spec.docx');
        $res = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $validDocx]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('pm_attachments', ['original_name' => 'valid_spec.docx']);
    }

    public function test_rejects_arbitrary_zip_disguised_as_docx(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $fakeDocx = $this->createFakeArbitraryZip('malicious.docx');
        $res = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $fakeDocx]);

        $res->assertStatus(422);
        $this->assertTrue(str_contains($res->json('message'), 'not a valid docx document structure'));
    }

    public function test_rejects_active_html_smuggled_in_text_files(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $smuggledFile = UploadedFile::fake()->createWithContent(
            'readme.txt',
            "<!DOCTYPE html><html><body><script>alert('xss');</script></body></html>"
        );

        $res = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $smuggledFile]);

        $res->assertStatus(422);
        $this->assertTrue(
            str_contains($res->json('message'), 'prohibited') ||
            str_contains($res->json('message'), 'active content')
        );
    }

    public function test_download_response_headers_contain_nosniff_and_attachment_disposition(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $pdf = UploadedFile::fake()->create('safe_report.pdf', 30, 'application/pdf');
        $uploadRes = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $pdf]);
        $attachmentUuid = $uploadRes->json('attachment.uuid');

        $downloadRes = $this->getJson("/api/v1/pm/attachments/{$attachmentUuid}/download");
        $downloadRes->assertStatus(200);

        $downloadRes->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertTrue(str_contains((string) $downloadRes->headers->get('Content-Disposition'), 'attachment; filename="safe_report.pdf"'));
        $this->assertEquals('application/pdf', $downloadRes->headers->get('Content-Type'));
    }

    public function test_restoring_already_restored_attachment_returns_422(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $pdf = UploadedFile::fake()->create('audit.pdf', 30, 'application/pdf');
        $uploadRes = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $pdf]);
        $attachmentUuid = $uploadRes->json('attachment.uuid');

        // Soft delete
        $this->deleteJson("/api/v1/pm/attachments/{$attachmentUuid}")->assertStatus(200);

        // Restore once as manager
        Sanctum::actingAs($this->managerUser);
        $this->postJson("/api/v1/pm/attachments/{$attachmentUuid}/restore")->assertStatus(200);

        // Repeated restore attempt
        $repeatedRestore = $this->postJson("/api/v1/pm/attachments/{$attachmentUuid}/restore");
        $repeatedRestore->assertStatus(422);
        $repeatedRestore->assertJsonFragment(['message' => 'Attachment is not deleted.']);
    }

    public function test_restoring_permanently_deleted_attachment_returns_404(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $pdf = UploadedFile::fake()->create('purged.pdf', 30, 'application/pdf');
        $uploadRes = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $pdf]);
        $attachmentUuid = $uploadRes->json('attachment.uuid');

        // Force delete as owner
        Sanctum::actingAs($this->ownerUser);
        $this->deleteJson("/api/v1/pm/attachments/{$attachmentUuid}/force")->assertStatus(200);

        // Attempt restore on permanently deleted record
        $restoreRes = $this->postJson("/api/v1/pm/attachments/{$attachmentUuid}/restore");
        $restoreRes->assertStatus(404);
    }

    public function test_repeated_soft_delete_returns_404(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $pdf = UploadedFile::fake()->create('double_delete.pdf', 30, 'application/pdf');
        $uploadRes = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $pdf]);
        $attachmentUuid = $uploadRes->json('attachment.uuid');

        // First delete succeeds
        $this->deleteJson("/api/v1/pm/attachments/{$attachmentUuid}")->assertStatus(200);

        // Second delete fails with 404 because attachment is already trashed
        $secondDelete = $this->deleteJson("/api/v1/pm/attachments/{$attachmentUuid}");
        $secondDelete->assertStatus(404);
    }

    public function test_repeated_force_delete_returns_404(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $pdf = UploadedFile::fake()->create('double_force.pdf', 30, 'application/pdf');
        $uploadRes = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $pdf]);
        $attachmentUuid = $uploadRes->json('attachment.uuid');

        // First force delete succeeds
        Sanctum::actingAs($this->ownerUser);
        $this->deleteJson("/api/v1/pm/attachments/{$attachmentUuid}/force")->assertStatus(200);

        // Second force delete fails with 404
        $secondForce = $this->deleteJson("/api/v1/pm/attachments/{$attachmentUuid}/force");
        $secondForce->assertStatus(404);
    }

    public function test_missing_physical_file_during_download_returns_404(): void
    {
        Sanctum::actingAs($this->contributorUser);

        $pdf = UploadedFile::fake()->create('vanished.pdf', 30, 'application/pdf');
        $uploadRes = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $pdf]);
        $attachmentUuid = $uploadRes->json('attachment.uuid');
        $attachment = PmAttachment::where('uuid', $attachmentUuid)->firstOrFail();

        // Physically delete file from disk behind the scenes
        Storage::disk('local')->delete($attachment->file_path);

        $downloadRes = $this->getJson("/api/v1/pm/attachments/{$attachmentUuid}/download");
        $downloadRes->assertStatus(404);
        $downloadRes->assertJsonFragment(['message' => 'Attachment file not found in storage.']);
    }

    public function test_cross_project_restore_and_force_delete_forbidden(): void
    {
        $otherProject = PmProject::create([
            'department_id' => $this->itDept->id,
            'name' => 'Other Project B',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Low,
            'owner_id' => $this->externalUser->id,
        ]);

        // User who is manager on other project B, but NOT on project A
        $otherManager = $this->createPmsUser('Other Manager', Role::firstOrCreate(['slug' => 'member'], ['uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Member']));
        $this->deptService->addMember($this->itDept, $otherManager, DepartmentRole::Member);
        PmProjectMember::create([
            'project_id' => $otherProject->id,
            'user_id' => $otherManager->id,
            'project_role' => ProjectRole::Manager,
            'invitation_status' => 'accepted',
        ]);

        // Contributor creates attachment on Project A
        Sanctum::actingAs($this->contributorUser);
        $pdf = UploadedFile::fake()->create('projectA_file.pdf', 30, 'application/pdf');
        $uploadRes = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $pdf]);
        $attachmentUuid = $uploadRes->json('attachment.uuid');
        $this->deleteJson("/api/v1/pm/attachments/{$attachmentUuid}")->assertStatus(200);

        // otherManager attempts to restore Project A's attachment
        Sanctum::actingAs($otherManager);
        $restoreRes = $this->postJson("/api/v1/pm/attachments/{$attachmentUuid}/restore");
        $restoreRes->assertStatus(403);

        // otherManager attempts to force delete Project A's attachment
        $forceRes = $this->deleteJson("/api/v1/pm/attachments/{$attachmentUuid}/force");
        $forceRes->assertStatus(403);
    }

    public function test_force_delete_success_deletes_db_record_physical_file_and_resolves_cleanup_intent(): void
    {
        Sanctum::actingAs($this->contributorUser);
        $pdf = UploadedFile::fake()->create('clean_force.pdf', 30, 'application/pdf');
        $uploadRes = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $pdf]);
        $attachmentUuid = $uploadRes->json('attachment.uuid');
        $attachment = PmAttachment::where('uuid', $attachmentUuid)->firstOrFail();
        $filePath = $attachment->file_path;

        $this->assertTrue(Storage::disk('local')->exists($filePath));

        Sanctum::actingAs($this->ownerUser);
        $forceRes = $this->deleteJson("/api/v1/pm/attachments/{$attachmentUuid}/force");
        $forceRes->assertStatus(200);
        $forceRes->assertJsonFragment(['cleanup_status' => 'completed']);

        // DB record is permanently deleted
        $this->assertNull(PmAttachment::withTrashed()->find($attachment->id));
        // Physical file is deleted from disk
        $this->assertFalse(Storage::disk('local')->exists($filePath));
        // Cleanup queue record is deleted / completed
        $this->assertFalse(PmAttachmentCleanup::where('file_path', $filePath)->exists());
    }

    public function test_force_delete_database_failure_rolls_back_and_preserves_physical_file(): void
    {
        Sanctum::actingAs($this->contributorUser);
        $pdf = UploadedFile::fake()->create('rollback_force.pdf', 30, 'application/pdf');
        $uploadRes = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $pdf]);
        $attachmentUuid = $uploadRes->json('attachment.uuid');
        $attachment = PmAttachment::where('uuid', $attachmentUuid)->firstOrFail();
        $filePath = $attachment->file_path;

        // Register a deleting event listener that throws an exception during DB transaction
        PmAttachment::deleting(function ($model) use ($attachment) {
            if ($model->id === $attachment->id && $model->isForceDeleting()) {
                throw new \RuntimeException('Simulated database deadlock/failure during force-delete');
            }
        });

        Sanctum::actingAs($this->ownerUser);
        try {
            $this->deleteJson("/api/v1/pm/attachments/{$attachmentUuid}/force");
        } catch (\Throwable $e) {
            // Expected simulation exception
        }

        // DB record was preserved because transaction rolled back
        $this->assertNotNull(PmAttachment::withTrashed()->find($attachment->id));
        // Physical file was NOT unlinked
        $this->assertTrue(Storage::disk('local')->exists($filePath));
        // Cleanup queue record was NOT created
        $this->assertFalse(PmAttachmentCleanup::where('file_path', $filePath)->exists());
    }

    public function test_force_delete_storage_failure_persists_durable_cleanup_intent(): void
    {
        Sanctum::actingAs($this->contributorUser);
        $pdf = UploadedFile::fake()->create('disk_failure.pdf', 30, 'application/pdf');
        $uploadRes = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $pdf]);
        $attachmentUuid = $uploadRes->json('attachment.uuid');
        $attachment = PmAttachment::where('uuid', $attachmentUuid)->firstOrFail();
        $filePath = $attachment->file_path;

        // Create a mock storage disk where delete throws an exception
        $mockDisk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $mockDisk->shouldReceive('exists')->with($filePath)->andReturn(true);
        $mockDisk->shouldReceive('delete')->with($filePath)->andThrow(new \RuntimeException('Disk I/O read-only filesystem lock'));
        Storage::set('local', $mockDisk);

        Sanctum::actingAs($this->ownerUser);
        $forceRes = $this->deleteJson("/api/v1/pm/attachments/{$attachmentUuid}/force");
        $forceRes->assertStatus(200);
        $forceRes->assertJsonFragment(['cleanup_status' => 'pending_reconciliation']);

        // DB record was deleted
        $this->assertNull(PmAttachment::withTrashed()->find($attachment->id));

        // Durable cleanup record was persisted with failed status and error details
        $cleanup = PmAttachmentCleanup::where('file_path', $filePath)->first();
        $this->assertNotNull($cleanup);
        $this->assertEquals('failed', $cleanup->status);
        $this->assertGreaterThanOrEqual(1, $cleanup->attempts);
        $this->assertStringContainsString('Disk I/O read-only filesystem lock', (string) $cleanup->last_error);

        // Reset Storage fake
        Storage::fake('local');
    }

    public function test_reconciliation_retries_and_cleans_failed_physical_deletions(): void
    {
        $filePath = "pm_attachments/{$this->project->uuid}/stuck_orphan.pdf";
        Storage::disk('local')->put($filePath, 'orphaned binary data');

        $cleanup = PmAttachmentCleanup::create([
            'project_id' => $this->project->id,
            'file_path' => $filePath,
            'disk' => 'local',
            'attempts' => 1,
            'last_error' => 'Previous network timeout',
            'status' => 'failed',
        ]);

        $this->assertTrue(Storage::disk('local')->exists($filePath));

        // Project manager triggers reconciliation
        Sanctum::actingAs($this->ownerUser);
        $reconcileRes = $this->postJson('/api/v1/pm/attachments/cleanup', [
            'project_uuid' => $this->project->uuid,
        ]);

        $reconcileRes->assertStatus(200);
        $reconcileRes->assertJsonFragment(['message' => 'Attachment reconciliation completed successfully.']);
        $this->assertGreaterThanOrEqual(1, $reconcileRes->json('reaped_files_count'));

        // Physical file is now unlinked
        $this->assertFalse(Storage::disk('local')->exists($filePath));
        // Cleanup queue record is cleared
        $this->assertFalse(PmAttachmentCleanup::where('id', $cleanup->id)->exists());
    }

    public function test_force_delete_succeeds_when_physical_file_already_missing(): void
    {
        Sanctum::actingAs($this->contributorUser);
        $pdf = UploadedFile::fake()->create('already_missing.pdf', 30, 'application/pdf');
        $uploadRes = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $pdf]);
        $attachmentUuid = $uploadRes->json('attachment.uuid');
        $attachment = PmAttachment::where('uuid', $attachmentUuid)->firstOrFail();
        $filePath = $attachment->file_path;

        // Manually delete physical file before force-delete is invoked
        Storage::disk('local')->delete($filePath);
        $this->assertFalse(Storage::disk('local')->exists($filePath));

        Sanctum::actingAs($this->ownerUser);
        $forceRes = $this->deleteJson("/api/v1/pm/attachments/{$attachmentUuid}/force");
        $forceRes->assertStatus(200);
        $forceRes->assertJsonFragment(['cleanup_status' => 'completed']);

        // DB record is deleted cleanly without throwing exceptions
        $this->assertNull(PmAttachment::withTrashed()->find($attachment->id));
        $this->assertFalse(PmAttachmentCleanup::where('file_path', $filePath)->exists());
    }

    public function test_reconciliation_protects_active_and_soft_deleted_attachments(): void
    {
        // 1. Create active attachment
        Sanctum::actingAs($this->contributorUser);
        $pdfActive = UploadedFile::fake()->create('active_file.pdf', 30, 'application/pdf');
        $uploadActive = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $pdfActive]);
        $activeAttachment = PmAttachment::where('uuid', $uploadActive->json('attachment.uuid'))->firstOrFail();

        // 2. Create soft-deleted attachment
        $pdfSoft = UploadedFile::fake()->create('soft_deleted_file.pdf', 30, 'application/pdf');
        $uploadSoft = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/attachments", ['file' => $pdfSoft]);
        $softAttachmentUuid = $uploadSoft->json('attachment.uuid');
        $softAttachment = PmAttachment::where('uuid', $softAttachmentUuid)->firstOrFail();
        $this->deleteJson("/api/v1/pm/attachments/{$softAttachmentUuid}")->assertStatus(200);

        // 3. Create an unindexed orphan file on disk
        $orphanPath = "pm_attachments/{$this->project->uuid}/unindexed_orphan.pdf";
        Storage::disk('local')->put($orphanPath, 'orphan data');

        $this->assertTrue(Storage::disk('local')->exists($activeAttachment->file_path));
        $this->assertTrue(Storage::disk('local')->exists($softAttachment->file_path));
        $this->assertTrue(Storage::disk('local')->exists($orphanPath));

        // 4. Run reconciliation
        Sanctum::actingAs($this->ownerUser);
        $cleanupRes = $this->postJson('/api/v1/pm/attachments/cleanup', [
            'project_uuid' => $this->project->uuid,
        ]);
        $cleanupRes->assertStatus(200);

        // Invariant: Active file remains untouched on disk
        $this->assertTrue(Storage::disk('local')->exists($activeAttachment->file_path));
        // Invariant: Soft-deleted file remains untouched on disk (awaiting restore)
        $this->assertTrue(Storage::disk('local')->exists($softAttachment->file_path));
        // Orphaned file was reaped
        $this->assertFalse(Storage::disk('local')->exists($orphanPath));
    }

    public function test_unauthorized_user_cannot_trigger_attachment_cleanup(): void
    {
        // Department viewer attempts cleanup
        Sanctum::actingAs($this->viewerUser);
        $viewerRes = $this->postJson('/api/v1/pm/attachments/cleanup', [
            'project_uuid' => $this->project->uuid,
        ]);
        $viewerRes->assertStatus(403);

        // Contributor without manager/owner role attempts cleanup
        Sanctum::actingAs($this->contributorUser);
        $contribRes = $this->postJson('/api/v1/pm/attachments/cleanup', [
            'project_uuid' => $this->project->uuid,
        ]);
        $contribRes->assertStatus(403);

        // External user without project access attempts cleanup
        Sanctum::actingAs($this->externalUser);
        $externalRes = $this->postJson('/api/v1/pm/attachments/cleanup', [
            'project_uuid' => $this->project->uuid,
        ]);
        $externalRes->assertStatus(403);
    }

    public function test_artisan_clean_attachments_command_reconciles_orphaned_files(): void
    {
        $orphanPath = "pm_attachments/{$this->project->uuid}/command_orphan.pdf";
        Storage::disk('local')->put($orphanPath, 'orphan content');

        $cleanup = PmAttachmentCleanup::create([
            'project_id' => $this->project->id,
            'file_path' => $orphanPath,
            'disk' => 'local',
            'attempts' => 1,
            'status' => 'failed',
        ]);

        $this->assertTrue(Storage::disk('local')->exists($orphanPath));

        // Run artisan command
        $exitCode = \Illuminate\Support\Facades\Artisan::call('pm:clean-attachments', [
            '--project' => $this->project->uuid,
        ]);
        $this->assertEquals(0, $exitCode);

        // Orphaned file was reaped from storage
        $this->assertFalse(Storage::disk('local')->exists($orphanPath));
        // Cleanup queue record was removed
        $this->assertFalse(PmAttachmentCleanup::where('id', $cleanup->id)->exists());
    }
}
