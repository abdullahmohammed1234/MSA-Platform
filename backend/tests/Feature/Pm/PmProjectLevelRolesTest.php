<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Pm\Enums\DepartmentRole;
use App\Pm\Enums\ProjectPriority;
use App\Pm\Enums\ProjectRole;
use App\Pm\Enums\ProjectStatus;
use App\Pm\Enums\ProjectVisibility;
use App\Pm\Enums\TaskStatus;
use App\Pm\Models\PmActivityLog;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmProjectMember;
use App\Pm\Models\PmTask;
use App\Pm\Services\DepartmentMembershipService;
use App\Pm\Services\ProjectAccessService;
use App\Services\ApplicationAccessService;
use Database\Seeders\PmDepartmentSeeder;
use Database\Seeders\PmRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PmProjectLevelRolesTest extends TestCase
{
    use RefreshDatabase;

    protected ApplicationAccessService $appAccessService;
    protected DepartmentMembershipService $deptService;
    protected ProjectAccessService $projectAccessService;

    protected Department $itDept;
    protected User $adminUser;
    protected PmProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PmDepartmentSeeder::class);
        $this->seed(PmRolePermissionSeeder::class);

        $this->appAccessService = app(ApplicationAccessService::class);
        $this->deptService = app(DepartmentMembershipService::class);
        $this->projectAccessService = app(ProjectAccessService::class);

        $this->itDept = Department::where('slug', 'it')->firstOrFail();

        $this->adminUser = User::factory()->create();
        $adminRole = Role::where('slug', 'super-admin')->firstOrFail();
        $this->adminUser->roles()->sync([$adminRole->id]);
        $this->appAccessService->grant($this->adminUser, 'projects', $this->adminUser);

        $ownerUser = $this->createPmsUser('team-member');
        $this->deptService->addMember($this->itDept, $ownerUser, DepartmentRole::Member);

        $this->project = PmProject::create([
            'department_id' => $this->itDept->id,
            'name' => 'Project Role Matrix Test',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'owner_id' => $ownerUser->id,
        ]);

        PmProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $ownerUser->id,
            'project_role' => ProjectRole::Owner,
        ]);
    }

    private function createPmsUser(string $pmsRole = 'team-member'): User
    {
        $role = Role::where('slug', $pmsRole)->firstOrFail();
        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);
        $this->appAccessService->grant($user, 'projects', $this->adminUser);

        return $user;
    }

    private function addProjectMember(User $user, ProjectRole $role): PmProjectMember
    {
        $this->deptService->addMember($this->itDept, $user, DepartmentRole::Member);

        return PmProjectMember::create([
            'project_id' => $this->project->id,
            'user_id' => $user->id,
            'project_role' => $role,
        ]);
    }

    /** @test */
    public function owner_has_full_management_capabilities_including_deletion()
    {
        $owner = User::find($this->project->owner_id);

        $this->assertTrue($this->projectAccessService->canViewProject($owner, $this->project));
        $this->assertTrue($this->projectAccessService->canUpdateProject($owner, $this->project));
        $this->assertTrue($this->projectAccessService->canDeleteProject($owner, $this->project));
        $this->assertTrue($this->projectAccessService->canAssignProjectRole($owner, $this->project, ProjectRole::Manager));
        $this->assertTrue($this->projectAccessService->canAssignProjectRole($owner, $this->project, ProjectRole::Owner));
        $this->assertTrue($this->projectAccessService->canCreateTask($owner, $this->project));
    }

    /** @test */
    public function manager_can_update_project_and_manage_tasks_but_cannot_delete_project()
    {
        $manager = $this->createPmsUser('team-member');
        $this->addProjectMember($manager, ProjectRole::Manager);

        $this->assertTrue($this->projectAccessService->canViewProject($manager, $this->project));
        $this->assertTrue($this->projectAccessService->canUpdateProject($manager, $this->project));
        $this->assertFalse(
            $this->projectAccessService->canDeleteProject($manager, $this->project),
            'Project Manager must NOT be able to delete the project.'
        );

        // Can assign Contributor or Viewer or Manager
        $this->assertTrue($this->projectAccessService->canAssignProjectRole($manager, $this->project, ProjectRole::Contributor));
        $this->assertTrue($this->projectAccessService->canAssignProjectRole($manager, $this->project, ProjectRole::Viewer));
        $this->assertTrue($this->projectAccessService->canAssignProjectRole($manager, $this->project, ProjectRole::Manager));

        // Manager CANNOT transfer or assign Owner
        $this->assertFalse(
            $this->projectAccessService->canAssignProjectRole($manager, $this->project, ProjectRole::Owner),
            'Project Manager must NOT be able to assign or transfer Owner role.'
        );

        $this->assertTrue($this->projectAccessService->canCreateTask($manager, $this->project));
    }

    /** @test */
    public function contributor_can_create_and_update_tasks_but_cannot_manage_project_or_assign_roles()
    {
        $contributor = $this->createPmsUser('team-member');
        $this->addProjectMember($contributor, ProjectRole::Contributor);

        $this->assertTrue($this->projectAccessService->canViewProject($contributor, $this->project));
        $this->assertFalse(
            $this->projectAccessService->canUpdateProject($contributor, $this->project),
            'Contributor cannot update project settings.'
        );
        $this->assertFalse(
            $this->projectAccessService->canDeleteProject($contributor, $this->project),
            'Contributor cannot delete project.'
        );
        $this->assertFalse(
            $this->projectAccessService->canAssignProjectRole($contributor, $this->project, ProjectRole::Contributor),
            'Contributor cannot assign project roles.'
        );
        $this->assertTrue($this->projectAccessService->canCreateTask($contributor, $this->project));
        $this->assertTrue($this->projectAccessService->canAddComment($contributor, $this->project));
    }

    /** @test */
    public function viewer_is_strictly_read_only()
    {
        $viewer = $this->createPmsUser('team-member');
        $this->addProjectMember($viewer, ProjectRole::Viewer);

        $task = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Sample Task',
            'status' => TaskStatus::Todo,
            'priority' => ProjectPriority::Medium,
            'created_by' => $this->project->owner_id,
        ]);

        $this->assertTrue(
            $this->projectAccessService->canViewProject($viewer, $this->project),
            'Viewer must be able to view project.'
        );
        $this->assertFalse(
            $this->projectAccessService->canUpdateProject($viewer, $this->project),
            'Viewer cannot update project.'
        );
        $this->assertFalse(
            $this->projectAccessService->canDeleteProject($viewer, $this->project),
            'Viewer cannot delete project.'
        );
        $this->assertFalse(
            $this->projectAccessService->canAssignProjectRole($viewer, $this->project, ProjectRole::Viewer),
            'Viewer cannot assign roles.'
        );
        $this->assertFalse(
            $this->projectAccessService->canCreateTask($viewer, $this->project),
            'Viewer cannot create tasks.'
        );
        $this->assertFalse(
            $this->projectAccessService->canUpdateTask($viewer, $task),
            'Viewer cannot update tasks.'
        );
        $this->assertFalse(
            $this->projectAccessService->canDeleteTask($viewer, $task),
            'Viewer cannot delete tasks.'
        );
        $this->assertFalse(
            $this->projectAccessService->canAddComment($viewer, $this->project),
            'Viewer cannot add comments.'
        );
    }

    /** @test */
    public function viewer_cannot_create_or_modify_tasks_via_api()
    {
        $viewer = $this->createPmsUser('team-member');
        $this->addProjectMember($viewer, ProjectRole::Viewer);

        $task = PmTask::create([
            'project_id' => $this->project->id,
            'title' => 'Initial Task',
            'status' => TaskStatus::Todo,
            'priority' => ProjectPriority::Medium,
            'created_by' => $this->project->owner_id,
        ]);

        Sanctum::actingAs($viewer);

        // Attempt task creation
        $createResponse = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/tasks", [
            'title' => 'Viewer Task Attempt',
            'status' => 'todo',
            'priority' => 'medium',
        ]);
        $createResponse->assertStatus(403);

        // Attempt task update
        $updateResponse = $this->putJson("/api/v1/pm/tasks/{$task->uuid}", [
            'title' => 'Viewer Modified Title',
        ]);
        $updateResponse->assertStatus(403);

        // Attempt task status update
        $statusResponse = $this->patchJson("/api/v1/pm/tasks/{$task->uuid}/status", [
            'status' => 'in_progress',
        ]);
        $statusResponse->assertStatus(403);

        // Attempt task deletion
        $deleteResponse = $this->deleteJson("/api/v1/pm/tasks/{$task->uuid}");
        $deleteResponse->assertStatus(403);

        // Attempt comment creation
        $commentResponse = $this->postJson("/api/v1/pm/projects/{$this->project->uuid}/comments", [
            'body' => 'Viewer comment attempt',
        ]);
        $commentResponse->assertStatus(403);
    }

    /** @test */
    public function project_member_role_can_be_assigned_and_updated_with_audit_trail()
    {
        $owner = User::find($this->project->owner_id);
        $targetUser = $this->createPmsUser('team-member');
        $memberRecord = $this->addProjectMember($targetUser, ProjectRole::Contributor);

        Sanctum::actingAs($owner);

        // Owner promotes Contributor to Manager
        $response = $this->putJson("/api/v1/pm/projects/{$this->project->uuid}/members/{$memberRecord->id}/role", [
            'project_role' => 'manager',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'member' => [
                'project_role' => 'manager',
                'project_role_label' => 'Manager',
            ],
        ]);

        $this->assertEquals(
            ProjectRole::Manager,
            $memberRecord->fresh()->project_role
        );

        // Verify audit log entry
        $this->assertDatabaseHas('pm_activity_logs', [
            'project_id' => $this->project->id,
            'user_id' => $owner->id,
            'action' => 'project_member_role_updated',
        ]);
    }

    /** @test */
    public function contributor_cannot_escalate_roles()
    {
        $contributor = $this->createPmsUser('team-member');
        $this->addProjectMember($contributor, ProjectRole::Contributor);

        $targetUser = $this->createPmsUser('team-member');
        $targetMember = $this->addProjectMember($targetUser, ProjectRole::Viewer);

        Sanctum::actingAs($contributor);

        $response = $this->putJson("/api/v1/pm/projects/{$this->project->uuid}/members/{$targetMember->id}/role", [
            'project_role' => 'manager',
        ]);

        $response->assertStatus(403);
    }
}
