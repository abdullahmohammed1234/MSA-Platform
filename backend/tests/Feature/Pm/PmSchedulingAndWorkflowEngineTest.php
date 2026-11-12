<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Pm\Enums\DependencyType;
use App\Pm\Enums\DepartmentRole;
use App\Pm\Enums\MilestoneStatus;
use App\Pm\Enums\ProjectPriority;
use App\Pm\Enums\ProjectStatus;
use App\Pm\Enums\ProjectVisibility;
use App\Pm\Enums\TaskStatus;
use App\Pm\Models\PmCalendarFeed;
use App\Pm\Models\PmMilestone;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
use App\Pm\Models\PmTaskDependency;
use App\Pm\Services\DepartmentMembershipService;
use App\Services\ApplicationAccessService;
use Database\Seeders\PmDepartmentSeeder;
use Database\Seeders\PmRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PmSchedulingAndWorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    protected ApplicationAccessService $appAccessService;
    protected DepartmentMembershipService $deptService;

    protected User $itLeader;
    protected User $itMember;
    protected User $eventsMember;

    protected Department $itDept;
    protected Department $eventsDept;

    protected PmProject $itPrivateProject;
    protected PmProject $eventsPrivateProject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PmDepartmentSeeder::class);
        $this->seed(PmRolePermissionSeeder::class);

        $this->appAccessService = app(ApplicationAccessService::class);
        $this->deptService = app(DepartmentMembershipService::class);

        $this->itDept = Department::where('slug', 'it')->firstOrFail();
        $this->eventsDept = Department::where('slug', 'events')->firstOrFail();

        $memberRole = Role::firstOrCreate(['slug' => 'member'], ['uuid' => (string) Str::uuid(), 'name' => 'Member']);

        $this->itLeader = User::factory()->create([
            'email' => 'it.leader@sfumsa.ca',
            'is_active' => true,
        ]);
        $this->itLeader->roles()->sync([$memberRole->id]);
        $this->deptService->addMember($this->itDept, $this->itLeader, DepartmentRole::Lead);
        $this->appAccessService->grant($this->itLeader, 'projects', $this->itLeader);

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

        $this->itPrivateProject = PmProject::create([
            'uuid' => (string) Str::uuid(),
            'department_id' => $this->itDept->id,
            'owner_id' => $this->itLeader->id,
            'name' => 'IT Infrastructure Overhaul',
            'description' => 'Upgrading MSA digital systems and workflows',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::High,
        ]);

        $this->eventsPrivateProject = PmProject::create([
            'uuid' => (string) Str::uuid(),
            'department_id' => $this->eventsDept->id,
            'owner_id' => $this->eventsMember->id,
            'name' => 'Winter Gala Operations',
            'description' => 'Planning winter social and guest banquet',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Medium,
        ]);
    }

    /**
     * Test 1: Full dependency graph setup, passive conflict detection, cascade preview, and execution.
     */
    public function test_multi_tier_dependency_chain_and_cascade_rescheduling(): void
    {
        Sanctum::actingAs($this->itLeader);

        // Create 4 chained tasks: Task A -> Task B (FS + 1d) -> Task C (SS + 2d) -> Task D (FF + 0d)
        $taskA = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itPrivateProject->id,
            'title' => 'Phase A: Server Architecture',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::High,
            'start_date' => '2026-11-01',
            'due_date' => '2026-11-05',
        ]);

        $taskB = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itPrivateProject->id,
            'title' => 'Phase B: Database Provisioning',
            'status' => TaskStatus::Todo,
            'priority' => ProjectPriority::High,
            'start_date' => '2026-11-06',
            'due_date' => '2026-11-10',
        ]);

        $taskC = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itPrivateProject->id,
            'title' => 'Phase C: API Integration',
            'status' => TaskStatus::Todo,
            'priority' => ProjectPriority::Medium,
            'start_date' => '2026-11-08',
            'due_date' => '2026-11-15',
        ]);

        $taskD = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itPrivateProject->id,
            'title' => 'Phase D: Load Testing & Hardening',
            'status' => TaskStatus::Todo,
            'priority' => ProjectPriority::Medium,
            'start_date' => '2026-11-12',
            'due_date' => '2026-11-15',
        ]);

        // Link dependencies
        // Task B depends on Task A (FS, lag 0 days)
        $this->postJson("/api/v1/pm/tasks/{$taskB->uuid}/dependencies", [
            'depends_on_task_uuid' => $taskA->uuid,
            'dependency_type' => DependencyType::FinishToStart->value,
            'lag_days' => 0,
        ])->assertStatus(201);

        // Task C depends on Task B (SS, lag 2 days)
        $this->postJson("/api/v1/pm/tasks/{$taskC->uuid}/dependencies", [
            'depends_on_task_uuid' => $taskB->uuid,
            'dependency_type' => DependencyType::StartToStart->value,
            'lag_days' => 2,
        ])->assertStatus(201);

        // Task D depends on Task C (FF, lag 0 days)
        $this->postJson("/api/v1/pm/tasks/{$taskD->uuid}/dependencies", [
            'depends_on_task_uuid' => $taskC->uuid,
            'dependency_type' => DependencyType::FinishToFinish->value,
            'lag_days' => 0,
        ])->assertStatus(201);

        // Initial check: Task B scheduling should report 0 conflicts
        $resB = $this->getJson("/api/v1/pm/tasks/{$taskB->uuid}/scheduling");
        $resB->assertStatus(200);
        $this->assertFalse($resB->json('scheduling.has_conflicts'));
        $this->assertEmpty($resB->json('scheduling.conflicts'));

        // Shift Task A due date forward to 2026-11-10 WITHOUT cascade (simulating manual pushout)
        $taskA->update([
            'start_date' => '2026-11-04',
            'due_date' => '2026-11-10',
        ]);

        // Passive conflict detection on Task B:
        // Task A finishes on 2026-11-10 + 1d lag => Task B must start on/after 2026-11-11, but Task B starts 2026-11-06
        $conflictCheck = $this->getJson("/api/v1/pm/tasks/{$taskB->uuid}/scheduling");
        $conflictCheck->assertStatus(200);
        $this->assertTrue($conflictCheck->json('scheduling.has_conflicts'));
        $this->assertCount(1, $conflictCheck->json('scheduling.conflicts'));
        $this->assertEquals($taskA->id, $conflictCheck->json('scheduling.conflicts.0.predecessor_id'));
        $this->assertEquals('2026-11-11', $conflictCheck->json('scheduling.conflicts.0.required_date'));

        // Preview cascade rescheduling starting from Task A
        $preview = $this->postJson("/api/v1/pm/tasks/{$taskA->uuid}/reschedule/preview", [
            'start_date' => '2026-11-04',
            'due_date' => '2026-11-10',
        ]);
        $preview->assertStatus(200);
        $this->assertEquals(3, $preview->json('preview.total_affected_count'));
        $this->assertGreaterThanOrEqual(3, $preview->json('preview.max_depth_reached'));

        // Execute on-demand cascade reschedule
        $executeRes = $this->postJson("/api/v1/pm/tasks/{$taskA->uuid}/reschedule", [
            'start_date' => '2026-11-04',
            'due_date' => '2026-11-10',
            'cascade' => true,
        ]);
        $executeRes->assertStatus(200);

        // Assert all tasks shifted correctly downstream
        $taskB->refresh();
        $taskC->refresh();
        $taskD->refresh();

        // Task B: duration was 5 days (Nov 6 to Nov 10). Shifted to start on Nov 11 -> due Nov 15
        $this->assertEquals('2026-11-11', $taskB->start_date->toDateString());
        $this->assertEquals('2026-11-15', $taskB->due_date->toDateString());

        // Task C (SS + 2d from Task B): Task B starts Nov 11 => Task C starts Nov 13.
        // Task C duration was 8 days (Nov 8 to Nov 15) => due Nov 20
        $this->assertEquals('2026-11-13', $taskC->start_date->toDateString());
        $this->assertEquals('2026-11-20', $taskC->due_date->toDateString());

        // Task D (FF + 0d from Task C): Task C finishes Nov 20 => Task D finishes Nov 20.
        // Task D duration was 4 days (Nov 12 to Nov 15) => starts Nov 17
        $this->assertEquals('2026-11-17', $taskD->start_date->toDateString());
        $this->assertEquals('2026-11-20', $taskD->due_date->toDateString());

        // Verify conflicts are now completely cleared
        $clearedCheck = $this->getJson("/api/v1/pm/tasks/{$taskB->uuid}/scheduling");
        $clearedCheck->assertStatus(200);
        $this->assertFalse($clearedCheck->json('scheduling.has_conflicts'));
    }

    /**
     * Test 2: Calendar events API reflects updated ranges, multi-day spans, and conflict flags.
     */
    public function test_calendar_events_reflects_scheduling_state(): void
    {
        Sanctum::actingAs($this->itLeader);

        $task = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itPrivateProject->id,
            'title' => 'Server Migration Sprint',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::Urgent,
            'start_date' => '2026-11-10',
            'due_date' => '2026-11-16',
            'assigned_to' => $this->itMember->id,
        ]);

        $res = $this->getJson("/api/v1/pm/calendar/events?project_uuid={$this->itPrivateProject->uuid}&start=2026-11-01&end=2026-11-30");
        $res->assertStatus(200);

        $events = $res->json('data');
        $this->assertNotEmpty($events);

        $event = collect($events)->firstWhere('uuid', $task->uuid);
        $this->assertNotNull($event);
        $this->assertEquals('2026-11-10', $event['start_date']);
        $this->assertEquals('2026-11-16', $event['due_date']);
        $this->assertTrue($event['is_multi_day']);
        $this->assertFalse($event['is_conflict']);
    }

    /**
     * Test 3: RFC 5545 snapshot export adheres to standard and respects Option B boundary.
     */
    public function test_rfc5545_export_generation_and_option_b_boundary(): void
    {
        // Add tasks to IT project
        PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itPrivateProject->id,
            'title' => 'Confidential IT Security Audit',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::Urgent,
            'start_date' => '2026-11-02',
            'due_date' => '2026-11-08',
        ]);

        // Add task to Events project
        PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->eventsPrivateProject->id,
            'title' => 'Catering Vendor Selection',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'start_date' => '2026-11-05',
            'due_date' => '2026-11-09',
        ]);

        // IT Leader exports calendar: contains IT task, does NOT contain Events task
        Sanctum::actingAs($this->itLeader);
        $itExport = $this->get('/api/v1/pm/calendar/export.ics');
        $itExport->assertStatus(200);
        $this->assertStringContainsString('text/calendar', $itExport->headers->get('Content-Type'));
        $itContent = $itExport->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $itContent);
        $this->assertStringContainsString('Confidential IT Security Audit', $itContent);
        $this->assertStringNotContainsString('Catering Vendor Selection', $itContent);

        // Events Member exports calendar: contains Events task, does NOT contain confidential IT task
        Sanctum::actingAs($this->eventsMember);
        $eventsExport = $this->get('/api/v1/pm/calendar/export.ics');
        $eventsExport->assertStatus(200);
        $eventsContent = $eventsExport->getContent();
        $this->assertStringContainsString('Catering Vendor Selection', $eventsContent);
        $this->assertStringNotContainsString('Confidential IT Security Audit', $eventsContent);
    }

    /**
     * Test 4: Webcal feed creation, external client token auth, access logging, and feed revocation.
     */
    public function test_webcal_subscription_lifecycle_token_auth_and_access_tracking(): void
    {
        Sanctum::actingAs($this->itLeader);

        PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itPrivateProject->id,
            'title' => 'Server Hardware Deployment',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::High,
            'start_date' => '2026-11-01',
            'due_date' => '2026-11-05',
        ]);

        // Create feed
        $createRes = $this->postJson('/api/v1/pm/calendar/feeds', [
            'name' => 'IT Infrastructure Calendar Sync',
            'project_uuid' => $this->itPrivateProject->uuid,
            'include_tasks' => true,
            'include_milestones' => true,
        ]);
        $createRes->assertStatus(201);
        $plainToken = $createRes->json('data.plain_token');
        $feedUuid = $createRes->json('data.uuid');
        $webcalUrl = $createRes->json('data.webcal_url');

        $this->assertNotEmpty($plainToken);
        $this->assertStringStartsWith('webcal://', $webcalUrl);

        // Verify token is hashed in database (raw token never stored)
        $feed = PmCalendarFeed::where('uuid', $feedUuid)->firstOrFail();
        $this->assertEquals(hash('sha256', $plainToken), $feed->token_hash);
        $this->assertNull($feed->last_accessed_at);

        // Public external client fetch (e.g. Apple Calendar, Google Calendar) without Bearer auth
        $feedRes = $this->get("/api/v1/pm/calendar/feed/{$plainToken}");
        $feedRes->assertStatus(200);
        $this->assertStringContainsString('text/calendar', $feedRes->headers->get('Content-Type'));
        $feedContent = $feedRes->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $feedContent);
        $this->assertStringContainsString('Server Hardware Deployment', $feedContent);

        // Unfold RFC 5545 folded lines (CRLF + space/tab) and assert project name
        $unfolded = preg_replace("/\r\n[ \t]/", '', $feedContent);
        $this->assertStringContainsString('IT Infrastructure Overhaul', $unfolded);

        // Verify access tracking logged IP and timestamp
        $feed->refresh();
        $this->assertNotNull($feed->last_accessed_at);
        $this->assertEquals('127.0.0.1', $feed->last_accessed_ip);

        // Revoke the feed
        $revokeRes = $this->deleteJson("/api/v1/pm/calendar/feeds/{$feedUuid}");
        $revokeRes->assertStatus(200);

        // Subsequent public fetch must return 404
        $afterRevoke = $this->get("/api/v1/pm/calendar/feed/{$plainToken}");
        $afterRevoke->assertStatus(404);
    }
}

