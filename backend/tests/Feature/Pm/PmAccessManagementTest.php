<?php

namespace Tests\Feature\Pm;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Pm\Enums\DepartmentRole;
use App\Pm\Models\PmAccessLog;
use App\Pm\Support\PmRoles;
use Database\Seeders\PmDepartmentSeeder;
use Database\Seeders\PmRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PmAccessManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;
    protected Department $itDept;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PmDepartmentSeeder::class);
        $this->seed(PmRolePermissionSeeder::class);

        $adminRole = Role::firstOrCreate(['slug' => 'super-admin'], ['uuid' => (string) Str::uuid(), 'name' => 'Super Admin']);
        $memberRole = Role::firstOrCreate(['slug' => 'member'], ['uuid' => (string) Str::uuid(), 'name' => 'Member']);

        $this->adminUser = User::factory()->create();
        $this->adminUser->roles()->sync([$adminRole->id]);

        $this->regularUser = User::factory()->create();
        $this->regularUser->roles()->sync([$memberRole->id]);

        $this->itDept = Department::where('slug', 'it')->firstOrFail();
    }

    /** @test */
    public function unauthorized_users_are_forbidden_from_pms_access_management()
    {
        // Unauthenticated
        $this->getJson('/api/v1/admin/pms/access/users')
            ->assertStatus(401);

        // Regular authenticated member
        $this->actingAs($this->regularUser, 'sanctum')
            ->getJson('/api/v1/admin/pms/access/users')
            ->assertStatus(403);
    }

    /** @test */
    public function admin_can_list_users_with_pms_access_details()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/pms/access/users')
            ->assertOk()
            ->assertJsonStructure([
                'users' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'has_pms_access',
                        'access_source',
                        'roles',
                        'department_memberships',
                    ],
                ],
                'meta' => ['current_page', 'last_page', 'total'],
            ]);

        $this->assertNotEmpty($response->json('users'));
    }

    /** @test */
    public function admin_can_grant_and_revoke_pms_application_access()
    {
        // Grant access
        $grantResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/pms/access/grant', [
                'user_id' => $this->regularUser->id,
                'reason' => 'Appointed as IT Coordinator',
            ])
            ->assertOk()
            ->assertJson([
                'has_pms_access' => true,
            ]);

        $this->assertDatabaseHas('application_access', [
            'user_id' => $this->regularUser->id,
            'application' => 'projects',
        ]);

        $this->assertDatabaseHas('pm_access_logs', [
            'target_user_id' => $this->regularUser->id,
            'actor_user_id' => $this->adminUser->id,
            'action' => 'grant_pms_access',
        ]);

        // Revoke access
        $revokeResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/pms/access/revoke', [
                'user_id' => $this->regularUser->id,
                'reason' => 'Stepped down from role',
            ])
            ->assertOk()
            ->assertJson([
                'has_pms_access' => false,
            ]);

        $this->assertDatabaseMissing('application_access', [
            'user_id' => $this->regularUser->id,
            'application' => 'projects',
        ]);

        $this->assertDatabaseHas('pm_access_logs', [
            'target_user_id' => $this->regularUser->id,
            'actor_user_id' => $this->adminUser->id,
            'action' => 'revoke_pms_access',
        ]);
    }

    /** @test */
    public function admin_can_assign_and_detach_pms_roles()
    {
        // Attach department-lead role
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/pms/access/assign-role', [
                'user_id' => $this->regularUser->id,
                'role' => PmRoles::DEPARTMENT_LEAD,
                'action' => 'attach',
            ])
            ->assertOk();

        $this->assertTrue($this->regularUser->fresh()->hasRole(PmRoles::DEPARTMENT_LEAD));

        // Detach role
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/pms/access/assign-role', [
                'user_id' => $this->regularUser->id,
                'role' => PmRoles::DEPARTMENT_LEAD,
                'action' => 'detach',
            ])
            ->assertOk();

        $this->assertFalse($this->regularUser->fresh()->hasRole(PmRoles::DEPARTMENT_LEAD));
    }

    /** @test */
    public function admin_can_assign_and_remove_department_membership()
    {
        // Assign to department
        $assignResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/pms/access/departments/assign', [
                'user_id' => $this->regularUser->id,
                'department_id' => $this->itDept->id,
                'department_role' => DepartmentRole::Coordinator->value,
            ])
            ->assertOk()
            ->assertJsonStructure([
                'membership' => ['id', 'department_id', 'department_name', 'department_role'],
            ]);

        $this->assertDatabaseHas('pm_department_memberships', [
            'department_id' => $this->itDept->id,
            'user_id' => $this->regularUser->id,
            'department_role' => DepartmentRole::Coordinator->value,
            'status' => 'active',
        ]);

        // Remove from department
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/pms/access/departments/remove', [
                'user_id' => $this->regularUser->id,
                'department_id' => $this->itDept->id,
            ])
            ->assertOk();

        $this->assertDatabaseMissing('pm_department_memberships', [
            'department_id' => $this->itDept->id,
            'user_id' => $this->regularUser->id,
        ]);
    }

    /** @test */
    public function admin_can_view_department_roster_summary_and_audit_logs()
    {
        // Departments summary
        $deptResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/pms/access/departments')
            ->assertOk()
            ->assertJsonStructure([
                'departments' => [
                    '*' => ['id', 'name', 'slug', 'active_members_count'],
                ],
            ]);

        $this->assertCount(8, $deptResponse->json('departments'));
        $this->assertTrue(
            collect($deptResponse->json('departments'))->contains('slug', 'jumuah'),
            "Jumu'ah must be present in canonical departments list."
        );

        // Access logs
        PmAccessLog::create([
            'target_user_id' => $this->regularUser->id,
            'actor_user_id' => $this->adminUser->id,
            'action' => 'grant_pms_access',
            'notes' => 'Test log record',
        ]);

        $logsResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/pms/access/logs')
            ->assertOk()
            ->assertJsonStructure([
                'logs' => [
                    '*' => ['id', 'action', 'target_user', 'actor', 'notes'],
                ],
                'meta',
            ]);

        $this->assertNotEmpty($logsResponse->json('logs'));
    }

    /** @test */
    public function admin_can_suspend_and_restore_pms_application_access()
    {
        // First grant access
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/pms/access/grant', [
                'user_id' => $this->regularUser->id,
                'reason' => 'Granted for testing suspend',
            ])
            ->assertOk();

        $this->assertTrue(app(\App\Services\ApplicationAccessService::class)->canAccess($this->regularUser, 'projects'));

        // Suspend access
        $suspendResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/pms/access/suspend', [
                'user_id' => $this->regularUser->id,
                'reason' => 'Temporary suspension pending review',
            ])
            ->assertOk()
            ->assertJson([
                'has_pms_access' => false,
                'access_status' => 'suspended',
            ]);

        // Verify cannot access while suspended
        $this->assertFalse(app(\App\Services\ApplicationAccessService::class)->canAccess($this->regularUser, 'projects'));

        $this->assertDatabaseHas('pm_access_logs', [
            'target_user_id' => $this->regularUser->id,
            'actor_user_id' => $this->adminUser->id,
            'action' => 'suspend_pms_access',
        ]);

        // Restore access
        $restoreResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/pms/access/restore', [
                'user_id' => $this->regularUser->id,
                'reason' => 'Review completed, access restored',
            ])
            ->assertOk()
            ->assertJson([
                'has_pms_access' => true,
                'access_status' => 'active',
            ]);

        // Verify can access again
        $this->assertTrue(app(\App\Services\ApplicationAccessService::class)->canAccess($this->regularUser, 'projects'));

        $this->assertDatabaseHas('pm_access_logs', [
            'target_user_id' => $this->regularUser->id,
            'actor_user_id' => $this->adminUser->id,
            'action' => 'restore_pms_access',
        ]);
    }

    /** @test */
    public function admin_can_fetch_roles_reference_with_canonical_six_pms_roles_and_four_project_roles()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/pms/access/roles-reference')
            ->assertOk()
            ->assertJsonStructure([
                'canonical_pms_roles' => [
                    'super-admin',
                    'president',
                    'executive',
                    'department-lead',
                    'coordinator',
                    'team-member',
                ],
                'canonical_permissions',
                'project_level_roles' => [
                    'owner',
                    'manager',
                    'contributor',
                    'viewer',
                ],
            ]);

        $roles = $response->json('canonical_pms_roles');
        $this->assertArrayHasKey('super-admin', $roles);
        $this->assertArrayHasKey('president', $roles);
        $this->assertArrayHasKey('executive', $roles);
        $this->assertArrayHasKey('department-lead', $roles);
        $this->assertArrayHasKey('coordinator', $roles);
        $this->assertArrayHasKey('team-member', $roles);

        $projRoles = $response->json('project_level_roles');
        $this->assertArrayHasKey('owner', $projRoles);
        $this->assertArrayHasKey('manager', $projRoles);
        $this->assertArrayHasKey('contributor', $projRoles);
        $this->assertArrayHasKey('viewer', $projRoles);
    }

    /** @test */
    public function admin_can_view_role_assignment_history()
    {
        // Perform a role assignment
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/admin/pms/access/assign-role', [
                'user_id' => $this->regularUser->id,
                'role' => PmRoles::COORDINATOR,
                'action' => 'attach',
            ])
            ->assertOk();

        $historyResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/admin/pms/access/role-history')
            ->assertOk()
            ->assertJsonStructure([
                'history' => [
                    '*' => ['id', 'action', 'target_user', 'actor', 'created_at'],
                ],
                'meta',
            ]);

        $this->assertNotEmpty($historyResponse->json('history'));
        $firstItem = $historyResponse->json('history.0');
        $this->assertEquals('attach_pms_role', $firstItem['action']);
        $this->assertEquals($this->regularUser->id, $firstItem['target_user']['id']);
    }
}
