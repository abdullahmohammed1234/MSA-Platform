<?php

namespace PhpOffice\PhpSpreadsheet\Shared {
    // Windows sandbox workaround: is_readable() returns false on newly created temp files
    // in restricted sandbox tokens even when fopen('rb') and file_exists() succeed.
    if (!function_exists('PhpOffice\PhpSpreadsheet\Shared\is_readable')) {
        function is_readable(string $filename): bool {
            return is_file($filename) || file_exists($filename);
        }
    }
}

namespace Tests\Feature\Pm {

use App\Models\Department;
use App\Models\User;
use App\Pm\Enums\MilestoneStatus;
use App\Pm\Enums\ProjectPriority;
use App\Pm\Enums\ProjectRole;
use App\Pm\Enums\ProjectStatus;
use App\Pm\Enums\ProjectVisibility;
use App\Pm\Enums\TaskStatus;
use App\Pm\Models\PmMilestone;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmProjectMember;
use App\Pm\Models\PmTask;
use App\Pm\Models\PmTaskList;
use App\Services\ApplicationAccessService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class PmSpreadsheetFormulaInjectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $outsider;
    protected Department $department;
    protected PmProject $project;
    protected PmTaskList $taskList;

    /**
     * Comprehensive list of dangerous spreadsheet injection payloads
     */
    protected array $maliciousPayloads = [
        '=1+1',
        '=SUM(A1:A10)',
        '+1+1',
        '-1+1',
        '@SUM(1+1)',
        "\t=1+1",
        "\r=1+1",
        "\n=1+1",
        '  =1+1',
        '    +1+1',
        "\t  =1+1",
        "=CMD|' /C calc'!A0",
        "-2+3+cmd|' /C calc'!A0",
        "@SUM(1+1)*cmd|' /C calc'!A0",
        "+cmd|' /C calc'!A0",
        "|' /C calc'!A0",
        "%1+1",
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $appAccess = app(ApplicationAccessService::class);

        $this->user = User::factory()->create([
            'name' => 'Security Auditor',
            'email' => 'auditor@sfu.ca',
        ]);

        $this->outsider = User::factory()->create([
            'name' => 'Unauthorized User',
            'email' => 'outsider@sfu.ca',
        ]);

        $appAccess->grant($this->user, 'projects');
        $appAccess->grant($this->outsider, 'projects');

        $this->department = Department::create([
            'name' => '+TECH Dangerous Department',
            'code' => 'TECH',
        ]);

        $this->project = PmProject::create([
            'name' => '=1+1 Dangerous Project Name',
            'slug' => 'dangerous-project',
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

        $this->taskList = PmTaskList::create([
            'project_id' => $this->project->id,
            'name' => '@SUM Section Name',
            'order_index' => 1,
        ]);
    }

    #[Test]
    public function csv_export_neutralizes_all_dangerous_formula_prefixes_in_task_titles(): void
    {
        // Create tasks with all malicious payloads
        foreach ($this->maliciousPayloads as $index => $payload) {
            PmTask::create([
                'project_id' => $this->project->id,
                'task_list_id' => $this->taskList->id,
                'title' => $payload,
                'status' => TaskStatus::InProgress,
                'priority' => ProjectPriority::High,
                'estimated_hours' => 5.0,
                'assigned_to' => $this->user->id,
                'created_by' => $this->user->id,
                'due_date' => Carbon::now()->addDays($index + 1),
            ]);
        }

        // Also add legitimate and edge case tasks
        PmTask::create([
            'project_id' => $this->project->id,
            'task_list_id' => $this->taskList->id,
            'title' => 'Legitimate Task Title',
            'status' => TaskStatus::Completed,
            'priority' => ProjectPriority::Medium,
            'estimated_hours' => 3.5,
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        PmTask::create([
            'project_id' => $this->project->id,
            'task_list_id' => $this->taskList->id,
            'title' => "Task with, comma and \"quotes\" and \nnewline",
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::Low,
            'estimated_hours' => 2.0,
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/api/v1/pm/projects/{$this->project->uuid}/export?type=progress&format=csv");

        $response->assertStatus(200);
        $content = $response->streamedContent();

        // 1. Verify UTF-8 BOM is present
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content, 'CSV must start with UTF-8 BOM');

        // 2. Parse using independent CSV reader (fgetcsv)
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, substr($content, 3)); // Strip BOM
        rewind($stream);

        $parsedRows = [];
        while (($row = fgetcsv($stream)) !== false) {
            if (!empty($row)) {
                $parsedRows[] = $row;
            }
        }
        fclose($stream);

        $this->assertNotEmpty($parsedRows);

        // 3. Verify Project Name in metadata row is neutralized
        $projectNameRow = null;
        foreach ($parsedRows as $r) {
            if (isset($r[0]) && $r[0] === 'Project Name') {
                $projectNameRow = $r;
                break;
            }
        }
        $this->assertNotNull($projectNameRow);
        $this->assertEquals("'=1+1 Dangerous Project Name", $projectNameRow[1]);

        // 4. Verify Department Name is neutralized
        $departmentRow = null;
        foreach ($parsedRows as $r) {
            if (isset($r[0]) && $r[0] === 'Department') {
                $departmentRow = $r;
                break;
            }
        }
        $this->assertNotNull($departmentRow);
        $this->assertEquals("'+TECH Dangerous Department", $departmentRow[1]);

        // 5. Verify every malicious task row is properly neutralized
        foreach ($this->maliciousPayloads as $payload) {
            $found = false;
            foreach ($parsedRows as $r) {
                if (isset($r[0]) && str_contains($r[0], ltrim($payload, " \t\n\r\0\x0B\xC2\xA0"))) {
                    $found = true;
                    // The first character of the parsed CSV field must be an apostrophe
                    $this->assertEquals("'", $r[0][0], "Malicious payload [{$payload}] must be prefixed with single quote in CSV");
                    break;
                }
            }
            $this->assertTrue($found, "Expected to find task for payload [{$payload}] in CSV output");
        }

        // 6. Verify Section name is neutralized
        $sectionFound = false;
        foreach ($parsedRows as $r) {
            if (isset($r[1]) && str_contains($r[1], 'Section Name')) {
                $sectionFound = true;
                $this->assertEquals("'@SUM Section Name", $r[1]);
                break;
            }
        }
        $this->assertTrue($sectionFound, "Section name '@SUM Section Name' must be sanitized in CSV");

        // 7. Verify legitimate text is NOT modified
        $legitFound = false;
        foreach ($parsedRows as $r) {
            if (isset($r[0]) && $r[0] === 'Legitimate Task Title') {
                $legitFound = true;
                break;
            }
        }
        $this->assertTrue($legitFound, 'Legitimate task title must remain unchanged');

        // 8. Verify multiline & comma description quoting is preserved
        $multilineFound = false;
        foreach ($parsedRows as $r) {
            if (isset($r[0]) && str_contains($r[0], 'comma and "quotes"')) {
                $multilineFound = true;
                $this->assertEquals("Task with, comma and \"quotes\" and \nnewline", $r[0]);
                break;
            }
        }
        $this->assertTrue($multilineFound, 'Quoted and multiline text must be correctly preserved by CSV parser');
    }

    #[Test]
    public function xlsx_export_neutralizes_formulas_and_stores_all_user_text_explicitly_as_strings(): void
    {
        $appAccess = app(ApplicationAccessService::class);

        // Create user with malicious name
        $maliciousUser = User::factory()->create([
            'name' => '=HYPERLINK("http://evil.com") Malicious Contributor',
            'email' => '+attacker@evil.com',
        ]);
        $appAccess->grant($maliciousUser, 'projects');

        PmProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $maliciousUser->id,
            'project_role' => ProjectRole::Contributor,
            'invitation_status' => 'accepted',
        ]);

        // Create milestone with malicious title
        PmMilestone::create([
            'project_id' => $this->project->id,
            'title' => '=2+2 Critical Phase',
            'status' => MilestoneStatus::Pending,
            'due_date' => Carbon::now()->addDays(10),
            'order' => 1,
        ]);

        // Create tasks with dangerous prefixes
        foreach ($this->maliciousPayloads as $index => $payload) {
            PmTask::create([
                'project_id' => $this->project->id,
                'task_list_id' => $this->taskList->id,
                'title' => $payload,
                'status' => TaskStatus::InProgress,
                'priority' => ProjectPriority::High,
                'estimated_hours' => 8.0,
                'assigned_to' => $maliciousUser->id,
                'created_by' => $this->user->id,
                'due_date' => Carbon::now()->addDays(5),
            ]);
        }

        $response = $this->actingAs($this->user)
            ->get("/api/v1/pm/projects/{$this->project->uuid}/export?type=progress&format=xlsx");

        $response->assertStatus(200);
        $binaryContent = $response->streamedContent();
        $this->assertNotEmpty($binaryContent);

        // Save binary content to temp file for inspection
        $tempPath = storage_path('framework/testing_formula_test_' . uniqid() . '.xlsx');
        file_put_contents($tempPath, $binaryContent);

        try {
            // A. Inspect raw OpenXML zip structure
            $zip = new ZipArchive();
            $this->assertTrue($zip->open($tempPath), 'Generated XLSX must be a valid ZipArchive');

            $sheet1Xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $sheet2Xml = $zip->getFromName('xl/worksheets/sheet2.xml');

            $zip->close();

            $this->assertNotEmpty($sheet1Xml);
            $this->assertNotEmpty($sheet2Xml);

            // In OpenXML, formulas are declared with <f> tags (e.g. <f>SUM(A1:A10)</f>)
            // Ensure NO <f> formula tags exist in either sheet for user data!
            $this->assertStringNotContainsString('<f>', $sheet1Xml, 'Sheet 1 (Overview) must not contain formula tags');
            $this->assertStringNotContainsString('<f>', $sheet2Xml, 'Sheet 2 (Tasks) must not contain formula tags');

            // B. Reopen with PhpSpreadsheet IOFactory and verify every cell
            $spreadsheet = IOFactory::load($tempPath);

            // 1. Inspect Sheet 1: Overview
            $overviewSheet = $spreadsheet->getSheetByName('Overview');
            $this->assertNotNull($overviewSheet);

            $projectNameCell = $overviewSheet->getCell('B3');
            $this->assertEquals(DataType::TYPE_STRING, $projectNameCell->getDataType());
            $this->assertFalse($projectNameCell->isFormula());
            $this->assertEquals("'=1+1 Dangerous Project Name", $projectNameCell->getValue());
            $this->assertEquals("'=1+1 Dangerous Project Name", $projectNameCell->getCalculatedValue());

            $deptCell = $overviewSheet->getCell('B5');
            $this->assertEquals(DataType::TYPE_STRING, $deptCell->getDataType());
            $this->assertFalse($deptCell->isFormula());
            $this->assertEquals("'+TECH Dangerous Department", $deptCell->getValue());

            // Milestone title in row 14
            $milestoneCell = $overviewSheet->getCell('A14');
            $this->assertEquals(DataType::TYPE_STRING, $milestoneCell->getDataType());
            $this->assertFalse($milestoneCell->isFormula());
            $this->assertEquals("'=2+2 Critical Phase", $milestoneCell->getValue());
            $this->assertEquals("'=2+2 Critical Phase", $milestoneCell->getCalculatedValue());

            // Milestone tasks count (numeric column D)
            $milestoneCountCell = $overviewSheet->getCell('D14');
            $this->assertEquals(DataType::TYPE_NUMERIC, $milestoneCountCell->getDataType());

            // 2. Inspect Sheet 2: Tasks
            $tasksSheet = $spreadsheet->getSheetByName('Tasks');
            $this->assertNotNull($tasksSheet);

            $highestRow = $tasksSheet->getHighestRow();
            $this->assertGreaterThan(count($this->maliciousPayloads), $highestRow);

            for ($row = 2; $row <= $highestRow; $row++) {
                $titleCell = $tasksSheet->getCell('A' . $row);
                $this->assertEquals(DataType::TYPE_STRING, $titleCell->getDataType(), "Row $row title must be TYPE_STRING");
                $this->assertFalse($titleCell->isFormula(), "Row $row title must not be a formula");

                $val = $titleCell->getValue();
                $calc = $titleCell->getCalculatedValue();

                // Value must never be mathematically evaluated
                $this->assertNotEquals(2, $val, "Row $row title was evaluated as 2!");
                $this->assertNotEquals(2, $calc, "Row $row title calculatedValue was evaluated as 2!");

                // Check section cell (Col B)
                $sectionCell = $tasksSheet->getCell('B' . $row);
                $this->assertEquals(DataType::TYPE_STRING, $sectionCell->getDataType());
                $this->assertFalse($sectionCell->isFormula());
                $this->assertEquals("'@SUM Section Name", $sectionCell->getValue());

                // Check assignee cell (Col C)
                $assigneeCell = $tasksSheet->getCell('C' . $row);
                $this->assertEquals(DataType::TYPE_STRING, $assigneeCell->getDataType());
                $this->assertFalse($assigneeCell->isFormula());
                $this->assertEquals("'=HYPERLINK(\"http://evil.com\") Malicious Contributor", $assigneeCell->getValue());

                // Check numeric estimated_hours (Col H)
                $hoursCell = $tasksSheet->getCell('H' . $row);
                $this->assertEquals(DataType::TYPE_NUMERIC, $hoursCell->getDataType(), "Row $row hours must remain TYPE_NUMERIC");
                $this->assertEquals(8.0, $hoursCell->getValue());
            }
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    #[Test]
    public function cross_project_tasks_export_neutralizes_spreadsheet_injection(): void
    {
        // Create task with malicious title and section
        PmTask::create([
            'project_id' => $this->project->id,
            'task_list_id' => $this->taskList->id,
            'title' => "=SUM(B1:B100)",
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::High,
            'estimated_hours' => 12.0,
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/api/v1/pm/tasks/export?format=csv');

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString("'=SUM(B1:B100)", $content);
        $this->assertStringContainsString("'=1+1 Dangerous Project Name", $content);
        $this->assertStringContainsString("'@SUM Section Name", $content);
    }

    #[Test]
    public function workload_export_neutralizes_spreadsheet_injection(): void
    {
        $appAccess = app(ApplicationAccessService::class);

        $maliciousUser = User::factory()->create([
            'name' => '=CMD|calc Contributor',
            'email' => '-user@msa.ca',
        ]);
        $appAccess->grant($maliciousUser, 'projects');

        PmProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $maliciousUser->id,
            'project_role' => ProjectRole::Contributor,
            'invitation_status' => 'accepted',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/api/v1/pm/projects/{$this->project->uuid}/export?type=workload&format=csv");

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString("SFU MSA PMS - Workload Allocation - =1+1 Dangerous Project Name", $content);
        $this->assertStringContainsString("'=CMD|calc Contributor", $content);
        $this->assertStringContainsString("'-user@msa.ca", $content);
    }

    #[Test]
    public function database_records_are_not_mutated_by_sanitization(): void
    {
        $task = PmTask::create([
            'project_id' => $this->project->id,
            'task_list_id' => $this->taskList->id,
            'title' => "=1+1 Raw Database Value",
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::High,
            'estimated_hours' => 4.0,
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        // Perform export
        $response = $this->actingAs($this->user)
            ->get("/api/v1/pm/projects/{$this->project->uuid}/export?type=progress&format=csv");

        $response->assertStatus(200);

        // Reload fresh record from database
        $freshTask = $task->fresh();
        $this->assertEquals('=1+1 Raw Database Value', $freshTask->title, 'Database record must remain unmutated without apostrophe prefix');

        $freshProject = $this->project->fresh();
        $this->assertEquals('=1+1 Dangerous Project Name', $freshProject->name, 'Project name in DB must remain unmutated');
    }

    #[Test]
    public function pdf_export_does_not_inject_spreadsheet_apostrophes(): void
    {
        PmTask::create([
            'project_id' => $this->project->id,
            'task_list_id' => $this->taskList->id,
            'title' => "- Bullet Point Item",
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::High,
            'estimated_hours' => 2.0,
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/api/v1/pm/projects/{$this->project->uuid}/export?type=progress&format=pdf");

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $pdfContent = $response->streamedContent();
        $this->assertStringStartsWith('%PDF-', $pdfContent);
    }

    #[Test]
    public function option_b_authorization_is_strictly_enforced_for_exports(): void
    {
        // Outsider without membership or lead role is forbidden
        $response = $this->actingAs($this->outsider)
            ->get("/api/v1/pm/projects/{$this->project->uuid}/export?type=progress&format=csv");

        $response->assertStatus(403);
    }
}

}
