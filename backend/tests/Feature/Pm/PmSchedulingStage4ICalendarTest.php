<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Pm\Enums\DepartmentRole;
use App\Pm\Enums\MilestoneStatus;
use App\Pm\Enums\ProjectPriority;
use App\Pm\Enums\ProjectStatus;
use App\Pm\Enums\ProjectVisibility;
use App\Pm\Enums\TaskStatus;
use App\Pm\Models\PmMilestone;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
use App\Pm\Services\DepartmentMembershipService;
use App\Pm\Services\PmICalendarService;
use App\Services\ApplicationAccessService;
use Database\Seeders\PmDepartmentSeeder;
use Database\Seeders\PmRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PmSchedulingStage4ICalendarTest extends TestCase
{
    use RefreshDatabase;

    protected PmICalendarService $iCalService;
    protected ApplicationAccessService $appAccessService;
    protected DepartmentMembershipService $deptService;

    protected User $itMember;
    protected User $eventsMember;
    protected User $unauthorizedUser;
    protected Department $itDept;
    protected Department $eventsDept;
    protected PmProject $itProject;
    protected PmProject $eventsProject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PmDepartmentSeeder::class);
        $this->seed(PmRolePermissionSeeder::class);

        $this->iCalService = app(PmICalendarService::class);
        $this->appAccessService = app(ApplicationAccessService::class);
        $this->deptService = app(DepartmentMembershipService::class);

        $this->itDept = Department::where('slug', 'it')->firstOrFail();
        $this->eventsDept = Department::where('slug', 'events')->firstOrFail();

        $memberRole = Role::firstOrCreate(['slug' => 'member'], ['uuid' => (string) Str::uuid(), 'name' => 'Member']);

        $this->itMember = User::factory()->create([
            'email' => 'it.member@sfumsa.ca',
            'is_active' => true,
        ]);
        $this->itMember->roles()->sync([$memberRole->id]);
        $this->deptService->addMember($this->itDept, $this->itMember, DepartmentRole::Member);
        $this->appAccessService->grant($this->itMember, 'projects', $this->itMember);

        $this->eventsMember = User::factory()->create([
            'email' => 'events.member@sfumsa.ca',
            'is_active' => true,
        ]);
        $this->eventsMember->roles()->sync([$memberRole->id]);
        $this->deptService->addMember($this->eventsDept, $this->eventsMember, DepartmentRole::Member);
        $this->appAccessService->grant($this->eventsMember, 'projects', $this->eventsMember);

        $this->unauthorizedUser = User::factory()->create([
            'email' => 'unauthorized@sfumsa.ca',
            'is_active' => true,
        ]);
        $this->unauthorizedUser->roles()->sync([$memberRole->id]);

        $this->itProject = PmProject::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'IT Infrastructure Overhaul',
            'code' => 'ITO',
            'department_id' => $this->itDept->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::High,
            'owner_id' => $this->itMember->id,
            'created_by' => $this->itMember->id,
            'due_date' => '2026-12-31',
        ]);

        $this->eventsProject = PmProject::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Annual Gala 2026',
            'code' => 'GALA',
            'department_id' => $this->eventsDept->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'owner_id' => $this->eventsMember->id,
            'created_by' => $this->eventsMember->id,
            'due_date' => '2026-11-20',
        ]);
    }

    public function test_icalendar_service_folds_lines_exceeding_75_octets(): void
    {
        $longLine = 'DESCRIPTION:This is an extremely long description designed specifically to exceed seventy-five octets in length and verify RFC 5545 compliant line folding technique.';
        $folded = $this->iCalService->foldLine($longLine);

        // Contains CRLF followed by space
        $this->assertStringContainsString("\r\n ", $folded);

        // Every individual folded chunk must not exceed 75 octets
        $lines = explode("\r\n", $folded);
        foreach ($lines as $idx => $line) {
            $this->assertLessThanOrEqual(75, strlen($line), "Folded line {$idx} exceeded 75 octets: {$line}");
        }

        // Unfolding must reproduce original text
        $unfolded = str_replace("\r\n ", '', $folded);
        $this->assertEquals($longLine, $unfolded);
    }

    public function test_icalendar_service_escapes_special_characters(): void
    {
        $rawText = "Title with, comma; semicolon \\ backslash\nand newline.";
        $escaped = $this->iCalService->escapeText($rawText);

        $this->assertEquals('Title with\, comma\; semicolon \\\\ backslash\nand newline.', $escaped);
    }

    public function test_icalendar_service_generates_exclusive_dtend_and_rfc5545_structure(): void
    {
        $taskMultiDay = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Server Migration',
            'status' => TaskStatus::InProgress,
            'priority' => 'high',
            'start_date' => '2026-11-15',
            'due_date' => '2026-11-20',
            'created_by' => $this->itMember->id,
        ]);

        $taskSingleDay = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'DNS Switchover',
            'status' => TaskStatus::Todo,
            'priority' => 'urgent',
            'start_date' => null,
            'due_date' => '2026-11-18',
            'created_by' => $this->itMember->id,
        ]);

        $milestone = PmMilestone::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Go Live Readiness',
            'status' => MilestoneStatus::Pending,
            'due_date' => '2026-11-25',
            'created_by' => $this->itMember->id,
        ]);

        $ics = $this->iCalService->generate(
            collect([$this->itProject]),
            collect([$milestone]),
            collect([$taskMultiDay, $taskSingleDay]),
            'Test Calendar'
        );

        // Header check
        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//SFU MSA//Project Management System//EN\r\n", $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);

        // Multi-day task: DTSTART 20261115, DTEND 20261121 (+1 day after Nov 20)
        $this->assertStringContainsString("UID:task-{$taskMultiDay->uuid}@projects.sfumsa.ca", $ics);
        $this->assertStringContainsString("DTSTART;VALUE=DATE:20261115\r\nDTEND;VALUE=DATE:20261121\r\n", $ics);
        $this->assertStringContainsString("STATUS:IN-PROCESS\r\nPRIORITY:3\r\n", $ics);

        // Single-day task: DTSTART 20261118, DTEND 20261119 (+1 day after Nov 18)
        $this->assertStringContainsString("UID:task-{$taskSingleDay->uuid}@projects.sfumsa.ca", $ics);
        $this->assertStringContainsString("DTSTART;VALUE=DATE:20261118\r\nDTEND;VALUE=DATE:20261119\r\n", $ics);
        $this->assertStringContainsString("STATUS:NEEDS-ACTION\r\nPRIORITY:1\r\n", $ics);

        // Milestone: DTSTART 20261125, DTEND 20261126
        $this->assertStringContainsString("UID:milestone-{$milestone->uuid}@projects.sfumsa.ca", $ics);
        $this->assertStringContainsString("DTSTART;VALUE=DATE:20261125\r\nDTEND;VALUE=DATE:20261126\r\n", $ics);
        $this->assertStringContainsString("SUMMARY:🎯 Go Live Readiness\r\n", $ics);

        // Project deadline: DTSTART 20261231, DTEND 20270101
        $this->assertStringContainsString("UID:project-{$this->itProject->uuid}@projects.sfumsa.ca", $ics);
        $this->assertStringContainsString("DTSTART;VALUE=DATE:20261231\r\nDTEND;VALUE=DATE:20270101\r\n", $ics);
        $this->assertStringContainsString("SUMMARY:🏁 IT Infrastructure Overhaul\r\n", $ics);
    }

    public function test_calendar_export_endpoint_requires_authentication(): void
    {
        $response = $this->get('/api/v1/pm/calendar/export.ics', ['Accept' => 'application/json']);
        $response->assertStatus(401);
    }

    public function test_calendar_export_endpoint_requires_project_access_entitlement(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);

        $response = $this->get('/api/v1/pm/calendar/export.ics');
        $response->assertStatus(403);
    }

    public function test_calendar_export_endpoint_streams_ics_and_enforces_option_b_scoping(): void
    {
        Sanctum::actingAs($this->itMember);

        // Create tasks in both IT project and Events project
        $itTask = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Authorized IT Task',
            'status' => TaskStatus::Todo,
            'priority' => 'high',
            'due_date' => '2026-11-20',
            'created_by' => $this->itMember->id,
        ]);

        $eventsTask = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->eventsProject->id,
            'title' => 'Forbidden Secret Events Task',
            'status' => TaskStatus::Todo,
            'priority' => 'high',
            'due_date' => '2026-11-20',
            'created_by' => $this->eventsMember->id,
        ]);

        $response = $this->get('/api/v1/pm/calendar/export.ics');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $response->assertHeader('Content-Disposition', 'attachment; filename="pms-calendar.ics"');

        $icsContent = $response->getContent();

        // Must include IT task and IT project
        $this->assertStringContainsString("task-{$itTask->uuid}@projects.sfumsa.ca", $icsContent);
        $this->assertStringContainsString("Authorized IT Task", $icsContent);
        $this->assertStringContainsString("project-{$this->itProject->uuid}@projects.sfumsa.ca", $icsContent);

        // MUST NOT contain events from Events department-private project
        $this->assertStringNotContainsString("task-{$eventsTask->uuid}@projects.sfumsa.ca", $icsContent);
        $this->assertStringNotContainsString("Forbidden Secret Events Task", $icsContent);
        $this->assertStringNotContainsString("project-{$this->eventsProject->uuid}@projects.sfumsa.ca", $icsContent);
    }

    public function test_calendar_export_endpoint_filters_by_project_uuid(): void
    {
        Sanctum::actingAs($this->itMember);

        $secondItProject = PmProject::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Secondary IT Project',
            'code' => 'IT2',
            'department_id' => $this->itDept->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'owner_id' => $this->itMember->id,
            'created_by' => $this->itMember->id,
            'due_date' => '2026-11-30',
        ]);

        $task1 = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Project 1 Task',
            'due_date' => '2026-11-20',
            'created_by' => $this->itMember->id,
        ]);

        $task2 = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $secondItProject->id,
            'title' => 'Project 2 Task',
            'due_date' => '2026-11-22',
            'created_by' => $this->itMember->id,
        ]);

        $response = $this->get("/api/v1/pm/calendar/export.ics?project_uuid={$this->itProject->uuid}");
        $response->assertOk();

        $ics = $response->getContent();
        $this->assertStringContainsString($task1->title, $ics);
        $this->assertStringNotContainsString($task2->title, $ics);
    }
}
