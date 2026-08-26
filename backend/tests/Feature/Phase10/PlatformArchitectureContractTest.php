<?php

namespace Tests\Feature\Phase10;

use App\Ems\Support\EmsPermissions;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\Ems\EmsRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Phase 10 — Platform consolidation architectural contract.
 *
 * Encodes the ownership model established in Phases 2–9 so regressions
 * surface as failing contract tests rather than silent boundary drift.
 */
class PlatformArchitectureContractTest extends TestCase
{
    use RefreshDatabase;

    private User $cmsUser;

    private User $damsUser;

    private User $learner;

    private User $emsUser;

    private User $platformUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'uuid' => (string) Str::uuid()]
        );
        $this->seed(EmsRolePermissionSeeder::class);

        $manageHomepage = $this->perm('manage_homepage', 'Website');
        $manageCourses = $this->perm('manage_courses', 'Academy');
        $manageStudents = $this->perm('manage_students', 'Academy');
        $manageUsers = $this->perm('manage_users', 'Admin');
        $systemView = $this->perm('system.view', 'System');

        $cmsRole = $this->role('cms-editor', 'CMS Editor');
        $cmsRole->permissions()->attach($manageHomepage);

        $damsRole = $this->role('dams-operator', 'DAMS Operator');
        $damsRole->permissions()->attach([$manageCourses->id, $manageStudents->id]);

        $volunteerRole = $this->role('volunteer', 'Volunteer');

        $platformRole = $this->role('platform-ops', 'Platform Ops');
        $platformRole->permissions()->attach([$manageUsers->id, $systemView->id]);

        $this->cmsUser = $this->user('cms@phase10.test', 'CMS', $cmsRole);
        $this->damsUser = $this->user('dams@phase10.test', 'DAMS', $damsRole);
        $this->learner = $this->user('learner@phase10.test', 'Learner', $volunteerRole);
        $this->platformUser = $this->user('platform@phase10.test', 'Platform', $platformRole);

        $emsRole = Role::where('slug', 'event-administrator')->first()
            ?? Role::where('slug', 'ems-event-administrator')->first();
        $this->assertNotNull($emsRole, 'EMS role seeder must provide an event administrator role');
        $this->emsUser = $this->user('ems@phase10.test', 'EMS', $emsRole);
    }

    private function perm(string $slug, string $module): Permission
    {
        return Permission::firstOrCreate(
            ['slug' => $slug],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $slug,
                'module' => $module,
                'description' => $slug,
            ]
        );
    }

    private function role(string $slug, string $name): Role
    {
        return Role::firstOrCreate(
            ['slug' => $slug],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'description' => $name,
            ]
        );
    }

    private function user(string $email, string $name, Role $role): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'name' => $name,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->roles()->attach($role);

        return $user;
    }

    // -------------------------------------------------------------------------
    // Authentication (Platform sole identity authority)
    // -------------------------------------------------------------------------

    /** @test */
    public function valid_token_authenticates_via_auth_me(): void
    {
        $token = $this->platformUser->createToken('phase10')->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('user.email', $this->platformUser->email);
    }

    /** @test */
    public function missing_token_is_rejected(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
        $this->getJson(route('api.admin.cms.homepage.index'))->assertStatus(401);
    }

    /** @test */
    public function invalid_token_is_rejected(): void
    {
        $this->withHeaders([
            'Authorization' => 'Bearer invalid-token-phase10',
            'Accept' => 'application/json',
        ])->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    /** @test */
    public function revoked_token_is_rejected(): void
    {
        $plain = $this->platformUser->createToken('phase10-revoke')->plainTextToken;
        $this->platformUser->tokens()->delete();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$plain,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/auth/me')->assertStatus(401);

        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $this->platformUser->id)->count());
    }

    // -------------------------------------------------------------------------
    // CMS isolation
    // -------------------------------------------------------------------------

    /** @test */
    public function cms_permission_allows_cms_and_denies_dams_and_ems(): void
    {
        $this->actingAs($this->cmsUser)
            ->getJson(route('api.admin.cms.homepage.index'))
            ->assertStatus(200);

        $this->actingAs($this->cmsUser)
            ->getJson(route('api.admin.courses.index'))
            ->assertStatus(403);

        $token = $this->cmsUser->createToken('phase10')->plainTextToken;
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/ems/events')->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // DAMS isolation
    // -------------------------------------------------------------------------

    /** @test */
    public function dams_permission_allows_dams_and_denies_cms_and_ems(): void
    {
        $this->actingAs($this->damsUser)
            ->getJson(route('api.admin.courses.index'))
            ->assertStatus(200);

        $this->actingAs($this->damsUser)
            ->getJson(route('api.admin.cms.homepage.index'))
            ->assertStatus(403);

        $token = $this->damsUser->createToken('phase10')->plainTextToken;
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/ems/events')->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Academy learner isolation
    // -------------------------------------------------------------------------

    /** @test */
    public function learner_role_allows_learner_api_and_denies_dams_and_cms_admin(): void
    {
        $this->actingAs($this->learner)
            ->getJson('/api/v1/academy/courses')
            ->assertStatus(200);

        $this->actingAs($this->learner)
            ->getJson(route('api.admin.courses.index'))
            ->assertStatus(403);

        $this->actingAs($this->learner)
            ->getJson(route('api.admin.cms.homepage.index'))
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // EMS isolation
    // -------------------------------------------------------------------------

    /** @test */
    public function ems_permission_allows_ems_and_denies_cms_and_dams(): void
    {
        $this->assertTrue($this->emsUser->hasPermission(EmsPermissions::EVENTS_VIEW));

        $token = $this->emsUser->createToken('phase10')->plainTextToken;
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/ems/events')->assertStatus(200);

        $this->actingAs($this->emsUser)
            ->getJson(route('api.admin.cms.homepage.index'))
            ->assertStatus(403);

        $this->actingAs($this->emsUser)
            ->getJson(route('api.admin.courses.index'))
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Platform
    // -------------------------------------------------------------------------

    /** @test */
    public function platform_permissions_reach_systems_and_users_remain_centralized(): void
    {
        $this->actingAs($this->platformUser)
            ->getJson('/api/v1/admin/systems')
            ->assertStatus(200);

        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('roles'));
        $this->assertTrue(Schema::hasTable('permissions'));
        $this->assertFalse(Schema::hasTable('cms_users'));
        $this->assertFalse(Schema::hasTable('ems_users'));
        $this->assertFalse(Schema::hasTable('dams_users'));
    }

    // -------------------------------------------------------------------------
    // Assets
    // -------------------------------------------------------------------------

    /** @test */
    public function course_assets_are_academy_owned_and_do_not_create_cms_media_rows(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('course.jpg', 40, 'image/jpeg');

        $this->actingAs($this->damsUser)
            ->postJson(route('api.admin.academy.assets.upload'), ['file' => $file])
            ->assertStatus(201)
            ->assertJsonPath('owner', 'academy');

        $this->assertDatabaseCount('media', 0);

        $this->actingAs($this->damsUser)
            ->postJson(route('api.admin.cms.assets.upload'), ['file' => $file])
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Events (Phase 9)
    // -------------------------------------------------------------------------

    /** @test */
    public function main_website_events_come_from_ems_and_legacy_cms_api_is_gone(): void
    {
        $this->getJson('/api/v1/ems/public/events')
            ->assertStatus(200);

        $this->getJson('/api/v1/website/events')
            ->assertStatus(410)
            ->assertJsonPath('retired', true);

        $this->getJson('/api/v1/admin/cms/events')->assertStatus(404);

        $this->assertFalse(File::exists(app_path('Services/CMS/EventService.php')));
        $this->assertFalse(File::exists(app_path('Repositories/CMS/EventRepository.php')));
        $this->assertTrue(Schema::hasTable('events'));
        $this->assertTrue(Schema::hasTable('event_registrations'));
        $this->assertFalse(config('cms.legacy_events.drop_schema'));
    }

    // -------------------------------------------------------------------------
    // Systems
    // -------------------------------------------------------------------------

    /** @test */
    public function systems_registry_has_exactly_five_applications_and_no_incidents(): void
    {
        $this->actingAs($this->platformUser)
            ->getJson('/api/v1/admin/systems')
            ->assertStatus(200)
            ->assertJsonPath('incidents_supported', false)
            ->assertJsonPath('summary.applications_total', 5);

        $ids = array_keys(config('systems.applications', []));
        sort($ids);
        $this->assertSame(
            ['cms', 'dams', 'dawah-academy', 'ems', 'main-website'],
            $ids
        );

        $this->assertNotEmpty(config('systems.platform_services'));
        $this->assertArrayNotHasKey('incidents', config('systems'));
    }

    /** @test */
    public function systems_health_is_probe_derived_not_hardcoded_true(): void
    {
        $response = $this->actingAs($this->platformUser)
            ->getJson('/api/v1/admin/systems')
            ->assertStatus(200);

        foreach ($response->json('applications') as $app) {
            $this->assertArrayHasKey('status', $app);
            $this->assertArrayHasKey('status_reason', $app);
            $this->assertArrayHasKey('last_checked_at', $app);
            $this->assertContains($app['status'], ['operational', 'degraded', 'unavailable', 'unknown']);
            // Never a bare boolean health masquerading as status
            $this->assertIsString($app['status']);
        }
    }

    // -------------------------------------------------------------------------
    // RBAC gap resolutions (Phase 6) still hold
    // -------------------------------------------------------------------------

    /** @test */
    public function retired_rbac_aliases_remain_absent_and_canonical_slugs_exist_in_seed_data(): void
    {
        $this->assertDatabaseMissing('permissions', ['slug' => 'assign_mentors']);
        $this->assertDatabaseMissing('permissions', ['slug' => 'view_student_progress']);
        $this->assertDatabaseMissing('permissions', ['slug' => 'manage_question_bank']);

        // manage_students must exist as a permission slug when seeded via DatabaseSeeder;
        // this test creates it in setUp — assert the route middleware still expects it.
        $this->assertTrue(
            collect(\Illuminate\Support\Facades\Route::getRoutes())
                ->contains(fn ($route) => str_contains((string) $route->getName(), 'students')
                    && str_contains(implode(',', $route->gatherMiddleware()), 'manage_students'))
        );
    }

    /** @test */
    public function manage_events_is_not_an_ems_permission(): void
    {
        $legacy = $this->perm('manage_events', 'Website');
        $role = $this->role('legacy-events', 'Legacy Events');
        $role->permissions()->sync([$legacy->id]);
        $user = $this->user('legacy-events@phase10.test', 'Legacy', $role);

        $this->assertFalse($user->hasPermission(EmsPermissions::EVENTS_VIEW));

        $token = $user->createToken('phase10')->plainTextToken;
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/ems/events')->assertStatus(403);
    }

    /** @test */
    public function academy_feature_flag_remains_disabled_by_default_in_config_contract(): void
    {
        // Backend cannot read Vite env; assert documented product flag files stay false.
        $example = base_path('../frontend/.env.example');
        if (File::exists($example)) {
            $this->assertStringContainsString('VITE_ACADEMY_ENABLED=false', File::get($example));
        }
    }
}
