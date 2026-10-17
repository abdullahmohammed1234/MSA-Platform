<?php

namespace Tests\Feature\Phase29;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ApplicationAccessService;
use App\Support\AuthorizationMemo;
use Database\Seeders\Ems\EmsRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The request-scoped authorization memo must never widen access: no sharing between users,
 * no survival past the request, and every RBAC write takes effect immediately.
 */
class AuthorizationMemoIsolationTest extends TestCase
{
    use RefreshDatabase;

    private AuthorizationMemo $memo;

    private Role $editor;

    private Permission $manageUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmsRolePermissionSeeder::class);
        foreach (['admin' => 'Admin', 'super-admin' => 'Super Admin', 'volunteer' => 'Volunteer'] as $slug => $name) {
            $this->role($slug, $name);
        }
        $this->manageUsers = Permission::firstOrCreate(['slug' => 'manage_users'], [
            'uuid' => (string) Str::uuid(), 'name' => 'manage_users', 'module' => 'Users', 'description' => 'manage_users',
        ]);
        Permission::firstOrCreate(['slug' => 'system.view'], [
            'uuid' => (string) Str::uuid(), 'name' => 'system.view', 'module' => 'System', 'description' => 'system.view',
        ]);
        $this->editor = $this->role('user-editor', 'User Editor');
        $this->editor->permissions()->attach($this->manageUsers->id);

        $this->memo = app(AuthorizationMemo::class);
    }

    private function role(string $slug, string $name): Role
    {
        return Role::firstOrCreate(['slug' => $slug], ['uuid' => (string) Str::uuid(), 'name' => $name, 'description' => $name]);
    }

    private function user(string ...$roles): User
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        foreach ($roles as $role) {
            $user->roles()->attach(Role::where('slug', $role)->firstOrFail()->id);
        }

        return $user;
    }

    /**
     * Runs $check with the memo active, as it is during an HTTP request.
     */
    private function inRequest(callable $check): mixed
    {
        $this->memo->begin();
        try {
            return $check();
        } finally {
            $this->memo->end();
        }
    }

    public function test_two_users_in_one_request_never_share_roles_permissions_or_grants(): void
    {
        $admin = $this->user('admin');
        $editor = $this->user('user-editor');
        $member = $this->user('volunteer');
        app(ApplicationAccessService::class)->grant($editor, 'cms');

        $this->inRequest(function () use ($admin, $editor, $member) {
            $access = app(ApplicationAccessService::class);
            foreach ([1, 2] as $ignored) {
                $this->assertTrue($admin->hasPermission('manage_users'));
                $this->assertTrue($editor->hasPermission('manage_users'));
                $this->assertFalse($member->hasPermission('manage_users'));

                $this->assertTrue($admin->hasRole('admin'));
                $this->assertFalse($editor->hasRole('admin'));
                $this->assertFalse($member->hasAnyRole(['admin', 'super-admin', 'user-editor']));

                $this->assertTrue($access->canAccess($editor, 'cms'));
                $this->assertFalse($access->canAccess($member, 'cms'));
                $this->assertFalse($access->canAccess($editor, 'ems'));
                $this->assertTrue($access->canAccess($admin, 'ems'));
            }
        });
    }

    public function test_two_instances_of_the_same_user_agree_and_unknown_users_have_nothing(): void
    {
        $editor = $this->user('user-editor');
        $unsaved = User::factory()->make();

        $this->inRequest(function () use ($editor, $unsaved) {
            $this->assertTrue($editor->hasPermission('manage_users'));
            $this->assertTrue(User::find($editor->id)->hasPermission('manage_users'));
            $this->assertFalse($unsaved->hasPermission('manage_users'));
            $this->assertFalse($unsaved->hasRole('user-editor'));
        });
    }

    /**
     * @return array<string, array{callable(User, Role, Permission): void}>
     */
    public static function revocations(): array
    {
        return [
            'role detached from user' => [fn (User $u, Role $r) => $u->roles()->detach($r->id)],
            'removeRole()' => [fn (User $u, Role $r) => $u->removeRole($r)],
            'syncRoles([])' => [fn (User $u) => $u->syncRoles([])],
            'permission detached from role' => [fn (User $u, Role $r, Permission $p) => $r->permissions()->detach($p->id)],
            'role deleted' => [function (User $u, Role $r) {
                DB::table('role_user')->where('role_id', $r->id)->delete();
                DB::table('permission_role')->where('role_id', $r->id)->delete();
                $r->delete();
            }],
            'raw pivot delete' => [fn (User $u, Role $r) => DB::table('role_user')->where('user_id', $u->id)->delete()],
        ];
    }

    #[DataProvider('revocations')]
    public function test_revocation_inside_a_request_takes_effect_immediately(callable $revoke): void
    {
        $user = $this->user('user-editor');

        $this->inRequest(function () use ($user, $revoke) {
            $this->assertTrue($user->hasPermission('manage_users'));
            $this->assertTrue($user->hasRole('user-editor'));

            $revoke($user, $this->editor, $this->manageUsers);

            $this->assertFalse($user->hasPermission('manage_users'));
        });
    }

    public function test_direct_permission_and_application_grants_follow_writes_inside_a_request(): void
    {
        $user = $this->user('volunteer');
        $access = app(ApplicationAccessService::class);

        $this->inRequest(function () use ($user, $access) {
            $this->assertFalse($user->hasPermission('manage_users'));
            $this->assertFalse($access->canAccess($user, 'cms'));

            $user->givePermission('manage_users');
            $access->grant($user, 'cms');
            $this->assertTrue($user->hasPermission('manage_users'));
            $this->assertTrue($access->canAccess($user, 'cms'));

            $user->revokePermission('manage_users');
            $access->revoke($user, 'cms');
            $this->assertFalse($user->hasPermission('manage_users'));
            $this->assertFalse($access->canAccess($user, 'cms'));

            $user->assignRole('admin');
            $this->assertTrue($user->hasPermission('manage_users'));
            $this->assertTrue($access->canAccess($user, 'cms'));
        });
    }

    public function test_memo_is_inactive_and_empty_outside_requests(): void
    {
        $user = $this->user('user-editor');
        $this->inRequest(fn () => $this->assertTrue($user->hasPermission('manage_users')));

        $this->assertFalse($this->memo->active());

        DB::table('permission_role')->delete();
        DB::enableQueryLog();
        $this->assertFalse($user->hasPermission('manage_users'));
        $this->assertNotEmpty(DB::getQueryLog(), 'Outside a request every check must query the database');
    }

    public function test_access_changes_between_http_requests_are_honoured_for_the_same_session(): void
    {
        $staff = $this->user('event-administrator');
        app(ApplicationAccessService::class)->grant($staff, 'ems');
        $token = $staff->createToken('p29')->plainTextToken;
        $call = function (string $uri) use ($token) {
            $this->app['auth']->forgetGuards();
            $this->flushHeaders();

            return $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'])->getJson($uri);
        };

        $call('/api/v1/ems/events')->assertOk();
        $call('/api/v1/admin/systems')->assertForbidden();

        app(ApplicationAccessService::class)->revoke($staff, 'ems');
        $call('/api/v1/ems/events')->assertForbidden();

        $staff->assignRole('admin');
        $call('/api/v1/admin/systems')->assertOk();
        $call('/api/v1/ems/events')->assertOk();

        $staff->removeRole('admin');
        $call('/api/v1/admin/systems')->assertForbidden();
        $call('/api/v1/ems/events')->assertForbidden();
    }

    public function test_acting_as_the_same_instance_across_requests_sees_revocation(): void
    {
        $editor = $this->user('user-editor');
        app(ApplicationAccessService::class)->grant($editor, 'admin-portal');

        $this->actingAs($editor, 'sanctum')->getJson('/api/v1/admin/users')->assertOk();

        $this->editor->permissions()->detach($this->manageUsers->id);
        $this->actingAs($editor, 'sanctum')->getJson('/api/v1/admin/users')->assertForbidden();
    }

    public function test_staff_and_member_boundaries_hold_with_the_memo(): void
    {
        $member = $this->user('volunteer');
        $staff = $this->user('event-administrator');
        app(ApplicationAccessService::class)->grant($staff, 'ems');
        $admin = $this->user('admin');

        $this->actingAs($member, 'sanctum')->getJson('/api/v1/ems/events')->assertForbidden();
        $this->actingAs($member, 'sanctum')->getJson('/api/v1/admin/systems')->assertForbidden();
        $this->actingAs($member, 'sanctum')->getJson('/api/v1/admin/users')->assertForbidden();
        $this->actingAs($staff, 'sanctum')->getJson('/api/v1/admin/systems')->assertForbidden();
        $this->actingAs($staff, 'sanctum')->getJson('/api/v1/admin/users')->assertForbidden();
        $this->actingAs($staff, 'sanctum')->getJson('/api/v1/ems/events')->assertOk();
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/systems')->assertOk();
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/users')->assertOk();
    }

    public function test_role_name_matching_stays_case_insensitive_like_the_production_collation(): void
    {
        $user = $this->user('user-editor');

        $this->inRequest(function () use ($user) {
            $this->assertTrue($user->hasRole('user-editor'));
            $this->assertTrue($user->hasRole('User Editor'));
            $this->assertTrue($user->hasRole('USER-EDITOR'));
            $this->assertFalse($user->hasRole('user'));
            $this->assertFalse($user->hasRole('editor'));
        });
    }

    public function test_memo_is_released_when_the_request_throws(): void
    {
        Route::middleware('api')->get('/api/p29-throws', fn () => throw new \RuntimeException('boom'));

        $this->actingAs($this->user('volunteer'), 'sanctum')->getJson('/api/p29-throws')->assertStatus(500);

        $this->assertFalse($this->memo->active());
    }
}
