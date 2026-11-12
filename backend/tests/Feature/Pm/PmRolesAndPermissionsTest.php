<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Pm\Support\PmPermissions;
use App\Pm\Support\PmRoles;
use App\Services\ApplicationAccessService;
use Database\Seeders\PmDepartmentSeeder;
use Database\Seeders\PmRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PmRolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PmDepartmentSeeder::class);
        $this->seed(PmRolePermissionSeeder::class);
    }

    /** @test */
    public function canonical_six_roles_exist_with_proper_definitions()
    {
        $definitions = PmRoles::definitions();

        $this->assertArrayHasKey(PmRoles::SUPER_ADMIN, $definitions);
        $this->assertArrayHasKey(PmRoles::PRESIDENT, $definitions);
        $this->assertArrayHasKey(PmRoles::EXECUTIVE, $definitions);
        $this->assertArrayHasKey(PmRoles::DEPARTMENT_LEAD, $definitions);
        $this->assertArrayHasKey(PmRoles::COORDINATOR, $definitions);
        $this->assertArrayHasKey(PmRoles::TEAM_MEMBER, $definitions);

        foreach ($definitions as $slug => $def) {
            $this->assertDatabaseHas('roles', [
                'slug' => $slug,
                'name' => $def['name'],
            ]);
            $this->assertNotEmpty($def['permissions']);
        }
    }

    /** @test */
    public function granular_permissions_are_seeded_with_pms_module()
    {
        $permissions = PmPermissions::definitions();

        $this->assertArrayHasKey(PmPermissions::PROJECTS_VIEW, $permissions);
        $this->assertArrayHasKey(PmPermissions::PROJECTS_CREATE, $permissions);
        $this->assertArrayHasKey(PmPermissions::PROJECTS_UPDATE, $permissions);
        $this->assertArrayHasKey(PmPermissions::PROJECTS_DELETE, $permissions);
        $this->assertArrayHasKey(PmPermissions::PROJECTS_MANAGE, $permissions);

        $this->assertArrayHasKey(PmPermissions::DEPARTMENTS_VIEW, $permissions);
        $this->assertArrayHasKey(PmPermissions::DEPARTMENTS_MANAGE, $permissions);

        $this->assertArrayHasKey(PmPermissions::TASKS_VIEW, $permissions);
        $this->assertArrayHasKey(PmPermissions::TASKS_CREATE, $permissions);
        $this->assertArrayHasKey(PmPermissions::TASKS_UPDATE, $permissions);
        $this->assertArrayHasKey(PmPermissions::TASKS_DELETE, $permissions);
        $this->assertArrayHasKey(PmPermissions::TASKS_ASSIGN, $permissions);

        $this->assertArrayHasKey(PmPermissions::MEMBERS_VIEW, $permissions);
        $this->assertArrayHasKey(PmPermissions::MEMBERS_MANAGE, $permissions);

        $this->assertArrayHasKey(PmPermissions::INVITATIONS_CREATE, $permissions);
        $this->assertArrayHasKey(PmPermissions::INVITATIONS_MANAGE, $permissions);

        $this->assertArrayHasKey(PmPermissions::ROLES_VIEW, $permissions);
        $this->assertArrayHasKey(PmPermissions::ROLES_MANAGE, $permissions);
        $this->assertArrayHasKey(PmPermissions::PERMISSIONS_VIEW, $permissions);
        $this->assertArrayHasKey(PmPermissions::PERMISSIONS_MANAGE, $permissions);
        $this->assertArrayHasKey(PmPermissions::SETTINGS_MANAGE, $permissions);

        foreach (array_keys($permissions) as $slug) {
            $this->assertDatabaseHas('permissions', [
                'slug' => $slug,
                'module' => PmPermissions::MODULE,
            ]);
        }
    }

    /** @test */
    public function team_member_receives_only_authorized_permissions()
    {
        $role = Role::where('slug', PmRoles::TEAM_MEMBER)->firstOrFail();
        $heldPerms = $role->permissions->pluck('slug')->toArray();

        $this->assertContains(PmPermissions::ACCESS, $heldPerms);
        $this->assertContains(PmPermissions::PROJECTS_VIEW, $heldPerms);
        $this->assertContains(PmPermissions::TASKS_VIEW, $heldPerms);
        $this->assertContains(PmPermissions::TASKS_CREATE, $heldPerms);
        $this->assertContains(PmPermissions::TASKS_UPDATE, $heldPerms);

        // Disallowed privileges
        $this->assertNotContains(PmPermissions::PROJECTS_DELETE, $heldPerms);
        $this->assertNotContains(PmPermissions::DEPARTMENTS_MANAGE, $heldPerms);
        $this->assertNotContains(PmPermissions::ROLES_MANAGE, $heldPerms);
        $this->assertNotContains(PmPermissions::PERMISSIONS_MANAGE, $heldPerms);
    }

    /** @test */
    public function president_and_executive_do_not_automatically_bypass_department_private_projects()
    {
        $appAccess = app(ApplicationAccessService::class);

        $presidentRole = Role::where('slug', PmRoles::PRESIDENT)->firstOrFail();
        $president = User::factory()->create();
        $president->roles()->sync([$presidentRole->id]);
        $appAccess->grant($president, 'projects');

        $financeDept = Department::where('slug', 'finance')->firstOrFail();
        $deptLead = User::factory()->create();
        $appAccess->grant($deptLead, 'projects');

        // Create department-private project in Finance
        $project = \App\Pm\Models\PmProject::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Finance Private Q4 Budget',
            'slug' => 'finance-private-q4-budget',
            'department_id' => $financeDept->id,
            'visibility' => \App\Pm\Enums\ProjectVisibility::DepartmentPrivate,
            'status' => 'planning',
            'priority' => 'high',
            'owner_id' => $deptLead->id,
            'created_by' => $deptLead->id,
        ]);

        $accessService = app(\App\Pm\Services\ProjectAccessService::class);

        // President CANNOT view department-private project without membership
        $this->assertFalse($accessService->canViewProject($president, $project));

        // Shared project CAN be viewed
        $sharedProject = \App\Pm\Models\PmProject::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'MSA Annual Gala',
            'slug' => 'msa-annual-gala',
            'department_id' => $financeDept->id,
            'visibility' => \App\Pm\Enums\ProjectVisibility::SharedMsa,
            'status' => 'planning',
            'priority' => 'high',
            'owner_id' => $deptLead->id,
            'created_by' => $deptLead->id,
        ]);

        $this->assertTrue($accessService->canViewProject($president, $sharedProject));
    }
}
