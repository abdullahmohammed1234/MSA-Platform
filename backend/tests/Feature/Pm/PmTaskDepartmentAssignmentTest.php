<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\User;
use App\Pm\Enums\ProjectPriority;
use App\Pm\Enums\ProjectRole;
use App\Pm\Enums\ProjectStatus;
use App\Pm\Enums\ProjectVisibility;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmProjectMember;
use App\Pm\Models\PmTask;
use App\Services\ApplicationAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PmTaskDepartmentAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $contributor;
    protected Department $owningDept;
    protected Department $participatingDept;
    protected Department $unrelatedDept;
    protected PmProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        $appAccess = app(ApplicationAccessService::class);

        $this->owner = User::factory()->create(['name' => 'Dept Owner']);
        $this->contributor = User::factory()->create(['name' => 'Dept Contributor']);
        $appAccess->grant($this->owner, 'projects');
        $appAccess->grant($this->contributor, 'projects');

        $this->owningDept = Department::create([
            'name' => 'Owning Department',
            'code' => 'OWN',
            'slug' => 'owning-department',
            'is_active' => true,
        ]);
        $this->participatingDept = Department::create([
            'name' => 'Participating Department',
            'code' => 'PART',
            'slug' => 'participating-department',
            'is_active' => true,
        ]);
        $this->unrelatedDept = Department::create([
            'name' => 'Unrelated Department',
            'code' => 'UNREL',
            'slug' => 'unrelated-department',
            'is_active' => true,
        ]);

        $this->project = PmProject::create([
            'name' => 'Department Assignment Project',
            'slug' => 'department-assignment-project',
            'visibility' => ProjectVisibility::SharedMsa,
            'department_id' => $this->owningDept->id,
            'owner_id' => $this->owner->id,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'created_by' => $this->owner->id,
        ]);
        $this->project->participatingDepartments()->attach($this->participatingDept->id, [
            'created_by' => $this->owner->id,
        ]);

        PmProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'project_role' => ProjectRole::Owner,
            'invitation_status' => 'accepted',
        ]);
        PmProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $this->contributor->id,
            'project_role' => ProjectRole::Contributor,
            'invitation_status' => 'accepted',
        ]);
    }

    #[Test]
    public function can_create_task_with_owning_department(): void
    {
        $response = $this->actingAs($this->owner)->postJson(
            "/api/v1/pm/projects/{$this->project->uuid}/tasks",
            [
                'title' => 'Owning Dept Task',
                'department_id' => $this->owningDept->id,
                'assignee_ids' => [$this->contributor->id],
                'assigned_to' => $this->contributor->id,
            ]
        );

        $response->assertCreated();
        $response->assertJsonPath('task.department_id', $this->owningDept->id);
        $response->assertJsonPath('task.department.name', 'Owning Department');
        $this->assertDatabaseHas('pm_tasks', [
            'uuid' => $response->json('task.uuid'),
            'department_id' => $this->owningDept->id,
        ]);
    }

    #[Test]
    public function can_create_task_with_participating_department(): void
    {
        $response = $this->actingAs($this->owner)->postJson(
            "/api/v1/pm/projects/{$this->project->uuid}/tasks",
            [
                'title' => 'Participating Dept Task',
                'department_id' => $this->participatingDept->id,
            ]
        );

        $response->assertCreated();
        $response->assertJsonPath('task.department_id', $this->participatingDept->id);
    }

    #[Test]
    public function can_create_task_without_department(): void
    {
        $response = $this->actingAs($this->owner)->postJson(
            "/api/v1/pm/projects/{$this->project->uuid}/tasks",
            ['title' => 'No Dept Task']
        );

        $response->assertCreated();
        $response->assertJsonPath('task.department_id', null);
    }

    #[Test]
    public function rejects_unrelated_department_on_create(): void
    {
        $response = $this->actingAs($this->owner)->postJson(
            "/api/v1/pm/projects/{$this->project->uuid}/tasks",
            [
                'title' => 'Bad Dept Task',
                'department_id' => $this->unrelatedDept->id,
            ]
        );

        $response->assertStatus(422);
        $this->assertDatabaseMissing('pm_tasks', ['title' => 'Bad Dept Task']);
    }

    #[Test]
    public function can_update_and_clear_task_department(): void
    {
        $task = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Editable Dept Task',
            'department_id' => $this->owningDept->id,
            'created_by' => $this->owner->id,
            'priority' => ProjectPriority::Medium,
        ]);

        $update = $this->actingAs($this->owner)->putJson("/api/v1/pm/tasks/{$task->uuid}", [
            'department_id' => $this->participatingDept->id,
        ]);
        $update->assertOk();
        $update->assertJsonPath('task.department_id', $this->participatingDept->id);

        $clear = $this->actingAs($this->owner)->putJson("/api/v1/pm/tasks/{$task->uuid}", [
            'department_id' => null,
        ]);
        $clear->assertOk();
        $clear->assertJsonPath('task.department_id', null);
    }

    #[Test]
    public function rejects_unrelated_department_on_update(): void
    {
        $task = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Protected Dept Task',
            'created_by' => $this->owner->id,
            'priority' => ProjectPriority::Medium,
        ]);

        $response = $this->actingAs($this->owner)->putJson("/api/v1/pm/tasks/{$task->uuid}", [
            'department_id' => $this->unrelatedDept->id,
        ]);

        $response->assertStatus(422);
        $this->assertNull($task->fresh()->department_id);
    }

    #[Test]
    public function can_filter_tasks_by_department(): void
    {
        PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Filter Match',
            'department_id' => $this->owningDept->id,
            'created_by' => $this->owner->id,
            'priority' => ProjectPriority::Medium,
        ]);
        PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Filter Miss',
            'department_id' => $this->participatingDept->id,
            'created_by' => $this->owner->id,
            'priority' => ProjectPriority::Medium,
        ]);

        $response = $this->actingAs($this->owner)->getJson(
            "/api/v1/pm/projects/{$this->project->uuid}/tasks?department_id={$this->owningDept->id}"
        );

        $response->assertOk();
        $titles = collect($response->json('tasks'))->pluck('title');
        $this->assertTrue($titles->contains('Filter Match'));
        $this->assertFalse($titles->contains('Filter Miss'));
    }

    #[Test]
    public function multi_assignee_still_works_with_department(): void
    {
        $response = $this->actingAs($this->owner)->postJson(
            "/api/v1/pm/projects/{$this->project->uuid}/tasks",
            [
                'title' => 'Multi Assignee Dept Task',
                'department_id' => $this->owningDept->id,
                'assignee_ids' => [$this->owner->id, $this->contributor->id],
                'assigned_to' => $this->contributor->id,
            ]
        );

        $response->assertCreated();
        $this->assertCount(2, $response->json('task.assignees'));
        $response->assertJsonPath('task.department_id', $this->owningDept->id);
    }
}
