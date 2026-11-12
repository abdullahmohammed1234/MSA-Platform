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
use App\Pm\Services\DepartmentMembershipService;
use App\Services\ApplicationAccessService;
use Database\Seeders\PmDepartmentSeeder;
use Database\Seeders\PmRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PmPhase13Stage4BulkOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected Department $itDept;
    protected User $leadUser;
    protected User $worker1;
    protected User $worker2;
    protected PmProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PmDepartmentSeeder::class);
        $this->seed(PmRolePermissionSeeder::class);

        $this->itDept = Department::where('slug', 'it')->firstOrFail();
        $memberRole = Role::firstOrCreate(['slug' => 'member'], ['uuid' => (string) Str::uuid(), 'name' => 'Member']);

        $this->leadUser = User::factory()->create(['email' => 'it.lead@sfumsa.ca', 'is_active' => true]);
        $this->worker1 = User::factory()->create(['email' => 'worker1@sfumsa.ca', 'is_active' => true]);
        $this->worker2 = User::factory()->create(['email' => 'worker2@sfumsa.ca', 'is_active' => true]);

        $appAccess = app(ApplicationAccessService::class);
        $deptService = app(DepartmentMembershipService::class);

        foreach ([$this->leadUser, $this->worker1, $this->worker2] as $u) {
            $u->roles()->sync([$memberRole->id]);
            $appAccess->grant($u, 'projects', $this->leadUser);
        }

        $deptService->addMember($this->itDept, $this->leadUser, DepartmentRole::Lead);
        $deptService->addMember($this->itDept, $this->worker1, DepartmentRole::Member);
        $deptService->addMember($this->itDept, $this->worker2, DepartmentRole::Member);

        $this->project = PmProject::create([
            'uuid' => (string) Str::uuid(),
            'department_id' => $this->itDept->id,
            'owner_id' => $this->leadUser->id,
            'name' => 'Bulk Operations Test Project',
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

    public function test_bulk_reschedule_shift_and_effort_preservation(): void
    {
        Sanctum::actingAs($this->leadUser);

        // Task 1: manual effort (15h) from 2026-11-09 to 2026-11-10
        $manualTask = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->project->id,
            'title' => 'Manual Estimate Task',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::High,
            'assigned_to' => $this->worker1->id,
            'start_date' => '2026-11-09',
            'due_date' => '2026-11-10',
            'estimated_hours' => 15.0,
            'effort_source' => 'manual',
            'created_by' => $this->leadUser->id,
        ]);

        // Task 2: inferred effort from 2026-11-09 to 2026-11-10 (2 days * 2h = 4h)
        $inferredTask = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->project->id,
            'title' => 'Inferred Estimate Task',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'assigned_to' => $this->worker1->id,
            'start_date' => '2026-11-09',
            'due_date' => '2026-11-10',
            'estimated_hours' => 4.0,
            'effort_source' => 'inferred',
            'created_by' => $this->leadUser->id,
        ]);

        // Shift by +3 days (Mon 09 -> Thu 12, Tue 10 -> Fri 13)
        $response = $this->postJson('/api/v1/pm/tasks/bulk', [
            'project_uuid' => $this->project->uuid,
            'task_uuids' => [$manualTask->uuid, $inferredTask->uuid],
            'action' => 'reschedule_shift',
            'value' => ['shift_days' => 3],
        ]);

        $response->assertStatus(200);

        $manualTask->refresh();
        $inferredTask->refresh();

        $this->assertEquals('2026-11-12', $manualTask->start_date->toDateString());
        $this->assertEquals('2026-11-13', $manualTask->due_date->toDateString());
        $this->assertEquals(15.0, (float) $manualTask->estimated_hours, 'Manual effort must be preserved');

        $this->assertEquals('2026-11-12', $inferredTask->start_date->toDateString());
        $this->assertEquals('2026-11-13', $inferredTask->due_date->toDateString());
        $this->assertEquals(4.0, (float) $inferredTask->estimated_hours, 'Inferred effort matches 2 working days');
    }

    public function test_bulk_set_dates_validates_order(): void
    {
        Sanctum::actingAs($this->leadUser);

        $task = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->project->id,
            'title' => 'Set Dates Task',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'assigned_to' => $this->worker1->id,
            'created_by' => $this->leadUser->id,
        ]);

        // Inverted dates rejected with 422
        $invalidResponse = $this->postJson('/api/v1/pm/tasks/bulk', [
            'project_uuid' => $this->project->uuid,
            'task_uuids' => [$task->uuid],
            'action' => 'set_dates',
            'value' => [
                'start_date' => '2026-11-15',
                'due_date' => '2026-11-10',
            ],
        ]);

        $invalidResponse->assertStatus(422);

        // Valid dates accepted
        $validResponse = $this->postJson('/api/v1/pm/tasks/bulk', [
            'project_uuid' => $this->project->uuid,
            'task_uuids' => [$task->uuid],
            'action' => 'set_dates',
            'value' => [
                'start_date' => '2026-11-10',
                'due_date' => '2026-11-12',
            ],
        ]);

        $validResponse->assertStatus(200);
        $task->refresh();
        $this->assertEquals('2026-11-10', $task->start_date->toDateString());
        $this->assertEquals('2026-11-12', $task->due_date->toDateString());
    }

    public function test_bulk_rebalance_assignee_transfers_safely(): void
    {
        Sanctum::actingAs($this->leadUser);

        $task = PmTask::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $this->project->id,
            'title' => 'Transfer Task',
            'status' => TaskStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'assigned_to' => $this->worker1->id,
            'created_by' => $this->leadUser->id,
        ]);
        $task->assignees()->sync([$this->worker1->id]);

        $response = $this->postJson('/api/v1/pm/tasks/bulk', [
            'project_uuid' => $this->project->uuid,
            'task_uuids' => [$task->uuid],
            'action' => 'rebalance_assignee',
            'value' => [
                'source_user_id' => $this->worker1->id,
                'target_user_id' => $this->worker2->id,
            ],
        ]);

        $response->assertStatus(200);

        $task->refresh();
        $this->assertEquals($this->worker2->id, $task->assigned_to);
        $this->assertTrue($task->assignees->contains('id', $this->worker2->id));
        $this->assertFalse($task->assignees->contains('id', $this->worker1->id));
    }
}

