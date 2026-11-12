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
use App\Pm\Models\PmActivityLog;
use App\Pm\Models\PmMilestone;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmProjectMember;
use App\Pm\Models\PmTask;
use App\Pm\Support\PmRoles;
use App\Services\ApplicationAccessService;
use Database\Seeders\PmDepartmentSeeder;
use Database\Seeders\PmRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PmStagingWorkflowEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $president;
    protected User $executive;
    protected User $jumuahLead;
    protected User $jumuahCoordinator;
    protected User $contributorUser;
    protected User $viewerUser;
    protected User $marketingUser;
    protected User $volunteerUser;

    protected Department $jumuahDept;
    protected Department $marketingDept;
    protected ApplicationAccessService $appAccessService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PmDepartmentSeeder::class);
        $this->seed(PmRolePermissionSeeder::class);

        $this->appAccessService = app(ApplicationAccessService::class);

        $this->jumuahDept = Department::where('slug', 'jumuah')->firstOrFail();
        $this->marketingDept = Department::where('slug', 'marketing')->firstOrFail();

        $memberRole = Role::firstOrCreate(['slug' => 'member'], ['uuid' => (string) Str::uuid(), 'name' => 'Member']);
        $volunteerRole = Role::firstOrCreate(['slug' => 'volunteer'], ['uuid' => (string) Str::uuid(), 'name' => 'Volunteer']);
        $adminRole = Role::firstOrCreate(['slug' => 'super-admin'], ['uuid' => (string) Str::uuid(), 'name' => 'Super Admin']);
        $presidentRole = Role::firstOrCreate(['slug' => 'president'], ['uuid' => (string) Str::uuid(), 'name' => 'President']);
        $executiveRole = Role::firstOrCreate(['slug' => 'executive'], ['uuid' => (string) Str::uuid(), 'name' => 'Executive']);

        $this->superAdmin = User::factory()->create(['name' => 'Platform Super Admin', 'email' => 'superadmin@sfumsa.ca']);
        $this->superAdmin->roles()->sync([$adminRole->id]);

        $this->president = User::factory()->create(['name' => 'MSA President', 'email' => 'president@sfumsa.ca']);
        $this->president->roles()->sync([$presidentRole->id]);

        $this->executive = User::factory()->create(['name' => 'MSA Executive', 'email' => 'executive@sfumsa.ca']);
        $this->executive->roles()->sync([$executiveRole->id]);

        $this->jumuahLead = User::factory()->create(['name' => 'Jumuah Lead Brother', 'email' => 'jumuah.lead@sfumsa.ca']);
        $this->jumuahLead->roles()->sync([$memberRole->id]);

        $this->jumuahCoordinator = User::factory()->create(['name' => 'Jumuah Coordinator Sister', 'email' => 'jumuah.coord@sfumsa.ca']);
        $this->jumuahCoordinator->roles()->sync([$memberRole->id]);

        $this->contributorUser = User::factory()->create(['name' => 'Jumuah Task Contributor', 'email' => 'contributor@sfumsa.ca']);
        $this->contributorUser->roles()->sync([$memberRole->id]);

        $this->viewerUser = User::factory()->create(['name' => 'Observer Viewer', 'email' => 'viewer@sfumsa.ca']);
        $this->viewerUser->roles()->sync([$memberRole->id]);

        $this->marketingUser = User::factory()->create(['name' => 'Marketing Team Rep', 'email' => 'marketing.rep@sfumsa.ca']);
        $this->marketingUser->roles()->sync([$memberRole->id]);

        $this->volunteerUser = User::factory()->create(['name' => 'Public Volunteer', 'email' => 'volunteer@sfumsa.ca']);
        $this->volunteerUser->roles()->sync([$volunteerRole->id]);
    }

    /**
     * Complete Functional End-to-End Workflow & Authorization Audit (20 steps).
     */
    public function test_complete_twenty_step_staging_workflow_and_authorization_matrix(): void
    {
        // -------------------------------------------------------------
        // Step 1: Admin grants PMS access through Admin Portal
        // -------------------------------------------------------------
        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/admin/pms/access/grant', [
                'user_id' => $this->jumuahLead->id,
                'reason' => 'Appointed Jumuah Lead Fall 2026',
            ])
            ->assertOk()
            ->assertJson(['has_pms_access' => true]);

        $this->assertDatabaseHas('application_access', [
            'user_id' => $this->jumuahLead->id,
            'application' => 'projects',
        ]);

        // Grant access to coordinator, contributor, viewer, and marketingUser
        $this->appAccessService->grant($this->jumuahCoordinator, 'projects');
        $this->appAccessService->grant($this->contributorUser, 'projects');
        $this->appAccessService->grant($this->viewerUser, 'projects');
        $this->appAccessService->grant($this->marketingUser, 'projects');
        $this->appAccessService->grant($this->president, 'projects');
        $this->appAccessService->grant($this->executive, 'projects');

        // -------------------------------------------------------------
        // Step 2: Assign user to Jumu'ah department
        // -------------------------------------------------------------
        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/admin/pms/access/departments/assign', [
                'user_id' => $this->jumuahLead->id,
                'department_id' => $this->jumuahDept->id,
                'department_role' => DepartmentRole::Lead->value,
            ])
            ->assertOk();

        $this->assertDatabaseHas('pm_department_memberships', [
            'department_id' => $this->jumuahDept->id,
            'user_id' => $this->jumuahLead->id,
            'department_role' => DepartmentRole::Lead->value,
            'status' => 'active',
        ]);

        // Assign marketing user to Marketing department
        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/admin/pms/access/departments/assign', [
                'user_id' => $this->marketingUser->id,
                'department_id' => $this->marketingDept->id,
                'department_role' => DepartmentRole::Member->value,
            ])
            ->assertOk();

        // -------------------------------------------------------------
        // Step 3: Login through normal flow and query dashboard
        // -------------------------------------------------------------
        $dashboardRes = $this->actingAs($this->jumuahLead, 'sanctum')
            ->getJson('/api/v1/pm/dashboard')
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Jumuah Lead Brother');

        $this->assertTrue(collect($dashboardRes->json('data.departments'))->contains('slug', 'jumuah'));

        // -------------------------------------------------------------
        // Step 4: Create a department-private Jumu'ah project
        // -------------------------------------------------------------
        $projectRes = $this->actingAs($this->jumuahLead, 'sanctum')
            ->postJson('/api/v1/pm/projects', [
                'name' => 'Burnaby Hall Khateeb Logistics 2026',
                'department_id' => $this->jumuahDept->id,
                'visibility' => 'department_private',
                'priority' => 'high',
                'description' => 'Weekly Friday prayer organization, speaker hospitality, and facility setup.',
                'start_date' => now()->toDateString(),
                'due_date' => now()->addMonths(3)->toDateString(),
            ])
            ->assertStatus(201);

        $projectUuid = $projectRes->json('project.uuid');
        $projectId = $projectRes->json('project.id');
        $this->assertNotEmpty($projectUuid);

        // Verify project ownership in DB
        $this->assertDatabaseHas('pm_projects', [
            'id' => $projectId,
            'owner_id' => $this->jumuahLead->id,
            'visibility' => ProjectVisibility::DepartmentPrivate->value,
        ]);

        // -------------------------------------------------------------
        // Step 5: Add project members (manager, contributor, viewer)
        // -------------------------------------------------------------
        // Invite coordinator as Manager
        $this->actingAs($this->jumuahLead, 'sanctum')
            ->postJson("/api/v1/pm/projects/{$projectUuid}/members/invite", [
                'user_id' => $this->jumuahCoordinator->id,
                'project_role' => ProjectRole::Manager->value,
            ])
            ->assertStatus(201);

        // Invite contributor as Contributor
        $this->actingAs($this->jumuahLead, 'sanctum')
            ->postJson("/api/v1/pm/projects/{$projectUuid}/members/invite", [
                'user_id' => $this->contributorUser->id,
                'project_role' => ProjectRole::Contributor->value,
            ])
            ->assertStatus(201);

        // Invite viewer as Viewer
        $this->actingAs($this->jumuahLead, 'sanctum')
            ->postJson("/api/v1/pm/projects/{$projectUuid}/members/invite", [
                'user_id' => $this->viewerUser->id,
                'project_role' => ProjectRole::Viewer->value,
            ])
            ->assertStatus(201);

        // Automatically accept their invitations for internal members
        PmProjectMember::where('project_id', $projectId)->update(['invitation_status' => InvitationStatus::Accepted]);

        // -------------------------------------------------------------
        // Step 6: Create milestones
        // -------------------------------------------------------------
        $milestoneRes = $this->actingAs($this->jumuahCoordinator, 'sanctum')
            ->postJson("/api/v1/pm/projects/{$projectUuid}/milestones", [
                'title' => 'Khateeb Confirmation Phase',
                'description' => 'Secure all 12 khateebs for Fall semester',
                'due_date' => now()->addMonth()->toDateString(),
            ])
            ->assertStatus(201);

        $milestoneId = $milestoneRes->json('milestone.id');
        $this->assertNotEmpty($milestoneId);

        // -------------------------------------------------------------
        // Step 7: Create tasks and assign them
        // -------------------------------------------------------------
        $taskRes = $this->actingAs($this->jumuahCoordinator, 'sanctum')
            ->postJson("/api/v1/pm/projects/{$projectUuid}/tasks", [
                'title' => 'Confirm Week 1 Khateeb Booking',
                'description' => 'Reach out to local Sheikh for the opening semester khutbah.',
                'milestone_id' => $milestoneId,
                'assigned_to' => $this->contributorUser->id,
                'priority' => 'high',
                'due_date' => now()->addDays(7)->toDateString(),
            ])
            ->assertStatus(201);

        $taskId = $taskRes->json('task.id');
        $taskUuid = $taskRes->json('task.uuid');
        $this->assertNotEmpty($taskId);
        $this->assertNotEmpty($taskUuid);

        // -------------------------------------------------------------
        // Step 8: Move tasks across Kanban statuses
        // -------------------------------------------------------------
        $this->actingAs($this->contributorUser, 'sanctum')
            ->patchJson("/api/v1/pm/tasks/{$taskUuid}/status", [
                'status' => TaskStatus::InProgress->value,
            ])
            ->assertOk()
            ->assertJsonPath('task.status', TaskStatus::InProgress->value);

        $this->actingAs($this->contributorUser, 'sanctum')
            ->patchJson("/api/v1/pm/tasks/{$taskUuid}/status", [
                'status' => TaskStatus::Completed->value,
            ])
            ->assertOk()
            ->assertJsonPath('task.status', TaskStatus::Completed->value);

        // -------------------------------------------------------------
        // Step 9: Add comments
        // -------------------------------------------------------------
        $commentRes = $this->actingAs($this->contributorUser, 'sanctum')
            ->postJson("/api/v1/pm/projects/{$projectUuid}/comments", [
                'task_id' => $taskId,
                'body' => 'Sheikh confirmed for Week 1 Friday prayer in Burnaby Hall.',
            ])
            ->assertStatus(201);

        $this->assertEquals('Sheikh confirmed for Week 1 Friday prayer in Burnaby Hall.', $commentRes->json('data.body'));

        // -------------------------------------------------------------
        // Step 10: Verify activity history records changes
        // -------------------------------------------------------------
        $this->assertDatabaseHas('pm_activity_logs', [
            'project_id' => $projectId,
            'action' => 'project_created',
        ]);

        $this->assertDatabaseHas('pm_comments', [
            'project_id' => $projectId,
            'task_id' => $taskId,
            'user_id' => $this->contributorUser->id,
        ]);

        // -------------------------------------------------------------
        // Step 11: Verify calendar entries query
        // -------------------------------------------------------------
        $calendarRes = $this->actingAs($this->jumuahLead, 'sanctum')
            ->getJson('/api/v1/pm/calendar/events')
            ->assertOk();

        $events = collect($calendarRes->json('data'));
        $this->assertTrue($events->contains('id', "task-{$taskId}"));

        // -------------------------------------------------------------
        // Step 12: Test visibility using external Marketing member -> 403
        // -------------------------------------------------------------
        $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$projectUuid}")
            ->assertStatus(403);

        // Super Admin, President, and Executive zero bypass checks on Jumuah private project
        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$projectUuid}")
            ->assertStatus(403);

        $this->actingAs($this->president, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$projectUuid}")
            ->assertStatus(403);

        $this->actingAs($this->executive, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$projectUuid}")
            ->assertStatus(403);

        // -------------------------------------------------------------
        // Step 13: Invite Marketing member to project
        // -------------------------------------------------------------
        $inviteRes = $this->actingAs($this->jumuahLead, 'sanctum')
            ->postJson("/api/v1/pm/projects/{$projectUuid}/members/invite", [
                'user_id' => $this->marketingUser->id,
                'project_role' => ProjectRole::Contributor->value,
            ])
            ->assertStatus(201);

        $invitationId = $inviteRes->json('member.id');

        // -------------------------------------------------------------
        // Step 14: Confirm pending invitation provides NO access
        // -------------------------------------------------------------
        $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$projectUuid}")
            ->assertStatus(403);

        // -------------------------------------------------------------
        // Step 15: Accept the invitation
        // -------------------------------------------------------------
        $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson("/api/v1/pm/invitations/{$invitationId}/accept")
            ->assertOk();

        // -------------------------------------------------------------
        // Step 16: Confirm access granted per project role (Contributor)
        // -------------------------------------------------------------
        $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$projectUuid}")
            ->assertOk()
            ->assertJsonPath('project.name', 'Burnaby Hall Khateeb Logistics 2026');

        // Contributor can create task
        $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson("/api/v1/pm/projects/{$projectUuid}/tasks", [
                'title' => 'Design Friday Social Banner',
                'priority' => 'medium',
            ])
            ->assertStatus(201);

        // Contributor CANNOT delete project
        $this->actingAs($this->marketingUser, 'sanctum')
            ->deleteJson("/api/v1/pm/projects/{$projectUuid}")
            ->assertStatus(403);

        // -------------------------------------------------------------
        // Step 17: Revoke PMS application access of marketing user
        // -------------------------------------------------------------
        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/admin/pms/access/revoke', [
                'user_id' => $this->marketingUser->id,
                'reason' => 'Audit check',
            ])
            ->assertOk();

        // -------------------------------------------------------------
        // Step 18: Confirm access is denied afterward
        // -------------------------------------------------------------
        $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$projectUuid}")
            ->assertStatus(403);

        $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/v1/pm/dashboard')
            ->assertStatus(403);

        // -------------------------------------------------------------
        // Step 19: Create a shared MSA project
        // -------------------------------------------------------------
        $sharedProjectRes = $this->actingAs($this->jumuahLead, 'sanctum')
            ->postJson('/api/v1/pm/projects', [
                'name' => 'Campus-Wide Ramadan Preparation 2027',
                'department_id' => $this->jumuahDept->id,
                'visibility' => 'shared_msa',
                'priority' => 'urgent',
                'description' => 'Collaborative multi-department preparation for Ramadan.',
            ])
            ->assertStatus(201);

        $sharedUuid = $sharedProjectRes->json('project.uuid');

        // -------------------------------------------------------------
        // Step 20: Other authorized PMS users can view shared project,
        // while ungranted volunteer is rejected
        // -------------------------------------------------------------
        $this->actingAs($this->jumuahCoordinator, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$sharedUuid}")
            ->assertOk();

        $this->actingAs($this->viewerUser, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$sharedUuid}")
            ->assertOk();

        // Self-registered volunteer without PMS grant is rejected
        $this->actingAs($this->volunteerUser, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$sharedUuid}")
            ->assertStatus(403);
    }
}
