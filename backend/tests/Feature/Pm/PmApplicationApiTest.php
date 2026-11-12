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
use App\Pm\Models\PmMilestone;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmProjectMember;
use App\Pm\Models\PmTask;
use App\Pm\Services\DepartmentMembershipService;
use Database\Seeders\PmDepartmentSeeder;
use Database\Seeders\PmRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PmApplicationApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $itLead;
    protected User $itMember;
    protected User $eventsLead;
    protected User $volunteerUser;
    protected Department $itDept;
    protected Department $eventsDept;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PmDepartmentSeeder::class);
        $this->seed(PmRolePermissionSeeder::class);

        $memberRole = Role::firstOrCreate(['slug' => 'member'], ['uuid' => (string) Str::uuid(), 'name' => 'Member']);
        $volunteerRole = Role::firstOrCreate(['slug' => 'volunteer'], ['uuid' => (string) Str::uuid(), 'name' => 'Volunteer']);

        $this->itDept = Department::where('slug', 'it')->firstOrFail();
        $this->eventsDept = Department::where('slug', 'events')->firstOrFail();

        $deptService = app(DepartmentMembershipService::class);

        // IT Lead
        $this->itLead = User::factory()->create(['name' => 'IT Lead']);
        $this->itLead->roles()->sync([$memberRole->id]);
        $deptService->addMember($this->itDept, $this->itLead, DepartmentRole::Lead);

        // IT Member
        $this->itMember = User::factory()->create(['name' => 'IT Member']);
        $this->itMember->roles()->sync([$memberRole->id]);
        $deptService->addMember($this->itDept, $this->itMember, DepartmentRole::Member);

        // Events Lead
        $this->eventsLead = User::factory()->create(['name' => 'Events Lead']);
        $this->eventsLead->roles()->sync([$memberRole->id]);
        $deptService->addMember($this->eventsDept, $this->eventsLead, DepartmentRole::Lead);

        // Self-registered Volunteer with NO department membership
        $this->volunteerUser = User::factory()->create(['name' => 'Volunteer Person']);
        $this->volunteerUser->roles()->sync([$volunteerRole->id]);

        // Explicitly grant PMS application access to authorized team members
        $appAccessService = app(\App\Services\ApplicationAccessService::class);
        $appAccessService->grant($this->itLead, 'projects');
        $appAccessService->grant($this->itMember, 'projects');
        $appAccessService->grant($this->eventsLead, 'projects');
    }

    /** @test */
    public function volunteer_without_pms_access_is_forbidden()
    {
        $this->actingAs($this->volunteerUser, 'sanctum')
            ->getJson('/api/v1/pm/dashboard')
            ->assertStatus(403);
    }

    /** @test */
    public function department_member_without_explicit_application_access_grant_is_forbidden()
    {
        $memberRole = Role::where('slug', 'member')->firstOrFail();
        $unauthorizedDeptMember = User::factory()->create(['name' => 'IT Member Without App Access']);
        $unauthorizedDeptMember->roles()->sync([$memberRole->id]);
        app(DepartmentMembershipService::class)->addMember($this->itDept, $unauthorizedDeptMember, DepartmentRole::Member);

        // Attempting to access PMS dashboard without explicit app grant must return 403
        $this->actingAs($unauthorizedDeptMember, 'sanctum')
            ->getJson('/api/v1/pm/dashboard')
            ->assertStatus(403);

        // Once explicitly granted, access is allowed
        $appAccessService = app(\App\Services\ApplicationAccessService::class);
        $appAccessService->grant($unauthorizedDeptMember, 'projects');

        $this->actingAs($unauthorizedDeptMember, 'sanctum')
            ->getJson('/api/v1/pm/dashboard')
            ->assertStatus(200);

        // When application access is revoked, subsequent requests are forbidden
        $appAccessService->revoke($unauthorizedDeptMember, 'projects');

        $this->actingAs($unauthorizedDeptMember, 'sanctum')
            ->getJson('/api/v1/pm/dashboard')
            ->assertStatus(403);
    }

    /** @test */
    public function authorized_team_member_can_view_pms_dashboard()
    {
        $response = $this->actingAs($this->itLead, 'sanctum')
            ->getJson('/api/v1/pm/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user',
                    'departments',
                    'kpi',
                    'my_tasks',
                    'recent_projects',
                    'activity_stream',
                ],
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals('IT Lead', $response->json('data.user.name'));
    }

    /** @test */
    public function team_member_can_create_and_manage_project()
    {
        // 1. Create project
        $createRes = $this->actingAs($this->itLead, 'sanctum')
            ->postJson('/api/v1/pm/projects', [
                'name' => 'Platform Redesign 2026',
                'department_id' => $this->itDept->id,
                'visibility' => 'department_private',
                'priority' => 'high',
                'description' => 'Comprehensive redesign of the digital platform.',
            ])
            ->assertStatus(201);

        $uuid = $createRes->json('project.uuid');
        $this->assertNotEmpty($uuid);

        // 2. Fetch project details
        $this->actingAs($this->itLead, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$uuid}")
            ->assertStatus(200)
            ->assertJsonPath('project.name', 'Platform Redesign 2026')
            ->assertJsonPath('project.visibility', 'department_private');

        // 3. Update project details
        $this->actingAs($this->itLead, 'sanctum')
            ->putJson("/api/v1/pm/projects/{$uuid}", [
                'name' => 'Platform Redesign 2026 v2',
                'priority' => 'urgent',
            ])
            ->assertStatus(200)
            ->assertJsonPath('project.name', 'Platform Redesign 2026 v2')
            ->assertJsonPath('project.priority', 'urgent');
    }

    /** @test */
    public function department_private_project_is_inaccessible_to_other_departments()
    {
        // IT Lead creates a department-private project
        $project = PmProject::create([
            'name' => 'Internal IT Infrastructure',
            'department_id' => $this->itDept->id,
            'owner_id' => $this->itLead->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Medium,
        ]);

        // IT Member can view
        $this->actingAs($this->itMember, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$project->uuid}")
            ->assertStatus(200);

        // Events Lead is forbidden
        $this->actingAs($this->eventsLead, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$project->uuid}")
            ->assertStatus(403);

        // Events Lead listing projects does NOT include the IT private project
        $listRes = $this->actingAs($this->eventsLead, 'sanctum')
            ->getJson('/api/v1/pm/projects')
            ->assertStatus(200);

        $projects = collect($listRes->json('projects'));
        $this->assertFalse($projects->contains('uuid', $project->uuid));
    }

    /** @test */
    public function cross_department_invitation_flow_grants_access_upon_acceptance()
    {
        // IT Lead creates private project
        $project = PmProject::create([
            'name' => 'Joint IT-Events App',
            'department_id' => $this->itDept->id,
            'owner_id' => $this->itLead->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::High,
        ]);

        // Events Lead cannot view initially
        $this->actingAs($this->eventsLead, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$project->uuid}")
            ->assertStatus(403);

        // IT Lead invites Events Lead as a Contributor
        $inviteRes = $this->actingAs($this->itLead, 'sanctum')
            ->postJson("/api/v1/pm/projects/{$project->uuid}/members/invite", [
                'user_id' => $this->eventsLead->id,
                'project_role' => 'contributor',
            ])
            ->assertStatus(201);

        $memberId = $inviteRes->json('member.id');

        // Events Lead checks their pending invitations
        $myInvitesRes = $this->actingAs($this->eventsLead, 'sanctum')
            ->getJson('/api/v1/pm/invitations/me')
            ->assertStatus(200);

        $this->assertTrue(collect($myInvitesRes->json('data'))->contains('id', $memberId));

        // Before accepting, Events Lead still cannot view
        $this->actingAs($this->eventsLead, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$project->uuid}")
            ->assertStatus(403);

        // Events Lead accepts the invitation
        $this->actingAs($this->eventsLead, 'sanctum')
            ->postJson("/api/v1/pm/invitations/{$memberId}/accept")
            ->assertStatus(200);

        // Now Events Lead CAN view the project
        $this->actingAs($this->eventsLead, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$project->uuid}")
            ->assertStatus(200);
    }

    /** @test */
    public function milestone_lifecycle_works_correctly()
    {
        $project = PmProject::create([
            'name' => 'Milestone Test Project',
            'department_id' => $this->itDept->id,
            'owner_id' => $this->itLead->id,
            'visibility' => ProjectVisibility::SharedMsa,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Medium,
        ]);

        // Create milestone
        $createRes = $this->actingAs($this->itLead, 'sanctum')
            ->postJson("/api/v1/pm/projects/{$project->uuid}/milestones", [
                'title' => 'Alpha Launch',
                'description' => 'Initial internal alpha preview',
                'due_date' => now()->addDays(14)->toDateString(),
            ])
            ->assertStatus(201);

        $msUuid = $createRes->json('milestone.uuid');

        // Update milestone
        $this->actingAs($this->itLead, 'sanctum')
            ->putJson("/api/v1/pm/milestones/{$msUuid}", [
                'title' => 'Alpha Launch Final',
                'status' => 'reached',
            ])
            ->assertStatus(200)
            ->assertJsonPath('milestone.title', 'Alpha Launch Final');

        // Delete milestone
        $this->actingAs($this->itLead, 'sanctum')
            ->deleteJson("/api/v1/pm/milestones/{$msUuid}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('pm_milestones', ['uuid' => $msUuid]);
    }

    /** @test */
    public function task_lifecycle_and_assignment_works_correctly()
    {
        $project = PmProject::create([
            'name' => 'Task Test Project',
            'department_id' => $this->itDept->id,
            'owner_id' => $this->itLead->id,
            'visibility' => ProjectVisibility::SharedMsa,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Medium,
        ]);

        PmProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $this->itMember->id,
            'project_role' => ProjectRole::Contributor,
            'invitation_status' => 'accepted',
        ]);

        // Create task assigned to IT Member
        $createRes = $this->actingAs($this->itLead, 'sanctum')
            ->postJson("/api/v1/pm/projects/{$project->uuid}/tasks", [
                'title' => 'Configure OAuth Provider',
                'description' => 'Setup SSO with Google and MSA Platform',
                'priority' => 'high',
                'status' => 'todo',
                'assigned_to' => $this->itMember->id,
                'due_date' => now()->addDays(3)->toDateString(),
            ])
            ->assertStatus(201);

        $taskUuid = $createRes->json('task.uuid');

        // IT Member views their tasks
        $myTasksRes = $this->actingAs($this->itMember, 'sanctum')
            ->getJson('/api/v1/pm/tasks/me')
            ->assertStatus(200);

        $this->assertTrue(collect($myTasksRes->json('tasks'))->contains('uuid', $taskUuid));

        // Update task status to in_progress
        $this->actingAs($this->itMember, 'sanctum')
            ->patchJson("/api/v1/pm/tasks/{$taskUuid}/status", [
                'status' => 'in_progress',
            ])
            ->assertStatus(200)
            ->assertJsonPath('task.status', 'in_progress');

        // Update task status to completed
        $this->actingAs($this->itMember, 'sanctum')
            ->patchJson("/api/v1/pm/tasks/{$taskUuid}/status", [
                'status' => 'completed',
            ])
            ->assertStatus(200)
            ->assertJsonPath('task.status', 'completed');
    }

    /** @test */
    public function comments_can_be_posted_and_deleted()
    {
        $project = PmProject::create([
            'name' => 'Comment Project',
            'department_id' => $this->itDept->id,
            'owner_id' => $this->itLead->id,
            'visibility' => ProjectVisibility::SharedMsa,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Medium,
        ]);

        $task = PmTask::create([
            'project_id' => $project->id,
            'created_by' => $this->itLead->id,
            'title' => 'Test Task for Comments',
        ]);

        // IT Member posts comment
        $commentRes = $this->actingAs($this->itMember, 'sanctum')
            ->postJson("/api/v1/pm/projects/{$project->uuid}/comments", [
                'body' => 'I have reviewed the task specs.',
                'task_uuid' => $task->uuid,
            ])
            ->assertStatus(201);

        $commentId = $commentRes->json('data.id');

        // List comments
        $this->actingAs($this->itLead, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$project->uuid}/comments?task_uuid={$task->uuid}")
            ->assertStatus(200)
            ->assertJsonPath('data.0.body', 'I have reviewed the task specs.');

        // Delete comment
        $this->actingAs($this->itMember, 'sanctum')
            ->deleteJson("/api/v1/pm/projects/{$project->uuid}/comments/{$commentId}")
            ->assertStatus(200);

        $this->assertSoftDeleted('pm_comments', ['id' => $commentId]);
    }

    /** @test */
    public function calendar_events_are_scoped_to_authorized_projects()
    {
        // IT private project with deadline
        $itProject = PmProject::create([
            'name' => 'IT Secret Deployment',
            'department_id' => $this->itDept->id,
            'owner_id' => $this->itLead->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Urgent,
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        // Shared project with deadline
        $sharedProject = PmProject::create([
            'name' => 'Annual MSA Banquet',
            'department_id' => $this->eventsDept->id,
            'owner_id' => $this->eventsLead->id,
            'visibility' => ProjectVisibility::SharedMsa,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::High,
            'due_date' => now()->addDays(10)->toDateString(),
        ]);

        // IT Member sees both (member of IT + shared project)
        $itCalendarRes = $this->actingAs($this->itMember, 'sanctum')
            ->getJson('/api/v1/pm/calendar/events')
            ->assertStatus(200);

        $itEvents = collect($itCalendarRes->json('data'));
        $this->assertTrue($itEvents->contains('id', "project-{$itProject->id}"));
        $this->assertTrue($itEvents->contains('id', "project-{$sharedProject->id}"));

        // Events Lead sees only shared project, NOT IT private project
        $eventsCalendarRes = $this->actingAs($this->eventsLead, 'sanctum')
            ->getJson('/api/v1/pm/calendar/events')
            ->assertStatus(200);

        $eventsEvents = collect($eventsCalendarRes->json('data'));
        $this->assertFalse($eventsEvents->contains('id', "project-{$itProject->id}"));
        $this->assertTrue($eventsEvents->contains('id', "project-{$sharedProject->id}"));
    }

    public function test_authorized_pms_user_can_list_canonical_departments(): void
    {
        $response = $this->actingAs($this->itMember, 'sanctum')
            ->getJson('/api/v1/pm/departments')
            ->assertOk();

        $departments = collect($response->json('departments'));
        $this->assertCount(8, $departments);

        $expectedOrder = [
            'presidential',
            'marketing',
            'finance',
            'education',
            'graphics',
            'events',
            'it',
            'jumuah',
        ];

        $actualSlugs = $departments->pluck('slug')->all();
        $this->assertEquals($expectedOrder, $actualSlugs);

        $jumuah = $departments->firstWhere('slug', 'jumuah');
        $this->assertNotNull($jumuah);
        $this->assertEquals("Jumu'ah", $jumuah['name']);
        $this->assertEquals(8, $jumuah['display_order']);
    }
}
