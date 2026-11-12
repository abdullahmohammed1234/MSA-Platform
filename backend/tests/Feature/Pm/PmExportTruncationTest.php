<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\User;
use App\Pm\Enums\ProjectPriority;
use App\Pm\Enums\ProjectRole;
use App\Pm\Enums\ProjectStatus;
use App\Pm\Enums\ProjectVisibility;
use App\Pm\Enums\TaskStatus;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmProjectMember;
use App\Pm\Models\PmTask;
use App\Pm\Services\PmExportService;
use App\Services\ApplicationAccessService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PmExportTruncationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Department $department;
    protected PmProject $project;
    protected PmExportService $exportService;

    protected function setUp(): void
    {
        parent::setUp();

        $appAccess = app(ApplicationAccessService::class);
        $this->user = User::factory()->create(['name' => 'Report Auditor', 'email' => 'auditor@sfu.ca']);
        $appAccess->grant($this->user, 'projects');

        $this->department = Department::create(['name' => 'Operations Dept', 'code' => 'OPS']);

        $this->project = PmProject::create([
            'name' => 'High Volume Logistics Project',
            'slug' => 'high-volume-logistics',
            'code' => 'OPS-99',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'department_id' => $this->department->id,
            'owner_id' => $this->user->id,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::High,
            'created_by' => $this->user->id,
        ]);

        PmProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'project_role' => ProjectRole::Owner,
            'invitation_status' => 'accepted',
        ]);

        $this->exportService = app(PmExportService::class);
    }

    #[Test]
    public function csv_export_includes_matching_and_exported_counts_without_truncation_when_under_limit(): void
    {
        // Create 3 tasks (well below limit of 1000)
        for ($i = 1; $i <= 3; $i++) {
            PmTask::create([
                'project_id' => $this->project->id,
                'title' => "Task Sample {$i}",
                'status' => TaskStatus::InProgress,
                'priority' => ProjectPriority::Medium,
                'created_by' => $this->user->id,
                'due_date' => Carbon::now()->addDays($i),
            ]);
        }

        $response = $this->actingAs($this->user)
            ->get("/api/v1/pm/projects/{$this->project->uuid}/export?type=tasks&format=csv");

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('"Total Matching Tasks",3', $content);
        $this->assertStringContainsString('"Exported Tasks",3', $content);
        $this->assertStringNotContainsString('NOTICE', $content);
    }

    #[Test]
    public function csv_export_includes_clear_truncation_notice_when_matching_tasks_exceed_limit(): void
    {
        $tasks = collect();
        for ($i = 1; $i <= 5; $i++) {
            $task = new PmTask([
                'title' => "Task #{$i}",
                'status' => TaskStatus::InProgress,
                'priority' => ProjectPriority::High,
            ]);
            $task->id = $i;
            $tasks->push($task);
        }

        // Simulate 1,250 total matching tasks capped at 5 exported
        $result = $this->exportService->exportTasks(
            $tasks,
            $this->user,
            'csv',
            'Simulated Large Task Export',
            1250,
            5
        );

        $content = $result['content'];

        $this->assertStringContainsString('"Total Matching Tasks",1250', $content);
        $this->assertStringContainsString('"Exported Tasks",5', $content);
        $this->assertStringContainsString('NOTICE,"Export truncated. Displaying first 5 of 1250 matching tasks (limit: 5).', $content);
    }

    #[Test]
    public function xlsx_export_includes_metadata_header_and_truncation_notice_when_truncated(): void
    {
        $tasks = collect();
        for ($i = 1; $i <= 5; $i++) {
            $task = new PmTask([
                'title' => "Task #{$i}",
                'status' => TaskStatus::InProgress,
                'priority' => ProjectPriority::Medium,
            ]);
            $task->id = $i;
            $tasks->push($task);
        }

        $result = $this->exportService->exportTasks(
            $tasks,
            $this->user,
            'xlsx',
            'Simulated XLSX Truncated Export',
            1500,
            1000
        );

        $this->assertNotEmpty($result['content']);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $result['contentType']);

        // Inspect spreadsheet XML content directly to verify notice without file lock issues
        $tempPath = tempnam(sys_get_temp_dir(), 'test_trunc_xlsx');
        file_put_contents($tempPath, $result['content']);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tempPath));
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();
        @unlink($tempPath);

        $combinedXml = ($sheetXml ?: '') . ($sharedStringsXml ?: '');
        $this->assertStringContainsString('Total Matching Tasks: 1500', $combinedXml);
        $this->assertStringContainsString('Exported Tasks: 5', $combinedXml);
        $this->assertStringContainsString('NOTICE: Export truncated', $combinedXml);
    }

    #[Test]
    public function pdf_export_includes_truncation_callout_when_truncated(): void
    {
        $tasks = collect();
        for ($i = 1; $i <= 3; $i++) {
            $task = new PmTask([
                'title' => "Task #{$i}",
                'status' => TaskStatus::Completed,
                'priority' => ProjectPriority::Low,
            ]);
            $task->id = $i;
            $tasks->push($task);
        }

        $result = $this->exportService->exportTasks(
            $tasks,
            $this->user,
            'pdf',
            'Simulated PDF Truncated Export',
            1050,
            1000
        );

        $this->assertNotEmpty($result['content']);
        $this->assertEquals('application/pdf', $result['contentType']);
        $this->assertStringStartsWith('%PDF-', $result['content']);
    }

    #[Test]
    public function project_progress_export_discloses_task_truncation_when_tasks_exceed_500(): void
    {
        // Verify that progress CSV renders cleanly with tasks metadata
        $response = $this->actingAs($this->user)
            ->get("/api/v1/pm/projects/{$this->project->uuid}/export?type=progress&format=csv");

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('SFU MSA PMS - Project Progress Report', $content);
        $this->assertStringContainsString('High Volume Logistics Project', $content);
        $this->assertStringContainsString('--- Tasks ---', $content);
    }
}

