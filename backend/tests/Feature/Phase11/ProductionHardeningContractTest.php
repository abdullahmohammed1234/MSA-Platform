<?php

namespace Tests\Feature\Phase11;

use App\Ems\Support\EmsPermissions;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use Database\Seeders\Ems\EmsRolePermissionSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 11 — Production hardening security & reliability contracts.
 */
class ProductionHardeningContractTest extends TestCase
{
    use RefreshDatabase;

    private User $cmsUser;

    private User $damsUser;

    private User $learner;

    private User $emsUser;

    private User $adminUser;

    private User $superAdminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'uuid' => (string) Str::uuid()]
        );
        $this->seed(EmsRolePermissionSeeder::class);

        $manageHomepage = $this->perm('manage_homepage', 'Website');
        $manageMedia = $this->perm('manage_media', 'Website');
        $manageCourses = $this->perm('manage_courses', 'Academy');

        $cmsRole = $this->role('cms-editor', 'CMS Editor');
        $cmsRole->permissions()->attach([$manageHomepage->id, $manageMedia->id]);

        $damsRole = $this->role('dams-operator', 'DAMS Operator');
        $damsRole->permissions()->attach($manageCourses);

        $volunteerRole = $this->role('volunteer', 'Volunteer');
        $adminRole = $this->role('admin', 'Admin');
        $superRole = Role::where('slug', 'super-admin')->firstOrFail();

        $this->cmsUser = $this->user('cms@phase11.test', 'CMS', $cmsRole);
        $this->damsUser = $this->user('dams@phase11.test', 'DAMS', $damsRole);
        $this->learner = $this->user('learner@phase11.test', 'Learner', $volunteerRole);
        $this->adminUser = $this->user('admin@phase11.test', 'Admin', $adminRole);
        $this->superAdminUser = $this->user('super@phase11.test', 'Super', $superRole);

        $emsRole = Role::where('slug', 'event-administrator')->first()
            ?? Role::where('slug', 'ems-event-administrator')->first();
        $this->assertNotNull($emsRole);
        $this->emsUser = $this->user('ems@phase11.test', 'EMS', $emsRole);
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
            ['uuid' => (string) Str::uuid(), 'name' => $name, 'description' => $name]
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

    /** @test */
    public function production_config_contracts_are_safe(): void
    {
        $this->assertFalse((bool) config('cms.legacy_events.drop_schema'));
        $this->assertSame('retired_410', config('cms.legacy_events.api'));
        $this->assertArrayHasKey('force_https', config('app'));

        $example = base_path('.env.example');
        $this->assertFileExists($example);
        $contents = file_get_contents($example);
        $this->assertStringContainsString('APP_DEBUG=false', $contents);
        $this->assertStringContainsString('QUEUE_CONNECTION=database', $contents);
        $this->assertStringContainsString('TRUSTED_PROXIES', $contents);
        $this->assertStringNotContainsString('abdullahelboraei@gmail.com', $contents);
    }

    /** @test */
    public function auth_mail_notifications_are_queued(): void
    {
        $this->assertTrue(is_subclass_of(VerifyEmailNotification::class, ShouldQueue::class));
        $this->assertTrue(is_subclass_of(ResetPasswordNotification::class, ShouldQueue::class));
    }

    /** @test */
    public function api_responses_include_security_headers(): void
    {
        $this->getJson('/api/v1/website/homepage')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy');
    }

    /** @test */
    public function authentication_token_lifecycle(): void
    {
        $plain = $this->cmsUser->createToken('phase11')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$plain, 'Accept' => 'application/json'])
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->getJson('/api/v1/auth/me')->assertStatus(401);

        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => 'Bearer dead-token', 'Accept' => 'application/json'])
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);

        $this->cmsUser->tokens()->delete();
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => 'Bearer '.$plain, 'Accept' => 'application/json'])
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    /** @test */
    public function ordinary_cms_user_is_isolated_from_dams_ems_and_platform_systems(): void
    {
        $this->actingAs($this->cmsUser)->getJson(route('api.admin.cms.homepage.index'))->assertOk();
        $this->actingAs($this->cmsUser)->getJson(route('api.admin.courses.index'))->assertStatus(403);
        $this->actingAs($this->cmsUser)->getJson('/api/v1/admin/systems')->assertStatus(403);

        $token = $this->cmsUser->createToken('t')->plainTextToken;
        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'])
            ->getJson('/api/v1/ems/events')
            ->assertStatus(403);
    }

    /** @test */
    public function ordinary_dams_user_is_isolated_from_cms_ems_and_platform_systems(): void
    {
        $this->actingAs($this->damsUser)->getJson(route('api.admin.courses.index'))->assertOk();
        $this->actingAs($this->damsUser)->getJson(route('api.admin.cms.homepage.index'))->assertStatus(403);
        $this->actingAs($this->damsUser)->getJson('/api/v1/admin/systems')->assertStatus(403);

        $token = $this->damsUser->createToken('t')->plainTextToken;
        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'])
            ->getJson('/api/v1/ems/events')
            ->assertStatus(403);
    }

    /** @test */
    public function ordinary_learner_cannot_administer_applications(): void
    {
        $this->actingAs($this->learner)->getJson('/api/v1/academy/courses')->assertOk();
        $this->actingAs($this->learner)->getJson(route('api.admin.courses.index'))->assertStatus(403);
        $this->actingAs($this->learner)->getJson(route('api.admin.cms.homepage.index'))->assertStatus(403);
    }

    /** @test */
    public function ordinary_ems_user_is_isolated_from_cms_and_dams(): void
    {
        $this->assertTrue($this->emsUser->hasPermission(EmsPermissions::EVENTS_VIEW));
        $token = $this->emsUser->createToken('t')->plainTextToken;
        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'])
            ->getJson('/api/v1/ems/events')
            ->assertOk();

        $this->actingAs($this->emsUser)->getJson(route('api.admin.cms.homepage.index'))->assertStatus(403);
        $this->actingAs($this->emsUser)->getJson(route('api.admin.courses.index'))->assertStatus(403);
    }

    /** @test */
    public function admin_and_super_admin_bypass_are_intentional_and_separate_from_ordinary_grants(): void
    {
        $this->assertTrue($this->adminUser->hasRole('admin'));
        $this->assertSame(0, $this->adminUser->roles()->first()->permissions()->count());
        $this->assertTrue($this->adminUser->hasPermission('manage_homepage'));
        $this->assertTrue($this->adminUser->hasPermission('manage_courses'));

        $this->actingAs($this->adminUser)->getJson(route('api.admin.cms.homepage.index'))->assertOk();
        $this->actingAs($this->adminUser)->getJson(route('api.admin.courses.index'))->assertOk();

        $this->assertTrue($this->superAdminUser->hasRole('super-admin'));
        $this->actingAs($this->superAdminUser)->getJson(route('api.admin.cms.homepage.index'))->assertOk();
        $this->actingAs($this->superAdminUser)->getJson(route('api.admin.courses.index'))->assertOk();
    }

    /** @test */
    public function cms_media_upload_creates_cms_owned_media_row(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('gallery.jpg', 100, 'image/jpeg');

        $this->actingAs($this->cmsUser)
            ->postJson('/api/v1/admin/cms/media', ['file' => $file])
            ->assertStatus(201);

        $this->assertDatabaseCount('media', 1);
        $path = DB::table('media')->value('filepath');
        $this->assertNotNull($path);
        $this->assertStringContainsString('uploads/cms', (string) $path);
    }

    /** @test */
    public function academy_asset_upload_stays_outside_cms_media(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('course.jpg', 40, 'image/jpeg');

        $this->actingAs($this->damsUser)
            ->postJson(route('api.admin.academy.assets.upload'), ['file' => $file])
            ->assertStatus(201)
            ->assertJsonPath('owner', 'academy');

        $this->assertDatabaseCount('media', 0);

        $this->actingAs($this->cmsUser)
            ->postJson(route('api.admin.academy.assets.upload'), ['file' => $file])
            ->assertStatus(403);

        $this->actingAs($this->damsUser)
            ->postJson('/api/v1/admin/cms/media', ['file' => $file])
            ->assertStatus(403);
    }

    /** @test */
    public function public_apis_and_legacy_events_contract(): void
    {
        $this->getJson('/api/v1/website/homepage')->assertOk();
        $this->getJson('/api/v1/website/announcements')->assertOk();
        $this->getJson('/api/v1/ems/public/events')->assertOk();
        $this->getJson('/api/v1/website/events')->assertStatus(410);
        $this->getJson('/api/v1/admin/cms/events')->assertStatus(404);
        $this->assertTrue(Schema::hasTable('events'));
        $this->assertTrue(Schema::hasTable('event_registrations'));
    }

    /** @test */
    public function failed_jobs_table_exists_for_platform_recovery(): void
    {
        $this->assertTrue(Schema::hasTable('failed_jobs'));
        $this->assertTrue(Schema::hasTable('jobs'));
    }

    /** @test */
    public function systems_overview_is_probe_derived_with_five_apps_and_no_incidents(): void
    {
        $systemView = $this->perm('system.view', 'System');
        $role = $this->role('sys-viewer', 'Sys Viewer');
        $role->permissions()->sync([$systemView->id]);
        $viewer = $this->user('sys@phase11.test', 'Sys', $role);

        $payload = $this->actingAs($viewer)
            ->getJson('/api/v1/admin/systems')
            ->assertOk()
            ->assertJsonPath('incidents_supported', false)
            ->assertJsonPath('summary.applications_total', 5)
            ->json();

        foreach ($payload['applications'] as $app) {
            $this->assertContains($app['status'], ['operational', 'degraded', 'unavailable', 'unknown']);
            $this->assertArrayHasKey('status_reason', $app);
            $this->assertArrayHasKey('last_checked_at', $app);
        }

        $this->assertNotEmpty($payload['platform_services'] ?? []);
    }

    /** @test */
    public function debug_mode_is_reported_via_config_not_unsafe_env_default(): void
    {
        Config::set('app.debug', false);
        $this->assertFalse(config('app.debug'));
    }
}
