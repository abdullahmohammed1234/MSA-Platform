<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Pm\Enums\DepartmentRole;
use App\Pm\Enums\DependencyType;
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
use App\Pm\Models\PmTaskDependency;
use App\Pm\Services\DepartmentMembershipService;
use App\Pm\Services\PmSchedulingService;
use App\Services\ApplicationAccessService;
use Database\Seeders\PmDepartmentSeeder;
use Database\Seeders\PmRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PmSchedulingStage3CalendarApiTest extends TestCase
{
    use RefreshDatabase;

    protected PmSchedulingService $schedulingService;
    protected ApplicationAccessService $appAccessService;
    protected DepartmentMembershipService $deptService;

    protected User $itMember;
    protected User $itSecondary;
    protected User $eventsMember;
    protected Department $itDept;
    protected Department $eventsDept;
    protected PmProject $itProject;
    protected PmProject $eventsProject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PmDepartmentSeeder::class);
        $this->seed(PmRolePermissionSeeder::class);

        $this->schedulingService = app(PmSchedulingService::class);
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

        $this->itSecondary = User::factory()->create([
            'email' => 'it.secondary@sfumsa.ca',
            'is_active' => true,
        ]);
        $this->itSecondary->roles()->sync([$memberRole->id]);
        $this->deptService->addMember($this->itDept, $this->itSecondary, DepartmentRole::Member);
        $this->appAccessService->grant($this->itSecondary, 'projects', $this->itSecondary);

        $this->eventsMember = User::factory()->create([
            'email' => 'events.member@sfumsa.ca',
            'is_active' => true,
        ]);
        $this->eventsMember->roles()->sync([$memberRole->id]);
        $this->deptService->addMember($this->eventsDept, $this->eventsMember, DepartmentRole::Member);
        $this->appAccessService->grant($this->eventsMember, 'projects', $this->eventsMember);

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

    public function test_calendar_events_returns_multi_day_task_ranges(): void
    {
        Sanctum::actingAs($this->itMember);

        $task = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Cluster Deployment',
            'status' => TaskStatus::InProgress,
            'priority' => 'high',
            'start_date' => '2026-11-15',
            'due_date' => '2026-11-20',
            'assigned_to' => $this->itMember->id,
            'created_by' => $this->itMember->id,
        ]);

        $response = $this->getJson('/api/v1/pm/calendar/events?type=task');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        $event = collect($data)->firstWhere('uuid', $task->uuid);
        $this->assertNotNull($event);
        $this->assertEquals('2026-11-15', $event['start']);
        $this->assertEquals('2026-11-20', $event['end']);
        $this->assertEquals('2026-11-15', $event['start_date']);
        $this->assertEquals('2026-11-20', $event['due_date']);
        $this->assertTrue($event['is_multi_day']);
        $this->assertTrue($event['allDay']);
    }

    public function test_calendar_events_falls_back_to_single_day_when_start_date_is_null(): void
    {
        Sanctum::actingAs($this->itMember);

        $task = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Single Day Review',
            'status' => TaskStatus::Todo,
            'priority' => 'medium',
            'start_date' => null,
            'due_date' => '2026-11-18',
            'assigned_to' => $this->itMember->id,
            'created_by' => $this->itMember->id,
        ]);

        $response = $this->getJson('/api/v1/pm/calendar/events?type=task');
        $response->assertOk();

        $event = collect($response->json('data'))->firstWhere('uuid', $task->uuid);
        $this->assertNotNull($event);
        $this->assertEquals('2026-11-18', $event['start']);
        $this->assertEquals('2026-11-18', $event['end']);
        $this->assertNull($event['start_date']);
        $this->assertEquals('2026-11-18', $event['due_date']);
        $this->assertFalse($event['is_multi_day']);
    }

    public function test_calendar_events_eager_loads_multi_assignees(): void
    {
        Sanctum::actingAs($this->itMember);

        $task = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Joint Firewall Audit',
            'status' => TaskStatus::Todo,
            'priority' => 'urgent',
            'due_date' => '2026-11-25',
            'assigned_to' => $this->itMember->id,
            'created_by' => $this->itMember->id,
        ]);

        $task->assignees()->attach([$this->itMember->id, $this->itSecondary->id]);

        $response = $this->getJson('/api/v1/pm/calendar/events?type=task');
        $response->assertOk();

        $event = collect($response->json('data'))->firstWhere('uuid', $task->uuid);
        $this->assertNotNull($event);

        // Primary assignee
        $this->assertNotNull($event['assignee']);
        $this->assertEquals($this->itMember->id, $event['assignee']['id']);

        // Multi assignees array
        $this->assertIsArray($event['assignees']);
        $this->assertCount(2, $event['assignees']);
        $assigneeIds = collect($event['assignees'])->pluck('id')->all();
        $this->assertContains($this->itMember->id, $assigneeIds);
        $this->assertContains($this->itSecondary->id, $assigneeIds);
    }

    public function test_calendar_events_embeds_scheduling_conflict_flags(): void
    {
        Sanctum::actingAs($this->itMember);

        // Predecessor task due 2026-11-20
        $pred = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Core Network Switch Config',
            'status' => TaskStatus::InProgress,
            'priority' => 'high',
            'start_date' => '2026-11-10',
            'due_date' => '2026-11-20',
            'created_by' => $this->itMember->id,
        ]);

        // Successor task starting 2026-11-15 (CONFLICT: before 2026-11-21)
        $succ = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Server Rack Mount',
            'status' => TaskStatus::Todo,
            'priority' => 'high',
            'start_date' => '2026-11-15',
            'due_date' => '2026-11-25',
            'created_by' => $this->itMember->id,
        ]);

        PmTaskDependency::create([
            'project_id' => $this->itProject->id,
            'task_id' => $succ->id,
            'depends_on_task_id' => $pred->id,
            'dependency_type' => DependencyType::FinishToStart,
            'lag_days' => 0,
        ]);

        $response = $this->getJson('/api/v1/pm/calendar/events?type=task');
        $response->assertOk();

        $predEvent = collect($response->json('data'))->firstWhere('uuid', $pred->uuid);
        $this->assertFalse($predEvent['is_conflict']);
        $this->assertNull($predEvent['conflict_reason']);

        $succEvent = collect($response->json('data'))->firstWhere('uuid', $succ->uuid);
        $this->assertTrue($succEvent['is_conflict']);
        $this->assertNotEmpty($succEvent['conflict_reason']);
        $this->assertStringContainsString('Core Network Switch Config', $succEvent['conflict_reason']);
    }

    public function test_calendar_events_includes_milestone_condition(): void
    {
        Sanctum::actingAs($this->itMember);

        $milestone = PmMilestone::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Phase 1 Go-Live',
            'status' => MilestoneStatus::Pending,
            'due_date' => '2026-11-20',
            'created_by' => $this->itMember->id,
        ]);

        // Child task due 2026-11-25 (delayed past milestone due date)
        PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'milestone_id' => $milestone->id,
            'title' => 'Delayed Task',
            'status' => TaskStatus::InProgress,
            'priority' => 'medium',
            'due_date' => '2026-11-25',
            'created_by' => $this->itMember->id,
        ]);

        $response = $this->getJson('/api/v1/pm/calendar/events?type=milestone');
        $response->assertOk();

        $event = collect($response->json('data'))->firstWhere('uuid', $milestone->uuid);
        $this->assertNotNull($event);
        $this->assertEquals('delayed', $event['condition']);
    }

    public function test_calendar_events_interval_overlap_filtering(): void
    {
        Sanctum::actingAs($this->itMember);

        // Task 1: Nov 10 to Nov 14 (strictly before window Nov 15-25)
        $taskBefore = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Before Window',
            'start_date' => '2026-11-10',
            'due_date' => '2026-11-14',
            'created_by' => $this->itMember->id,
        ]);

        // Task 2: Nov 12 to Nov 18 (overlaps start of window)
        $taskOverlapStart = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Overlap Start',
            'start_date' => '2026-11-12',
            'due_date' => '2026-11-18',
            'created_by' => $this->itMember->id,
        ]);

        // Task 3: Nov 16 to Nov 22 (fully inside window)
        $taskInside = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Inside Window',
            'start_date' => '2026-11-16',
            'due_date' => '2026-11-22',
            'created_by' => $this->itMember->id,
        ]);

        // Task 4: Nov 24 to Nov 30 (overlaps end of window)
        $taskOverlapEnd = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Overlap End',
            'start_date' => '2026-11-24',
            'due_date' => '2026-11-30',
            'created_by' => $this->itMember->id,
        ]);

        // Task 5: Dec 1 to Dec 5 (strictly after window)
        $taskAfter = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'After Window',
            'start_date' => '2026-12-01',
            'due_date' => '2026-12-05',
            'created_by' => $this->itMember->id,
        ]);

        // Task 6: start_date null, due Nov 17 (due date inside window)
        $taskNullStartInside = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Null Start Inside',
            'start_date' => null,
            'due_date' => '2026-11-17',
            'created_by' => $this->itMember->id,
        ]);

        // Task 7: start_date null, due Nov 28 (due date outside window)
        $taskNullStartOutside = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Null Start Outside',
            'start_date' => null,
            'due_date' => '2026-11-28',
            'created_by' => $this->itMember->id,
        ]);

        $response = $this->getJson('/api/v1/pm/calendar/events?type=task&start=2026-11-15&end=2026-11-25');
        $response->assertOk();

        $uuids = collect($response->json('data'))->pluck('uuid')->all();

        $this->assertNotContains($taskBefore->uuid, $uuids);
        $this->assertContains($taskOverlapStart->uuid, $uuids);
        $this->assertContains($taskInside->uuid, $uuids);
        $this->assertContains($taskOverlapEnd->uuid, $uuids);
        $this->assertNotContains($taskAfter->uuid, $uuids);
        $this->assertContains($taskNullStartInside->uuid, $uuids);
        $this->assertNotContains($taskNullStartOutside->uuid, $uuids);
    }

    public function test_my_tasks_only_filters_by_assigned_to_or_assignees_pivot(): void
    {
        Sanctum::actingAs($this->itMember);

        // Task assigned primarily to itMember
        $taskPrimary = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Primary Assigned',
            'due_date' => '2026-11-20',
            'assigned_to' => $this->itMember->id,
            'created_by' => $this->itMember->id,
        ]);

        // Task assigned primarily to itSecondary, but itMember in pivot assignees
        $taskCoAssigned = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Co Assigned',
            'due_date' => '2026-11-20',
            'assigned_to' => $this->itSecondary->id,
            'created_by' => $this->itSecondary->id,
        ]);
        $taskCoAssigned->assignees()->attach([$this->itMember->id]);

        // Task assigned only to itSecondary
        $taskUnassignedToMe = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Not Mine',
            'due_date' => '2026-11-20',
            'assigned_to' => $this->itSecondary->id,
            'created_by' => $this->itSecondary->id,
        ]);

        $response = $this->getJson('/api/v1/pm/calendar/events?type=task&my_tasks_only=1');
        $response->assertOk();

        $uuids = collect($response->json('data'))->pluck('uuid')->all();
        $this->assertContains($taskPrimary->uuid, $uuids);
        $this->assertContains($taskCoAssigned->uuid, $uuids);
        $this->assertNotContains($taskUnassignedToMe->uuid, $uuids);
    }

    public function test_calendar_events_strictly_enforces_option_b_scoping(): void
    {
        // itMember views calendar
        Sanctum::actingAs($this->itMember);

        $eventsTask = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->eventsProject->id,
            'title' => 'Secret Events Task',
            'due_date' => '2026-11-20',
            'created_by' => $this->eventsMember->id,
        ]);

        $response = $this->getJson('/api/v1/pm/calendar/events');
        $response->assertOk();

        $data = $response->json('data');
        $projectIds = collect($data)->pluck('project.id')->filter()->unique()->all();

        // itMember must only see events from IT project, never Events private project
        $this->assertContains($this->itProject->id, $projectIds);
        $this->assertNotContains($this->eventsProject->id, $projectIds);
    }
}

