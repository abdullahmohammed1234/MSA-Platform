<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\User;
use App\Pm\Enums\DepartmentRole;
use App\Pm\Enums\InvitationStatus;
use App\Pm\Enums\ProjectPriority;
use App\Pm\Enums\ProjectRole;
use App\Pm\Enums\ProjectStatus;
use App\Pm\Enums\ProjectVisibility;
use App\Pm\Models\PmActivityLog;
use App\Pm\Models\PmDepartmentMembership;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmProjectMember;
use App\Pm\Services\DepartmentMembershipService;
use App\Pm\Services\ProjectAccessService;
use App\Services\ApplicationAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class PmMultiDepartmentProjectTest extends TestCase
{
    use RefreshDatabase;

    protected DepartmentMembershipService $deptService;
    protected ProjectAccessService $accessService;
    protected ApplicationAccessService $appAccessService;

    protected Department $presidentialDept;
    protected Department $marketingDept;
    protected Department $financeDept;
    protected Department $educationDept;
    protected Department $graphicsDept;
    protected Department $eventsDept;
    protected Department $itDept;
    protected Department $jumuahDept;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PmDepartmentSeeder::class);
        $this->seed(\Database\Seeders\PmRolePermissionSeeder::class);

        $this->deptService = app(DepartmentMembershipService::class);
        $this->accessService = app(ProjectAccessService::class);
        $this->appAccessService = app(ApplicationAccessService::class);

        $this->presidentialDept = Department::where('slug', 'presidential')->firstOrFail();
        $this->marketingDept = Department::where('slug', 'marketing')->firstOrFail();
        $this->financeDept = Department::where('slug', 'finance')->firstOrFail();
        $this->educationDept = Department::where('slug', 'education')->firstOrFail();
        $this->graphicsDept = Department::where('slug', 'graphics')->firstOrFail();
        $this->eventsDept = Department::where('slug', 'events')->firstOrFail();
        $this->itDept = Department::where('slug', 'it')->firstOrFail();
        $this->jumuahDept = Department::where('slug', 'jumuah')->firstOrFail();
    }

    protected function createUserWithPmsAccess(string $role = 'member'): User
    {
        $user = User::factory()->create([
            'uuid' => (string) Str::uuid(),
            'is_active' => true,
        ]);

        $this->appAccessService->grant($user, 'projects');

        return $user;
    }

    // =========================================================================
    // 1. Department Dropdown / Registry Tests
    // =========================================================================

    public function test_department_endpoint_returns_all_eight_canonical_departments_with_codes_and_ordering(): void
    {
        $user = $this->createUserWithPmsAccess();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/pm/departments');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $departments = $response->json('departments');
        $this->assertCount(8, $departments);

        $expectedOrder = [
            ['slug' => 'presidential', 'code' => 'PRE', 'order' => 1],
            ['slug' => 'marketing', 'code' => 'MAR', 'order' => 2],
            ['slug' => 'finance', 'code' => 'FIN', 'order' => 3],
            ['slug' => 'education', 'code' => 'EDU', 'order' => 4],
            ['slug' => 'graphics', 'code' => 'GRA', 'order' => 5],
            ['slug' => 'events', 'code' => 'EVE', 'order' => 6],
            ['slug' => 'it', 'code' => 'IT', 'order' => 7],
            ['slug' => 'jumuah', 'code' => 'JUM', 'order' => 8],
        ];

        foreach ($expectedOrder as $index => $expected) {
            $this->assertSame($expected['slug'], $departments[$index]['slug']);
            $this->assertSame($expected['code'], $departments[$index]['code']);
            $this->assertSame($expected['order'], $departments[$index]['display_order']);
            $this->assertArrayHasKey('id', $departments[$index]);
            $this->assertArrayHasKey('name', $departments[$index]);
        }
    }

    public function test_department_endpoint_denies_unauthenticated_or_unauthorized_users(): void
    {
        $response = $this->getJson('/api/v1/pm/departments');
        $response->assertUnauthorized();

        $userWithoutPms = User::factory()->create(['is_active' => true]);
        $response2 = $this->actingAs($userWithoutPms, 'sanctum')
            ->getJson('/api/v1/pm/departments');
        $response2->assertForbidden();
    }

    // =========================================================================
    // 2. Multi-Department Project Creation & Validation Tests
    // =========================================================================

    public function test_create_project_with_owning_and_multiple_participating_departments(): void
    {
        $eventsLead = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->eventsDept, $eventsLead, DepartmentRole::Lead);

        $payload = [
            'name' => 'Ramadan Charity Campaign',
            'owning_department_id' => $this->eventsDept->id,
            'participating_department_ids' => [
                $this->marketingDept->id,
                $this->graphicsDept->id,
                $this->itDept->id,
            ],
            'visibility' => 'department_private',
            'priority' => 'high',
            'description' => 'Cross-department Ramadan initiative.',
        ];

        $response = $this->actingAs($eventsLead, 'sanctum')
            ->postJson('/api/v1/pm/projects', $payload);

        $response->assertCreated()
            ->assertJsonPath('project.name', 'Ramadan Charity Campaign')
            ->assertJsonPath('project.department_id', $this->eventsDept->id);

        $projectUuid = $response->json('project.uuid');
        $project = PmProject::where('uuid', $projectUuid)->firstOrFail();

        $this->assertSame($this->eventsDept->id, (int) $project->department_id);
        $participatingIds = $project->participatingDepartments()->pluck('departments.id')->all();
        sort($participatingIds);

        $expectedIds = [$this->marketingDept->id, $this->graphicsDept->id, $this->itDept->id];
        sort($expectedIds);

        $this->assertSame($expectedIds, $participatingIds);

        // Check activity log
        $this->assertDatabaseHas('pm_activity_logs', [
            'project_id' => $project->id,
            'action' => 'project_created',
        ]);
    }

    public function test_create_project_rejects_owning_department_as_participating_department(): void
    {
        $eventsLead = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->eventsDept, $eventsLead, DepartmentRole::Lead);

        $payload = [
            'name' => 'Invalid Department Assignment Project',
            'owning_department_id' => $this->eventsDept->id,
            'participating_department_ids' => [
                $this->marketingDept->id,
                $this->eventsDept->id, // DUPLICATE OWNING
            ],
            'visibility' => 'department_private',
        ];

        $response = $this->actingAs($eventsLead, 'sanctum')
            ->postJson('/api/v1/pm/projects', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('participating_department_ids');
    }

    public function test_create_project_handles_legacy_department_id_parameter(): void
    {
        $eventsLead = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->eventsDept, $eventsLead, DepartmentRole::Lead);

        $payload = [
            'name' => 'Legacy Parameter Project',
            'department_id' => $this->eventsDept->id,
            'participating_department_ids' => [$this->marketingDept->id],
            'visibility' => 'department_private',
        ];

        $response = $this->actingAs($eventsLead, 'sanctum')
            ->postJson('/api/v1/pm/projects', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('pm_projects', ['name' => 'Legacy Parameter Project', 'department_id' => $this->eventsDept->id]);
    }

    // =========================================================================
    // 3. Option B Authorization: Access Matrix Tests
    // =========================================================================

    public function test_option_b_owning_department_member_can_view_project(): void
    {
        $eventsLead = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->eventsDept, $eventsLead, DepartmentRole::Lead);

        $eventsMember = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->eventsDept, $eventsMember, DepartmentRole::Member);

        $project = PmProject::create([
            'name' => 'Events Private Project',
            'department_id' => $this->eventsDept->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'owner_id' => $eventsLead->id,
            'created_by' => $eventsLead->id,
        ]);

        $this->assertTrue($this->accessService->canViewProject($eventsMember, $project));

        $this->actingAs($eventsMember, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$project->uuid}")
            ->assertOk();
    }

    public function test_option_b_participating_department_member_can_view_project(): void
    {
        $eventsLead = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->eventsDept, $eventsLead, DepartmentRole::Lead);

        $marketingMember = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->marketingDept, $marketingMember, DepartmentRole::Member);

        $project = PmProject::create([
            'name' => 'Events Project with Marketing',
            'department_id' => $this->eventsDept->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'owner_id' => $eventsLead->id,
            'created_by' => $eventsLead->id,
        ]);

        $project->participatingDepartments()->attach($this->marketingDept->id);

        $this->assertTrue($this->accessService->canViewProject($marketingMember, $project));

        $this->actingAs($marketingMember, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$project->uuid}")
            ->assertOk();
    }

    public function test_option_b_unrelated_department_member_is_denied(): void
    {
        $eventsLead = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->eventsDept, $eventsLead, DepartmentRole::Lead);

        $financeMember = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->financeDept, $financeMember, DepartmentRole::Member);

        $project = PmProject::create([
            'name' => 'Events and Marketing Project',
            'department_id' => $this->eventsDept->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'owner_id' => $eventsLead->id,
            'created_by' => $eventsLead->id,
        ]);

        $project->participatingDepartments()->attach($this->marketingDept->id);

        $this->assertFalse($this->accessService->canViewProject($financeMember, $project));

        $this->actingAs($financeMember, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$project->uuid}")
            ->assertForbidden();
    }

    public function test_option_b_inactive_or_expired_memberships_are_denied(): void
    {
        $eventsLead = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->eventsDept, $eventsLead, DepartmentRole::Lead);

        // Inactive participating member
        $inactiveMarketingMember = $this->createUserWithPmsAccess();
        $mem = PmDepartmentMembership::create([
            'department_id' => $this->marketingDept->id,
            'user_id' => $inactiveMarketingMember->id,
            'department_role' => 'member',
            'status' => 'inactive',
            'start_date' => now()->subMonths(2)->toDateString(),
        ]);

        // Future start date
        $futureMember = $this->createUserWithPmsAccess();
        PmDepartmentMembership::create([
            'department_id' => $this->marketingDept->id,
            'user_id' => $futureMember->id,
            'department_role' => 'member',
            'status' => 'active',
            'start_date' => now()->addMonth()->toDateString(),
        ]);

        // Expired end date
        $expiredMember = $this->createUserWithPmsAccess();
        PmDepartmentMembership::create([
            'department_id' => $this->marketingDept->id,
            'user_id' => $expiredMember->id,
            'department_role' => 'member',
            'status' => 'active',
            'start_date' => now()->subMonths(6)->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
        ]);

        $project = PmProject::create([
            'name' => 'Events and Marketing Project',
            'department_id' => $this->eventsDept->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'owner_id' => $eventsLead->id,
            'created_by' => $eventsLead->id,
        ]);
        $project->participatingDepartments()->attach($this->marketingDept->id);

        $this->assertFalse($this->accessService->canViewProject($inactiveMarketingMember, $project));
        $this->assertFalse($this->accessService->canViewProject($futureMember, $project));
        $this->assertFalse($this->accessService->canViewProject($expiredMember, $project));
    }

    public function test_option_b_zero_executive_bypass_president_and_executive_denied_without_qualifying_membership(): void
    {
        $president = $this->createUserWithPmsAccess('president');
        $presidentRole = \App\Models\Role::firstOrCreate(['slug' => 'president'], ['name' => 'President', 'uuid' => (string) Str::uuid()]);
        $president->roles()->syncWithoutDetaching([$presidentRole->id]);

        $executive = $this->createUserWithPmsAccess('executive');
        $execRole = \App\Models\Role::firstOrCreate(['slug' => 'executive'], ['name' => 'Executive', 'uuid' => (string) Str::uuid()]);
        $executive->roles()->syncWithoutDetaching([$execRole->id]);

        $eventsLead = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->eventsDept, $eventsLead, DepartmentRole::Lead);

        $project = PmProject::create([
            'name' => 'Secret Events Project',
            'department_id' => $this->eventsDept->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'owner_id' => $eventsLead->id,
            'created_by' => $eventsLead->id,
        ]);
        $project->participatingDepartments()->attach($this->marketingDept->id);

        // Neither President nor Executive belongs to Events or Marketing
        $this->assertFalse($this->accessService->canViewProject($president, $project));
        $this->assertFalse($this->accessService->canViewProject($executive, $project));

        $this->actingAs($president, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$project->uuid}")
            ->assertForbidden();

        $this->actingAs($executive, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$project->uuid}")
            ->assertForbidden();
    }

    // =========================================================================
    // 4. Read-Only Default for Participating Department Members
    // =========================================================================

    public function test_participating_department_member_is_read_only_viewer_by_default(): void
    {
        $eventsLead = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->eventsDept, $eventsLead, DepartmentRole::Lead);

        $marketingMember = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->marketingDept, $marketingMember, DepartmentRole::Member);

        $project = PmProject::create([
            'name' => 'Events-Owned Project with Marketing Support',
            'department_id' => $this->eventsDept->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'owner_id' => $eventsLead->id,
            'created_by' => $eventsLead->id,
        ]);
        $project->participatingDepartments()->attach($this->marketingDept->id);

        // 1. Can View
        $this->assertTrue($this->accessService->canViewProject($marketingMember, $project));

        // 2. Cannot Create Tasks
        $this->assertFalse($this->accessService->canCreateTask($marketingMember, $project));
        $this->actingAs($marketingMember, 'sanctum')
            ->postJson("/api/v1/pm/projects/{$project->uuid}/tasks", [
                'title' => 'Unauthorized Task',
            ])->assertForbidden();

        // 3. Cannot Add Comments
        $this->assertFalse($this->accessService->canAddComment($marketingMember, $project));
        $this->actingAs($marketingMember, 'sanctum')
            ->postJson("/api/v1/pm/projects/{$project->uuid}/comments", [
                'body' => 'Unauthorized comment',
            ])->assertForbidden();

        // 4. Cannot Update Project
        $this->assertFalse($this->accessService->canUpdateProject($marketingMember, $project));
        $this->actingAs($marketingMember, 'sanctum')
            ->putJson("/api/v1/pm/projects/{$project->uuid}", [
                'name' => 'Renamed Unauthorized',
            ])->assertForbidden();

        // 5. Cannot Delete Project
        $this->assertFalse($this->accessService->canDeleteProject($marketingMember, $project));
        $this->actingAs($marketingMember, 'sanctum')
            ->deleteJson("/api/v1/pm/projects/{$project->uuid}")
            ->assertForbidden();

        // 6. Cannot Manage Members
        $this->assertFalse($this->accessService->canManageMembers($marketingMember, $project));
        $this->actingAs($marketingMember, 'sanctum')
            ->postJson("/api/v1/pm/projects/{$project->uuid}/members/invite", [
                'user_id' => 999,
                'project_role' => 'contributor',
            ])->assertForbidden();
    }

    public function test_participating_department_member_with_explicit_contributor_role_can_create_tasks(): void
    {
        $eventsLead = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->eventsDept, $eventsLead, DepartmentRole::Lead);

        $marketingMember = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->marketingDept, $marketingMember, DepartmentRole::Member);

        $project = PmProject::create([
            'name' => 'Events-Owned Project with Appointed Contributor',
            'department_id' => $this->eventsDept->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'owner_id' => $eventsLead->id,
            'created_by' => $eventsLead->id,
        ]);
        $project->participatingDepartments()->attach($this->marketingDept->id);

        // Appoint as explicit Contributor
        PmProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $marketingMember->id,
            'project_role' => ProjectRole::Contributor,
            'invitation_status' => InvitationStatus::Accepted,
            'accepted_at' => now(),
        ]);

        $this->assertTrue($this->accessService->canCreateTask($marketingMember, $project));
        $this->assertTrue($this->accessService->canAddComment($marketingMember, $project));
        $this->assertFalse($this->accessService->canUpdateProject($marketingMember, $project)); // Contributor still cannot update settings

        $this->actingAs($marketingMember, 'sanctum')
            ->postJson("/api/v1/pm/projects/{$project->uuid}/tasks", [
                'title' => 'Authorized Contributor Task',
                'priority' => 'medium',
            ])->assertCreated();
    }

    // =========================================================================
    // 5. Updating Participating Departments & Revocation Tests
    // =========================================================================

    public function test_updating_participating_departments_and_access_revocation(): void
    {
        $eventsLead = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->eventsDept, $eventsLead, DepartmentRole::Lead);

        $marketingMember = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->marketingDept, $marketingMember, DepartmentRole::Member);

        $graphicsMember = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->graphicsDept, $graphicsMember, DepartmentRole::Member);

        $project = PmProject::create([
            'name' => 'Department Dynamic Project',
            'department_id' => $this->eventsDept->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'owner_id' => $eventsLead->id,
            'created_by' => $eventsLead->id,
        ]);

        // Add Marketing
        $project->participatingDepartments()->attach($this->marketingDept->id);

        $this->assertTrue($this->accessService->canViewProject($marketingMember, $project));
        $this->assertFalse($this->accessService->canViewProject($graphicsMember, $project));

        // Update participating departments: remove Marketing, add Graphics
        $response = $this->actingAs($eventsLead, 'sanctum')
            ->putJson("/api/v1/pm/projects/{$project->uuid}", [
                'participating_department_ids' => [$this->graphicsDept->id],
            ]);

        $response->assertOk();

        // Refresh project
        $project->refresh();
        $project->load('participatingDepartments');

        // Marketing member has LOST access
        $this->assertFalse($this->accessService->canViewProject($marketingMember, $project));
        $this->actingAs($marketingMember, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$project->uuid}")
            ->assertForbidden();

        // Graphics member has GAINED access
        $this->assertTrue($this->accessService->canViewProject($graphicsMember, $project));
        $this->actingAs($graphicsMember, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$project->uuid}")
            ->assertOk();

        // Activity log audit checks
        $this->assertDatabaseHas('pm_activity_logs', [
            'project_id' => $project->id,
            'action' => 'participating_department_removed',
        ]);
        $this->assertDatabaseHas('pm_activity_logs', [
            'project_id' => $project->id,
            'action' => 'participating_department_added',
        ]);
    }

    public function test_user_retains_access_via_multiple_department_memberships(): void
    {
        $eventsLead = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->eventsDept, $eventsLead, DepartmentRole::Lead);

        // User belongs to both Marketing and IT
        $multiDeptUser = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->marketingDept, $multiDeptUser, DepartmentRole::Member);
        $this->deptService->addMember($this->itDept, $multiDeptUser, DepartmentRole::Member);

        $project = PmProject::create([
            'name' => 'Multi-Membership Project',
            'department_id' => $this->eventsDept->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'owner_id' => $eventsLead->id,
            'created_by' => $eventsLead->id,
        ]);
        $project->participatingDepartments()->attach([$this->marketingDept->id, $this->itDept->id]);

        $this->assertTrue($this->accessService->canViewProject($multiDeptUser, $project));

        // Remove Marketing from project — user STILL has access via IT
        $project->participatingDepartments()->detach($this->marketingDept->id);
        $project->refresh();

        $this->assertTrue($this->accessService->canViewProject($multiDeptUser, $project));

        // Remove IT from project — user NOW loses access
        $project->participatingDepartments()->detach($this->itDept->id);
        $project->refresh();

        $this->assertFalse($this->accessService->canViewProject($multiDeptUser, $project));
    }

    // =========================================================================
    // 6. Centralized Query Scope & Filtering Tests
    // =========================================================================

    public function test_projects_index_includes_participating_department_projects(): void
    {
        $eventsLead = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->eventsDept, $eventsLead, DepartmentRole::Lead);

        $marketingMember = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->marketingDept, $marketingMember, DepartmentRole::Member);

        $project1 = PmProject::create([
            'name' => 'Events with Marketing Participation',
            'department_id' => $this->eventsDept->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'owner_id' => $eventsLead->id,
            'created_by' => $eventsLead->id,
        ]);
        $project1->participatingDepartments()->attach($this->marketingDept->id);

        $project2 = PmProject::create([
            'name' => 'Events Strictly Private',
            'department_id' => $this->eventsDept->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'owner_id' => $eventsLead->id,
            'created_by' => $eventsLead->id,
        ]);

        $response = $this->actingAs($marketingMember, 'sanctum')
            ->getJson('/api/v1/pm/projects');

        $response->assertOk();
        $projectNames = collect($response->json('projects'))->pluck('name')->all();

        $this->assertContains('Events with Marketing Participation', $projectNames);
        $this->assertNotContains('Events Strictly Private', $projectNames);
    }

    public function test_dashboard_and_calendar_include_participating_department_projects(): void
    {
        $eventsLead = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->eventsDept, $eventsLead, DepartmentRole::Lead);

        $marketingMember = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->marketingDept, $marketingMember, DepartmentRole::Member);

        $project = PmProject::create([
            'name' => 'Events Calendar Project',
            'department_id' => $this->eventsDept->id,
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'due_date' => now()->addDays(5)->toDateString(),
            'owner_id' => $eventsLead->id,
            'created_by' => $eventsLead->id,
        ]);
        $project->participatingDepartments()->attach($this->marketingDept->id);

        // Dashboard check
        $dashRes = $this->actingAs($marketingMember, 'sanctum')
            ->getJson('/api/v1/pm/dashboard');
        $dashRes->assertOk();
        $this->assertEquals(1, $dashRes->json('data.kpi.total_projects'));

        // Calendar check
        $calRes = $this->actingAs($marketingMember, 'sanctum')
            ->getJson('/api/v1/pm/calendar/events');
        $calRes->assertOk();
        $this->assertNotEmpty($calRes->json('data'));
    }

    // =========================================================================
    // Workflow F — Shared MSA Regression with Participating Departments
    // =========================================================================

    public function test_workflow_f_shared_msa_regression_with_participating_departments(): void
    {
        $eventsLead = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->eventsDept, $eventsLead, DepartmentRole::Lead);

        $financeMember = $this->createUserWithPmsAccess();
        $this->deptService->addMember($this->financeDept, $financeMember, DepartmentRole::Member);

        // 1. Create a Shared MSA project with Events as owner and Marketing + IT as participants
        $project = PmProject::create([
            'name' => 'Campus Community Iftar (Shared MSA)',
            'department_id' => $this->eventsDept->id,
            'visibility' => ProjectVisibility::SharedMsa,
            'owner_id' => $eventsLead->id,
            'created_by' => $eventsLead->id,
        ]);
        $project->participatingDepartments()->attach([
            $this->marketingDept->id,
            $this->itDept->id,
        ]);

        // 2. Finance member (unrelated to Events, Marketing, or IT) can view because visibility is shared_msa
        $viewRes = $this->actingAs($financeMember, 'sanctum')
            ->getJson("/api/v1/pm/projects/{$project->uuid}");
        $viewRes->assertOk();
        $this->assertEquals('shared_msa', $viewRes->json('project.visibility'));
        $this->assertCount(2, $viewRes->json('project.participating_departments'));

        // 3. Finance member has read-only access by default (cannot create task)
        $taskRes = $this->actingAs($financeMember, 'sanctum')
            ->postJson("/api/v1/pm/projects/{$project->uuid}/tasks", [
                'title' => 'Unauthorized Finance Task',
            ]);
        $taskRes->assertForbidden();

        // 4. When assigned explicit Contributor role on the project, Finance member can create tasks
        PmProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $financeMember->id,
            'project_role' => ProjectRole::Contributor,
            'invitation_status' => InvitationStatus::Accepted,
            'accepted_at' => now(),
        ]);

        $taskRes2 = $this->actingAs($financeMember, 'sanctum')
            ->postJson("/api/v1/pm/projects/{$project->uuid}/tasks", [
                'title' => 'Authorized Finance Contributor Task',
            ]);
        $taskRes2->assertCreated();

        // 5. Existing Shared MSA visibility remains intact and verified
        $listRes = $this->actingAs($financeMember, 'sanctum')
            ->getJson('/api/v1/pm/projects');
        $listRes->assertOk();
        $names = collect($listRes->json('projects'))->pluck('name')->all();
        $this->assertContains('Campus Community Iftar (Shared MSA)', $names);
    }
}
