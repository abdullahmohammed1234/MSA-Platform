<?php

namespace Tests\Feature\Pm;

use App\Pm\Enums\DepartmentRole;
use App\Models\Department;
use App\Models\User;
use App\Pm\Enums\InvitationStatus;
use App\Pm\Enums\ProjectPriority;
use App\Pm\Enums\ProjectRole;
use App\Pm\Enums\ProjectStatus;
use App\Pm\Enums\ProjectVisibility;
use App\Pm\Enums\TaskStatus;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmProjectMember;
use App\Pm\Models\PmTask;
use App\Pm\Models\PmUserCapacity;
use App\Pm\Services\DepartmentMembershipService;
use App\Pm\Services\PmWorkloadService;
use App\Services\ApplicationAccessService;
use Carbon\Carbon;
use Database\Seeders\PmDepartmentSeeder;
use Database\Seeders\PmRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PmPhase13Stage2WorkloadServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PmWorkloadService $workloadService;
    protected Department $itDept;
    protected Department $eventsDept;
    protected User $leadUser;
    protected User $worker1;
    protected User $worker2;
    protected User $worker3;
    protected PmProject $itProject;
    protected PmProject $eventsProject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PmDepartmentSeeder::class);
        $this->seed(PmRolePermissionSeeder::class);

        $this->workloadService = app(PmWorkloadService::class);

        $this->itDept = Department::where('slug', 'it')->firstOrFail();
        $this->eventsDept = Department::where('slug', 'events')->firstOrFail();

        $this->leadUser = User::factory()->create([
            'email' => 'it.lead@sfumsa.ca',
            'is_active' => true,
        ]);
        $this->worker1 = User::factory()->create([
            'email' => 'worker1@sfumsa.ca',
            'is_active' => true,
        ]);
        $this->worker2 = User::factory()->create([
            'email' => 'worker2@sfumsa.ca',
            'is_active' => true,
        ]);
        $this->worker3 = User::factory()->create([
            'email' => 'worker3@sfumsa.ca',
            'is_active' => true,
        ]);

        $membershipService = app(DepartmentMembershipService::class);
        $appAccessService = app(ApplicationAccessService::class);

        foreach ([$this->leadUser, $this->worker1, $this->worker2, $this->worker3] as $u) {
            $appAccessService->grant($u, 'projects', $this->leadUser);
        }

        $membershipService->addMember($this->itDept, $this->leadUser, DepartmentRole::Lead);
        $membershipService->addMember($this->itDept, $this->worker1, DepartmentRole::Member);
        $membershipService->addMember($this->itDept, $this->worker2, DepartmentRole::Member);
        $membershipService->addMember($this->itDept, $this->worker3, DepartmentRole::Member);

        // Worker1 is also a member of Events department
        $membershipService->addMember($this->eventsDept, $this->worker1, DepartmentRole::Member);

        $this->itProject = PmProject::create([
            'uuid' => (string) Str::uuid(),
            'department_id' => $this->itDept->id,
            'owner_id' => $this->leadUser->id,
            'name' => 'IT Infrastructure Portal',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Medium,
        ]);

        $this->eventsProject = PmProject::create([
            'uuid' => (string) Str::uuid(),
            'department_id' => $this->eventsDept->id,
            'owner_id' => $this->worker1->id,
            'name' => 'Secret Gala Planning',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::High,
        ]);

        foreach ([$this->worker1, $this->worker2, $this->worker3] as $worker) {
            PmProjectMember::create([
                'project_id' => $this->itProject->id,
                'user_id' => $worker->id,
                'project_role' => ProjectRole::Contributor,
                'invitation_status' => InvitationStatus::Accepted,
                'invited_by' => $this->leadUser->id,
                'invited_at' => now(),
                'accepted_at' => now(),
            ]);
        }
    }

    public function test_allocate_task_effort_distributes_equally_with_deterministic_cents(): void
    {
        // 10.00 hours across 3 assignees: 3.34h to primary, 3.33h to others; sum = 10.00h
        $task = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Tri-member Task',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::High,
            'assigned_to' => $this->worker1->id,
            'estimated_hours' => 10.00,
            'effort_source' => 'manual',
            'created_by' => $this->leadUser->id,
        ]);

        $task->assignees()->sync([$this->worker1->id, $this->worker2->id, $this->worker3->id]);

        $allocations = $this->workloadService->allocateTaskEffort($task);

        $this->assertCount(3, $allocations);
        $this->assertEquals(3.34, $allocations[$this->worker1->id], 'Primary assignee receives remainder cent');
        $this->assertEquals(3.33, $allocations[$this->worker2->id]);
        $this->assertEquals(3.33, $allocations[$this->worker3->id]);
        $this->assertEquals(10.00, array_sum($allocations), 'Total sum must equal 10.00 exactly');
    }

    public function test_get_working_days_in_range_and_weekend_fallback(): void
    {
        // Monday 2026-11-09 to Friday 2026-11-13 (5 working days)
        $days = $this->workloadService->getWorkingDaysInRange('2026-11-09', '2026-11-13');
        $this->assertCount(5, $days);
        $this->assertEquals(['2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13'], $days);

        // Saturday to Sunday (weekend only) -> fallback to start date
        $weekendDays = $this->workloadService->getWorkingDaysInRange('2026-11-14', '2026-11-15');
        $this->assertCount(1, $weekendDays);
        $this->assertEquals(['2026-11-14'], $weekendDays);
    }

    public function test_distribute_effort_over_schedule_splits_cents_evenly(): void
    {
        // 5.00 hours across 3 working days: 1.67h, 1.67h, 1.66h = 5.00h
        $distribution = $this->workloadService->distributeEffortOverSchedule(5.00, '2026-11-09', '2026-11-11');

        $this->assertCount(3, $distribution);
        $this->assertEquals(1.67, $distribution['2026-11-09']);
        $this->assertEquals(1.67, $distribution['2026-11-10']);
        $this->assertEquals(1.66, $distribution['2026-11-11']);
        $this->assertEquals(5.00, array_sum($distribution));
    }

    public function test_member_workload_calculation_and_over_allocation(): void
    {
        // Set capacity: 2.0h / day
        PmUserCapacity::create([
            'user_id' => $this->worker2->id,
            'weekly_capacity_hours' => 10.00,
            'daily_capacity_hours' => 2.00,
            'max_concurrent_tasks' => 3,
            'created_by' => $this->leadUser->id,
        ]);

        // Task with 6.00 hours across 2 working days (Mon-Tue) -> 3.0h / day (> 2.0h capacity => 150% over-allocated)
        PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Heavy Migration Task',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::Urgent,
            'assigned_to' => $this->worker2->id,
            'start_date' => '2026-11-09',
            'due_date' => '2026-11-10',
            'estimated_hours' => 6.00,
            'effort_source' => 'manual',
            'created_by' => $this->leadUser->id,
        ]);

        $workload = $this->workloadService->getMemberWorkload(
            member: $this->worker2,
            startDate: '2026-11-09',
            endDate: '2026-11-13',
            viewer: $this->leadUser,
            projectScope: $this->itProject
        );

        $this->assertTrue($workload['summary']['is_over_allocated']);
        $this->assertEquals(2, $workload['summary']['over_allocated_days']);
        $this->assertEquals(150.0, $workload['summary']['max_daily_utilization']);
        $this->assertEquals('over_allocated', $workload['daily_breakdown']['2026-11-09']['status']);
        $this->assertEquals('over_allocated', $workload['daily_breakdown']['2026-11-10']['status']);
        $this->assertEquals('available', $workload['daily_breakdown']['2026-11-11']['status']);
    }

    public function test_option_b_cross_project_confidential_anonymization(): void
    {
        // Worker1 has a task in IT (4.0h) on 2026-11-09 to 2026-11-10 (2.0h/day)
        PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->itProject->id,
            'title' => 'Public IT Setup',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'assigned_to' => $this->worker1->id,
            'start_date' => '2026-11-09',
            'due_date' => '2026-11-10',
            'estimated_hours' => 4.00,
            'effort_source' => 'manual',
            'created_by' => $this->leadUser->id,
        ]);

        // Worker1 ALSO has a private task in Events (Secret Gala Planning) (4.0h) on 2026-11-09 to 2026-11-10 (2.0h/day)
        PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->eventsProject->id,
            'title' => 'Top Secret Gala Budget',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::High,
            'assigned_to' => $this->worker1->id,
            'start_date' => '2026-11-09',
            'due_date' => '2026-11-10',
            'estimated_hours' => 4.00,
            'effort_source' => 'manual',
            'created_by' => $this->worker1->id,
        ]);

        // When IT Lead (who is NOT in Events) views Worker1's workload for IT Project
        $workload = $this->workloadService->getMemberWorkload(
            member: $this->worker1,
            startDate: '2026-11-09',
            endDate: '2026-11-13',
            viewer: $this->leadUser,
            projectScope: $this->itProject
        );

        // Daily allocated hours must include BOTH tasks (2.0 + 2.0 = 4.0h/day)
        $this->assertEquals(4.0, $workload['daily_breakdown']['2026-11-09']['allocated_hours']);
        $this->assertEquals(2.0, $workload['daily_breakdown']['2026-11-09']['external_hours']);

        // Inspect tasks in workload
        $taskTitles = array_column($workload['tasks'], 'title');
        $this->assertContains('Public IT Setup', $taskTitles);
        $this->assertNotContains('Top Secret Gala Budget', $taskTitles, 'Secret task title must NOT leak');
        $this->assertContains('Confidential External Commitment', $taskTitles, 'Must be anonymized');

        // Verify confidential task has null project_id and is marked confidential
        $confidentialTasks = array_filter($workload['tasks'], fn ($t) => $t['is_confidential'] === true);
        $this->assertNotEmpty($confidentialTasks);
        $confTask = array_values($confidentialTasks)[0];
        $this->assertNull($confTask['project_id']);
        $this->assertNull($confTask['project_name']);
        $this->assertEquals('Confidential External Commitment', $confTask['title']);
    }

    public function test_project_team_workload_aggregation(): void
    {
        $teamWorkload = $this->workloadService->getProjectTeamWorkload(
            project: $this->itProject,
            startDate: '2026-11-09',
            endDate: '2026-11-13',
            viewer: $this->leadUser
        );

        $this->assertEquals($this->itProject->uuid, $teamWorkload['project']['uuid']);
        $this->assertNotEmpty($teamWorkload['team_workload']);

        // Team should include all eligible assignees in IT
        $userIds = array_map(fn ($m) => $m['user']['id'], $teamWorkload['team_workload']);
        $this->assertContains($this->leadUser->id, $userIds);
        $this->assertContains($this->worker1->id, $userIds);
        $this->assertContains($this->worker2->id, $userIds);
        $this->assertContains($this->worker3->id, $userIds);
    }
}
