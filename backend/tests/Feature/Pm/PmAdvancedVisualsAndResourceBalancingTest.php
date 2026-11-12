<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Pm\Enums\DependencyType;
use App\Pm\Enums\DepartmentRole;
use App\Pm\Enums\InvitationStatus;
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
use App\Pm\Models\PmTaskList;
use App\Pm\Models\PmUserCapacity;
use App\Pm\Services\DepartmentMembershipService;
use App\Services\ApplicationAccessService;
use Database\Seeders\PmDepartmentSeeder;
use Database\Seeders\PmRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PmAdvancedVisualsAndResourceBalancingTest extends TestCase
{
    use RefreshDatabase;

    protected Department $itDept;
    protected Department $graphicsDept;
    protected User $leadUser;
    protected User $contributor1;
    protected User $contributor2;
    protected User $outsider;
    protected PmProject $project;
    protected PmProject $confidentialProject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PmDepartmentSeeder::class);
        $this->seed(PmRolePermissionSeeder::class);

        $this->itDept = Department::where('slug', 'it')->firstOrFail();
        $this->graphicsDept = Department::where('slug', 'graphics')->firstOrFail();

        $memberRole = Role::firstOrCreate(['slug' => 'member'], ['uuid' => (string) Str::uuid(), 'name' => 'Member']);

        $this->leadUser = User::factory()->create(['email' => 'lead@sfumsa.ca', 'is_active' => true]);
        $this->contributor1 = User::factory()->create(['email' => 'contrib1@sfumsa.ca', 'is_active' => true]);
        $this->contributor2 = User::factory()->create(['email' => 'contrib2@sfumsa.ca', 'is_active' => true]);
        $this->outsider = User::factory()->create(['email' => 'outsider@sfumsa.ca', 'is_active' => true]);

        $appAccess = app(ApplicationAccessService::class);
        $deptService = app(DepartmentMembershipService::class);

        foreach ([$this->leadUser, $this->contributor1, $this->contributor2, $this->outsider] as $u) {
            $u->roles()->sync([$memberRole->id]);
            $appAccess->grant($u, 'projects', $this->leadUser);
        }

        $deptService->addMember($this->itDept, $this->leadUser, DepartmentRole::Lead);
        $deptService->addMember($this->itDept, $this->contributor1, DepartmentRole::Member);
        $deptService->addMember($this->itDept, $this->contributor2, DepartmentRole::Member);
        $deptService->addMember($this->graphicsDept, $this->outsider, DepartmentRole::Member);
        // Contributor1 is also in graphics
        $deptService->addMember($this->graphicsDept, $this->contributor1, DepartmentRole::Member);

        // IT Private Project
        $this->project = PmProject::create([
            'uuid' => (string) Str::uuid(),
            'department_id' => $this->itDept->id,
            'owner_id' => $this->leadUser->id,
            'name' => 'IT Core Visuals Engine',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::High,
        ]);

        foreach ([$this->contributor1, $this->contributor2] as $w) {
            PmProjectMember::create([
                'project_id' => $this->project->id,
                'user_id' => $w->id,
                'project_role' => ProjectRole::Contributor,
                'invitation_status' => InvitationStatus::Accepted,
                'invited_by' => $this->leadUser->id,
                'invited_at' => now(),
                'accepted_at' => now(),
            ]);
        }

        // Graphics Private Project (Confidential to IT Lead)
        $this->confidentialProject = PmProject::create([
            'uuid' => (string) Str::uuid(),
            'department_id' => $this->graphicsDept->id,
            'owner_id' => $this->outsider->id,
            'name' => 'Secret Rebranding Graphics',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::High,
        ]);

        PmProjectMember::create([
            'project_id' => $this->confidentialProject->id,
            'user_id' => $this->contributor1->id,
            'project_role' => ProjectRole::Contributor,
            'invitation_status' => InvitationStatus::Accepted,
            'invited_by' => $this->outsider->id,
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);
    }

    public function test_gantt_chart_dataset_pipeline(): void
    {
        Sanctum::actingAs($this->leadUser);

        $section = PmTaskList::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->project->id,
            'name' => 'Architecture Section',
            'order_index' => 1,
        ]);

        $milestone = PmMilestone::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->project->id,
            'title' => 'Alpha Release',
            'due_date' => '2026-11-20',
            'status' => MilestoneStatus::Pending,
        ]);

        $task1 = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->project->id,
            'task_list_id' => $section->id,
            'milestone_id' => $milestone->id,
            'title' => 'Design Architecture',
            'status' => TaskStatus::Completed,
            'priority' => ProjectPriority::High,
            'assigned_to' => $this->contributor1->id,
            'start_date' => '2026-11-09',
            'due_date' => '2026-11-11',
            'estimated_hours' => 6.0,
            'effort_source' => 'manual',
            'created_by' => $this->leadUser->id,
        ]);

        $task2 = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->project->id,
            'task_list_id' => $section->id,
            'title' => 'Implement SVG Overlay',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'assigned_to' => $this->contributor2->id,
            'start_date' => '2026-11-10', // Starts before task1 finishes -> Conflict for FS!
            'due_date' => '2026-11-13',
            'estimated_hours' => 8.0,
            'effort_source' => 'manual',
            'created_by' => $this->leadUser->id,
        ]);

        // Finish-to-Start dependency with conflict
        PmTaskDependency::create([
            'project_id' => $this->project->id,
            'task_id' => $task2->id,
            'depends_on_task_id' => $task1->id,
            'dependency_type' => DependencyType::FinishToStart,
            'lag_days' => 0,
        ]);

        $response = $this->getJson("/api/v1/pm/projects/{$this->project->uuid}/gantt");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.project.uuid', $this->project->uuid)
            ->assertJsonCount(2, 'data.tasks')
            ->assertJsonCount(1, 'data.dependencies')
            ->assertJsonCount(1, 'data.milestones')
            ->assertJsonCount(1, 'data.task_lists');

        $dep = $response->json('data.dependencies.0');
        $this->assertTrue($dep['is_conflict'], 'FS violation detected where successor starts before predecessor finishes');
        $this->assertNotNull($dep['conflict_reason']);
    }

    public function test_end_to_end_workload_and_option_b_anonymization(): void
    {
        // Contributor 1 has 6h in IT (Nov 9-11)
        PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->project->id,
            'title' => 'Public IT Setup',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'assigned_to' => $this->contributor1->id,
            'start_date' => '2026-11-09',
            'due_date' => '2026-11-11',
            'estimated_hours' => 6.0,
            'effort_source' => 'manual',
            'created_by' => $this->leadUser->id,
        ]);

        // Contributor 1 has 6h in Confidential Graphics Project (Nov 9-11)
        PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->confidentialProject->id,
            'title' => 'Top Secret Rebranding Strategy',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::High,
            'assigned_to' => $this->contributor1->id,
            'start_date' => '2026-11-09',
            'due_date' => '2026-11-11',
            'estimated_hours' => 6.0,
            'effort_source' => 'manual',
            'created_by' => $this->outsider->id,
        ]);

        Sanctum::actingAs($this->leadUser);

        $response = $this->getJson("/api/v1/pm/projects/{$this->project->uuid}/workload?start_date=2026-11-09&end_date=2026-11-13");
        $response->assertStatus(200);

        $teamWorkload = $response->json('data.team_workload');
        $c1Workload = collect($teamWorkload)->firstWhere('user.id', $this->contributor1->id);
        $this->assertNotNull($c1Workload);

        // Daily allocated hours = 2.0h (IT) + 2.0h (Graphics) = 4.0h/day
        $this->assertEquals(4.0, $c1Workload['daily_breakdown']['2026-11-09']['allocated_hours']);
        $this->assertEquals(2.0, $c1Workload['daily_breakdown']['2026-11-09']['external_hours']);

        // Zero title leakage
        $taskTitles = array_column($c1Workload['tasks'], 'title');
        $this->assertContains('Public IT Setup', $taskTitles);
        $this->assertNotContains('Top Secret Rebranding Strategy', $taskTitles);
        $this->assertContains('Confidential External Commitment', $taskTitles);
    }

    public function test_advisory_resource_balancing_and_reassignment_execution(): void
    {
        // Set capacity
        PmUserCapacity::create([
            'user_id' => $this->contributor1->id,
            'weekly_capacity_hours' => 10.0,
            'daily_capacity_hours' => 2.0,
            'created_by' => $this->leadUser->id,
        ]);
        PmUserCapacity::create([
            'user_id' => $this->contributor2->id,
            'weekly_capacity_hours' => 20.0,
            'daily_capacity_hours' => 4.0,
            'created_by' => $this->leadUser->id,
        ]);

        // Over-allocated task on contributor 1
        $task = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->project->id,
            'title' => 'Heavy Balancing Candidate Task',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::High,
            'assigned_to' => $this->contributor1->id,
            'start_date' => '2026-11-09',
            'due_date' => '2026-11-10',
            'estimated_hours' => 8.0, // 4.0h/day vs 2.0h capacity = 200%
            'effort_source' => 'manual',
            'created_by' => $this->leadUser->id,
        ]);

        Sanctum::actingAs($this->leadUser);

        // Fetch suggestions
        $suggRes = $this->getJson("/api/v1/pm/projects/{$this->project->uuid}/balancing-suggestions");
        $suggRes->assertStatus(200);
        $suggestions = $suggRes->json('data');
        $this->assertNotEmpty($suggestions);
        $this->assertEquals($task->uuid, $suggestions[0]['task_uuid']);
        $this->assertEquals($this->contributor2->id, $suggestions[0]['suggested_assignee']['id']);

        // Execute reassignment
        $applyRes = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/balancing-suggestions/apply", [
            'task_uuid' => $task->uuid,
            'source_user_id' => $this->contributor1->id,
            'target_user_id' => $this->contributor2->id,
        ]);

        $applyRes->assertStatus(200);
        $task->refresh();
        $this->assertEquals($this->contributor2->id, $task->assigned_to);
    }
}

