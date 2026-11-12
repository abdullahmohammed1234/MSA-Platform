<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Pm\Enums\DepartmentRole;
use App\Pm\Enums\InvitationStatus;
use App\Pm\Enums\ProjectPriority;
use App\Pm\Enums\ProjectRole;
use App\Pm\Enums\ProjectStatus;
use App\Pm\Enums\ProjectVisibility;
use App\Pm\Models\PmMilestone;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmProjectMember;
use App\Pm\Models\PmTask;
use App\Pm\Services\DepartmentMembershipService;
use App\Pm\Services\ProjectAccessService;
use App\Pm\Support\PmPermissions;
use App\Pm\Support\PmRoles;
use App\Services\ApplicationAccessService;
use Database\Seeders\PmDepartmentSeeder;
use Database\Seeders\PmRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class PmAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected ApplicationAccessService $appAccessService;
    protected DepartmentMembershipService $deptService;
    protected ProjectAccessService $projectAccessService;

    protected Department $itDept;
    protected Department $marketingDept;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PmDepartmentSeeder::class);
        $this->seed(PmRolePermissionSeeder::class);

        $this->appAccessService = app(ApplicationAccessService::class);
        $this->deptService = app(DepartmentMembershipService::class);
        $this->projectAccessService = app(ProjectAccessService::class);

        $this->itDept = Department::where('slug', 'it')->firstOrFail();
        $this->marketingDept = Department::where('slug', 'marketing')->firstOrFail();
    }

    private function createUserWithRole(string $roleSlug): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $roleSlug],
            ['uuid' => (string) Str::uuid(), 'name' => ucfirst($roleSlug)]
        );

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function createProject(array $attributes = []): PmProject
    {
        return PmProject::create(array_merge([
            'department_id' => $this->itDept->id,
            'name' => 'Internal Cloud Infrastructure',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'status' => ProjectStatus::InProgress,
            'priority' => ProjectPriority::Medium,
            'owner_id' => User::factory()->create()->id,
        ], $attributes));
    }

    /** @test */
    public function public_self_registered_volunteer_is_denied_pms_access()
    {
        $volunteer = $this->createUserWithRole('volunteer');

        $this->assertFalse(
            $this->appAccessService->canAccess($volunteer, 'projects'),
            'Self-registered volunteer must be denied PMS access by default.'
        );
        $this->assertFalse(
            $this->projectAccessService->canAccessProjects($volunteer)
        );
    }

    /** @test */
    public function standard_community_member_is_denied_pms_access()
    {
        $member = $this->createUserWithRole('member');

        $this->assertFalse(
            $this->appAccessService->canAccess($member, 'projects'),
            'Standard community member must be denied PMS access by default.'
        );
    }

    /** @test */
    public function department_member_without_explicit_grant_is_denied_pms_access()
    {
        $user = $this->createUserWithRole('member');
        $this->deptService->addMember($this->itDept, $user, DepartmentRole::Member);

        $this->assertFalse(
            $this->appAccessService->canAccess($user, 'projects'),
            'Department membership alone must NOT grant PMS access.'
        );
        $this->assertFalse(
            $this->projectAccessService->canAccessProjects($user)
        );
    }

    /** @test */
    public function formally_appointed_team_members_without_explicit_grant_are_denied_pms_access()
    {
        $roles = [
            PmRoles::PRESIDENT,
            PmRoles::EXECUTIVE,
            PmRoles::DEPARTMENT_LEAD,
            PmRoles::COORDINATOR,
        ];

        foreach ($roles as $role) {
            $user = $this->createUserWithRole($role);
            $this->assertFalse(
                $this->appAccessService->canAccess($user, 'projects'),
                "Formally appointed [{$role}] without explicit grant must be denied PMS access."
            );
            $this->assertFalse(
                $this->projectAccessService->canAccessProjects($user)
            );
        }
    }

    /** @test */
    public function explicitly_authorized_team_member_can_access_pms_and_revocation_denies_subsequent_access()
    {
        $user = $this->createUserWithRole('member');

        // Explicit grant
        $this->appAccessService->grant($user, 'projects');

        $this->assertTrue(
            $this->appAccessService->canAccess($user, 'projects'),
            'User with explicit application_access record must be granted PMS access.'
        );
        $this->assertTrue(
            $this->appAccessService->canAccess($user, 'pms'),
            'pms alias must also resolve to granted.'
        );
        $this->assertTrue(
            $this->projectAccessService->canAccessProjects($user)
        );

        // Revocation
        $this->appAccessService->revoke($user, 'projects');

        $this->assertFalse(
            $this->appAccessService->canAccess($user, 'projects'),
            'Revoking application access must immediately deny PMS access.'
        );
        $this->assertFalse(
            $this->projectAccessService->canAccessProjects($user)
        );
    }

    /** @test */
    public function department_member_can_view_own_department_private_project()
    {
        $itMember = $this->createUserWithRole('member');
        $this->deptService->addMember($this->itDept, $itMember, DepartmentRole::Member);
        $this->appAccessService->grant($itMember, 'projects');

        $project = $this->createProject([
            'department_id' => $this->itDept->id,
            'name' => 'Internal Cloud Infrastructure',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'owner_id' => $itMember->id,
        ]);

        $this->assertTrue($this->projectAccessService->canViewProject($itMember, $project));
        $this->assertTrue(Gate::forUser($itMember)->allows('view', $project));
    }

    /** @test */
    public function department_member_cannot_view_another_department_private_project()
    {
        $itMember = $this->createUserWithRole('member');
        $this->deptService->addMember($this->itDept, $itMember, DepartmentRole::Member);
        $this->appAccessService->grant($itMember, 'projects');

        $marketingProject = $this->createProject([
            'department_id' => $this->marketingDept->id,
            'name' => 'Secret Marketing Campaign',
            'visibility' => ProjectVisibility::DepartmentPrivate,
        ]);

        $this->assertFalse(
            $this->projectAccessService->canViewProject($itMember, $marketingProject),
            'Department A member must NOT view Department B private project.'
        );
        $this->assertTrue(Gate::forUser($itMember)->denies('view', $marketingProject));
    }

    /** @test */
    public function super_admin_cannot_view_department_private_project_without_membership_or_invitation()
    {
        $superAdmin = $this->createUserWithRole('super-admin');

        $privateProject = $this->createProject([
            'department_id' => $this->marketingDept->id,
            'name' => 'Private Finance and Marketing Audit',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'priority' => ProjectPriority::Urgent,
        ]);

        $this->assertFalse(
            $this->projectAccessService->canViewProject($superAdmin, $privateProject),
            'Super Admin MUST NOT view department-private project without department membership or accepted invitation.'
        );
        $this->assertTrue(
            Gate::forUser($superAdmin)->denies('view', $privateProject),
            'Policy Gate must deny super-admin on department-private project.'
        );
    }

    /** @test */
    public function president_and_executive_cannot_view_department_private_project_without_membership_or_invitation()
    {
        $president = $this->createUserWithRole(PmRoles::PRESIDENT);
        $executive = $this->createUserWithRole(PmRoles::EXECUTIVE);
        $this->appAccessService->grant($president, 'projects');
        $this->appAccessService->grant($executive, 'projects');

        $privateProject = $this->createProject([
            'department_id' => $this->itDept->id,
            'name' => 'IT Internal Infrastructure Restructure',
            'visibility' => ProjectVisibility::DepartmentPrivate,
        ]);

        $this->assertFalse(
            $this->projectAccessService->canViewProject($president, $privateProject),
            'President must NOT bypass department-private boundary.'
        );
        $this->assertTrue(Gate::forUser($president)->denies('view', $privateProject));

        $this->assertFalse(
            $this->projectAccessService->canViewProject($executive, $privateProject),
            'Executive must NOT bypass department-private boundary.'
        );
        $this->assertTrue(Gate::forUser($executive)->denies('view', $privateProject));
    }

    /** @test */
    public function cross_department_pending_invitation_does_not_grant_view_access_until_accepted()
    {
        $itMember = $this->createUserWithRole('member');
        $this->deptService->addMember($this->itDept, $itMember, DepartmentRole::Member);
        $this->appAccessService->grant($itMember, 'projects');

        $marketingProject = $this->createProject([
            'department_id' => $this->marketingDept->id,
            'name' => 'Social Media Automation',
            'visibility' => ProjectVisibility::DepartmentPrivate,
        ]);

        // Pending invitation
        $memberRecord = PmProjectMember::create([
            'project_id' => $marketingProject->id,
            'user_id' => $itMember->id,
            'project_role' => ProjectRole::Contributor,
            'invitation_status' => InvitationStatus::Pending,
        ]);

        $this->assertFalse(
            $this->projectAccessService->canViewProject($itMember, $marketingProject),
            'Pending invitation must NOT grant project viewing.'
        );
        $this->assertTrue(Gate::forUser($itMember)->denies('view', $marketingProject));

        // Accept invitation
        $memberRecord->update(['invitation_status' => InvitationStatus::Accepted]);

        $this->assertTrue(
            $this->projectAccessService->canViewProject($itMember->fresh(), $marketingProject->fresh()),
            'Accepted invitation MUST grant project viewing.'
        );
        $this->assertTrue(Gate::forUser($itMember->fresh())->allows('view', $marketingProject->fresh()));
    }

    /** @test */
    public function any_authorized_pms_user_can_view_shared_msa_project()
    {
        $itMember = $this->createUserWithRole('member');
        $this->deptService->addMember($this->itDept, $itMember, DepartmentRole::Member);
        $this->appAccessService->grant($itMember, 'projects');

        $sharedProject = $this->createProject([
            'department_id' => $this->marketingDept->id,
            'name' => 'Annual MSA Gala & Orientation',
            'visibility' => ProjectVisibility::SharedMsa,
        ]);

        $this->assertTrue(
            $this->projectAccessService->canViewProject($itMember, $sharedProject),
            'Any authorized PMS user must be able to view shared_msa projects.'
        );
        $this->assertTrue(Gate::forUser($itMember)->allows('view', $sharedProject));

        // Self-registered volunteer STILL cannot view because they lack PMS app access
        $volunteer = $this->createUserWithRole('volunteer');
        $this->assertFalse(
            $this->projectAccessService->canViewProject($volunteer, $sharedProject),
            'Volunteer without PMS access cannot view even shared_msa projects.'
        );
    }

    /** @test */
    public function task_and_milestone_policy_strictly_inherits_project_privacy()
    {
        $itMember = $this->createUserWithRole('member');
        $this->deptService->addMember($this->itDept, $itMember, DepartmentRole::Member);
        $this->appAccessService->grant($itMember, 'projects');

        $externalMember = $this->createUserWithRole('member');
        $this->deptService->addMember($this->marketingDept, $externalMember, DepartmentRole::Member);
        $this->appAccessService->grant($externalMember, 'projects');

        $project = $this->createProject([
            'department_id' => $this->itDept->id,
            'name' => 'Internal Core Services',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'owner_id' => $itMember->id,
        ]);

        $milestone = PmMilestone::create([
            'project_id' => $project->id,
            'title' => 'Phase 1 Core Infrastructure',
            'created_by' => $itMember->id,
            'due_date' => now()->addMonth()->toDateString(),
        ]);

        $task = PmTask::create([
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
            'title' => 'Provision Redis Cache Cluster',
            'created_by' => $itMember->id,
            'due_date' => now()->addWeeks(2)->toDateString(),
        ]);

        // IT member can view milestone and task
        $this->assertTrue(Gate::forUser($itMember)->allows('view', $milestone));
        $this->assertTrue(Gate::forUser($itMember)->allows('view', $task));

        // External marketing member CANNOT view milestone or task
        $this->assertTrue(Gate::forUser($externalMember)->denies('view', $milestone));
        $this->assertTrue(Gate::forUser($externalMember)->denies('view', $task));
    }

    /** @test */
    public function project_modification_is_restricted_to_lead_and_authorized_roles()
    {
        $itLead = $this->createUserWithRole('member');
        $this->deptService->addMember($this->itDept, $itLead, DepartmentRole::Lead);
        $this->appAccessService->grant($itLead, 'projects');

        $itRegularMember = $this->createUserWithRole('member');
        $this->deptService->addMember($this->itDept, $itRegularMember, DepartmentRole::Member);
        $this->appAccessService->grant($itRegularMember, 'projects');

        $project = $this->createProject([
            'department_id' => $this->itDept->id,
            'name' => 'Database Performance Tuning',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'owner_id' => $itLead->id,
        ]);

        // Lead can update and delete
        $this->assertTrue(Gate::forUser($itLead)->allows('update', $project));
        $this->assertTrue(Gate::forUser($itLead)->allows('delete', $project));
        $this->assertTrue(Gate::forUser($itLead)->allows('manageMembers', $project));

        // Regular department member can view and create tasks, but CANNOT update project settings or delete
        $this->assertTrue(Gate::forUser($itRegularMember)->allows('view', $project));
        $this->assertTrue(Gate::forUser($itRegularMember)->allows('createTasks', $project));
        $this->assertTrue(Gate::forUser($itRegularMember)->denies('update', $project));
        $this->assertTrue(Gate::forUser($itRegularMember)->denies('delete', $project));
    }

    /** @test */
    public function jumuah_department_behaves_identically_with_complete_privacy_boundaries()
    {
        $jumuahDept = Department::where('slug', 'jumuah')->firstOrFail();

        $jumuahMember = $this->createUserWithRole('member');
        $this->deptService->addMember($jumuahDept, $jumuahMember, DepartmentRole::Member);
        $this->appAccessService->grant($jumuahMember, 'projects');

        $itMember = $this->createUserWithRole('member');
        $this->deptService->addMember($this->itDept, $itMember, DepartmentRole::Member);
        $this->appAccessService->grant($itMember, 'projects');

        $jumuahProject = $this->createProject([
            'department_id' => $jumuahDept->id,
            'name' => 'Burnaby Campus Jumuah Logistics',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'owner_id' => $jumuahMember->id,
        ]);

        // Jumuah member can view
        $this->assertTrue($this->projectAccessService->canViewProject($jumuahMember, $jumuahProject));
        $this->assertTrue(Gate::forUser($jumuahMember)->allows('view', $jumuahProject));

        // IT member CANNOT view Jumuah private project
        $this->assertFalse($this->projectAccessService->canViewProject($itMember, $jumuahProject));
        $this->assertTrue(Gate::forUser($itMember)->denies('view', $jumuahProject));

        // Jumuah member CANNOT view IT private project
        $itProject = $this->createProject([
            'department_id' => $this->itDept->id,
            'name' => 'IT Infrastructure Security',
            'visibility' => ProjectVisibility::DepartmentPrivate,
        ]);
        $this->assertFalse($this->projectAccessService->canViewProject($jumuahMember, $itProject));
        $this->assertTrue(Gate::forUser($jumuahMember)->denies('view', $itProject));

        // Super-admin, President, and Executive cannot view Jumuah private project without invitation
        $superAdmin = $this->createUserWithRole('super-admin');
        $president = $this->createUserWithRole(PmRoles::PRESIDENT);
        $this->appAccessService->grant($president, 'projects');

        $this->assertFalse($this->projectAccessService->canViewProject($superAdmin, $jumuahProject));
        $this->assertTrue(Gate::forUser($superAdmin)->denies('view', $jumuahProject));
        $this->assertFalse($this->projectAccessService->canViewProject($president, $jumuahProject));
        $this->assertTrue(Gate::forUser($president)->denies('view', $jumuahProject));
    }

    /** @test */
    public function cross_project_idor_attempts_are_blocked()
    {
        $itMember = $this->createUserWithRole('member');
        $this->deptService->addMember($this->itDept, $itMember, DepartmentRole::Member);
        $this->appAccessService->grant($itMember, 'projects');

        $marketingMember = $this->createUserWithRole('member');
        $this->deptService->addMember($this->marketingDept, $marketingMember, DepartmentRole::Member);
        $this->appAccessService->grant($marketingMember, 'projects');

        $itProject = $this->createProject([
            'department_id' => $this->itDept->id,
            'name' => 'IT Secure Service',
            'visibility' => ProjectVisibility::DepartmentPrivate,
            'owner_id' => $itMember->id,
        ]);

        $task = PmTask::create([
            'project_id' => $itProject->id,
            'title' => 'Internal Secure Task',
            'created_by' => $itMember->id,
        ]);

        $milestone = PmMilestone::create([
            'project_id' => $itProject->id,
            'title' => 'Internal Milestone',
            'created_by' => $itMember->id,
            'due_date' => now()->addWeek()->toDateString(),
        ]);

        // Marketing member cannot update or delete IT project task
        $this->assertTrue(Gate::forUser($marketingMember)->denies('update', $task));
        $this->assertTrue(Gate::forUser($marketingMember)->denies('delete', $task));

        // Marketing member cannot update or delete IT project milestone
        $this->assertTrue(Gate::forUser($marketingMember)->denies('update', $milestone));
        $this->assertTrue(Gate::forUser($marketingMember)->denies('delete', $milestone));
    }
}
