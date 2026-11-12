<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Pm\Enums\DepartmentRole;
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
use App\Services\ApplicationAccessService;
use Database\Seeders\PmDepartmentSeeder;
use Database\Seeders\PmRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PmPhase13Stage3ResourceApisTest extends TestCase
{
    use RefreshDatabase;

    protected Department $itDept;
    protected Department $eventsDept;
    protected User $leadUser;
    protected User $worker1;
    protected User $worker2;
    protected User $outsider;
    protected PmProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PmDepartmentSeeder::class);
        $this->seed(PmRolePermissionSeeder::class);

        $this->itDept = Department::where('slug', 'it')->firstOrFail();
        $this->eventsDept = Department::where('slug', 'events')->firstOrFail();

        $memberRole = Role::firstOrCreate(['slug' => 'member'], ['uuid' => (string) Str::uuid(), 'name' => 'Member']);

        $this->leadUser = User::factory()->create(['email' => 'lead@sfumsa.ca', 'is_active' => true]);
        $this->worker1 = User::factory()->create(['email' => 'worker1@sfumsa.ca', 'is_active' => true]);
        $this->worker2 = User::factory()->create(['email' => 'worker2@sfumsa.ca', 'is_active' => true]);
        $this->outsider = User::factory()->create(['email' => 'outsider@sfumsa.ca', 'is_active' => true]);

        $appAccess = app(ApplicationAccessService::class);
        $deptService = app(DepartmentMembershipService::class);

        foreach ([$this->leadUser, $this->worker1, $this->worker2, $this->outsider] as $u) {
            $u->roles()->sync([$memberRole->id]);
            $appAccess->grant($u, 'projects', $this->leadUser);
        }

        $deptService->addMember($this->itDept, $this->leadUser, DepartmentRole::Lead);
        $deptService->addMember($this->itDept, $this->worker1, DepartmentRole::Member);
        $deptService->addMember($this->itDept, $this->worker2, DepartmentRole::Member);
        $deptService->addMember($this->eventsDept, $this->outsider, DepartmentRole::Member);

        $this->project = PmProject::create([
            'uuid' => (string) Str::uuid(),
            'department_id' => $this->itDept->id,
            'owner_id' => $this->leadUser->id,
            'name' => 'IT Infrastructure Portal',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Medium,
        ]);

        foreach ([$this->worker1, $this->worker2] as $w) {
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
    }

    public function test_project_workload_endpoint_authorizes_and_returns_data(): void
    {
        Sanctum::actingAs($this->leadUser);

        $response = $this->getJson("/api/v1/pm/projects/{$this->project->uuid}/workload?start_date=2026-11-09&end_date=2026-11-13");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'data' => [
                    'project' => ['id', 'uuid', 'name'],
                    'date_range' => ['start', 'end'],
                    'team_workload',
                ],
            ]);

        // Outsider cannot view department private project workload
        Sanctum::actingAs($this->outsider);
        $deniedResponse = $this->getJson("/api/v1/pm/projects/{$this->project->uuid}/workload");
        $deniedResponse->assertStatus(403);
    }

    public function test_department_workload_endpoint_requires_lead_or_admin(): void
    {
        // IT Lead can view IT department workload
        Sanctum::actingAs($this->leadUser);
        $response = $this->getJson("/api/v1/pm/departments/{$this->itDept->id}/workload?start_date=2026-11-09&end_date=2026-11-13");
        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'data' => [
                    'department' => ['id', 'name', 'slug'],
                    'date_range' => ['start', 'end'],
                    'members_workload',
                ],
            ]);

        // Worker1 (regular member) cannot access department-wide workload
        Sanctum::actingAs($this->worker1);
        $workerResponse = $this->getJson("/api/v1/pm/departments/{$this->itDept->id}/workload");
        $workerResponse->assertStatus(403);
    }

    public function test_capacity_endpoints_crud(): void
    {
        Sanctum::actingAs($this->worker1);

        // View default capacity profile
        $showResponse = $this->getJson("/api/v1/pm/resources/capacities/{$this->worker1->id}");
        $showResponse->assertStatus(200)
            ->assertJsonPath('data.user_id', $this->worker1->id);
        $this->assertEquals(10.0, (float) $showResponse->json('data.weekly_capacity_hours'));

        // Worker updates own capacity
        $updateResponse = $this->putJson("/api/v1/pm/resources/capacities/{$this->worker1->id}", [
            'weekly_capacity_hours' => 15.0,
            'daily_capacity_hours' => 3.0,
            'max_concurrent_tasks' => 4,
            'notes' => 'Available on Mondays and Wednesdays',
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.weekly_capacity_hours', '15.00')
            ->assertJsonPath('data.daily_capacity_hours', '3.00');

        $this->assertDatabaseHas('pm_user_capacities', [
            'user_id' => $this->worker1->id,
            'weekly_capacity_hours' => 15.0,
            'daily_capacity_hours' => 3.0,
        ]);

        // Index capacities
        $indexResponse = $this->getJson("/api/v1/pm/resources/capacities?department_id={$this->itDept->id}");
        $indexResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }

    public function test_balancing_suggestions_and_apply(): void
    {
        // Set worker1 capacity: 2h/day
        PmUserCapacity::create([
            'user_id' => $this->worker1->id,
            'weekly_capacity_hours' => 10.0,
            'daily_capacity_hours' => 2.0,
            'created_by' => $this->leadUser->id,
        ]);

        // Set worker2 capacity: 4h/day (available)
        PmUserCapacity::create([
            'user_id' => $this->worker2->id,
            'weekly_capacity_hours' => 20.0,
            'daily_capacity_hours' => 4.0,
            'created_by' => $this->leadUser->id,
        ]);

        // Create an overloaded task for worker1: 8h across 2 days (4h/day > 2h capacity)
        $overloadedTask = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->project->id,
            'title' => 'Server Migration Step 1',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::High,
            'assigned_to' => $this->worker1->id,
            'start_date' => '2026-11-09',
            'due_date' => '2026-11-10',
            'estimated_hours' => 8.0,
            'effort_source' => 'manual',
            'created_by' => $this->leadUser->id,
        ]);

        Sanctum::actingAs($this->leadUser);

        // Fetch suggestions
        $suggResponse = $this->getJson("/api/v1/pm/projects/{$this->project->uuid}/balancing-suggestions");
        $suggResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $suggestions = $suggResponse->json('data');
        $this->assertNotEmpty($suggestions, 'Should generate an advisory suggestion for the overloaded task');
        $this->assertEquals($overloadedTask->uuid, $suggestions[0]['task_uuid']);
        $this->assertEquals($this->worker1->id, $suggestions[0]['source_user']['id']);
        $this->assertEquals($this->worker2->id, $suggestions[0]['suggested_assignee']['id']);

        // Apply suggestion
        $applyResponse = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/balancing-suggestions/apply", [
            'task_uuid' => $overloadedTask->uuid,
            'source_user_id' => $this->worker1->id,
            'target_user_id' => $this->worker2->id,
        ]);

        $applyResponse->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.assigned_to', $this->worker2->id);

        $this->assertDatabaseHas('pm_tasks', [
            'id' => $overloadedTask->id,
            'assigned_to' => $this->worker2->id,
        ]);
    }
}
