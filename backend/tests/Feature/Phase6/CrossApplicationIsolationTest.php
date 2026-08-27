<?php

namespace Tests\Feature\Phase6;

use App\Ems\Support\EmsPermissions;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\Ems\EmsRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 6 — Cross-application isolation + permission completeness (non-admin).
 */
class CrossApplicationIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $cmsUser;

    private User $damsUser;

    private User $learner;

    private User $emsUser;

    private User $adminUser;

    private User $superAdminUser;

    private User $platformUserManager;

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
        $viewProgress = $this->perm('view_progress', 'Academy');
        $manageMentors = $this->perm('manage_mentors', 'Academy');
        $manageQuizzes = $this->perm('manage_quizzes', 'Academy');
        $manageDiscussions = $this->perm('manage_discussions', 'Academy');
        $manageModules = $this->perm('manage_modules', 'Academy');
        $manageLessons = $this->perm('manage_lessons', 'Academy');
        $manageLearningPaths = $this->perm('manage_learning_paths', 'Academy');

        $cmsRole = $this->role('cms-editor', 'CMS Editor');
        $cmsRole->permissions()->attach($manageHomepage);

        $damsRole = $this->role('dams-operator', 'DAMS Operator');
        $damsRole->permissions()->attach([
            $manageCourses->id,
            $manageStudents->id,
            $viewProgress->id,
            $manageMentors->id,
            $manageQuizzes->id,
            $manageDiscussions->id,
            $manageModules->id,
            $manageLessons->id,
            $manageLearningPaths->id,
        ]);

        $volunteerRole = $this->role('volunteer', 'Volunteer');

        $platformRole = $this->role('user-admin', 'User Admin');
        $platformRole->permissions()->attach($manageUsers);

        $adminRole = $this->role('admin', 'Admin');
        $superRole = Role::where('slug', 'super-admin')->firstOrFail();

        $emsRole = Role::where('slug', 'event-organizer')->firstOrFail();

        $this->cmsUser = $this->user('cms@example.com', 'CMS', $cmsRole);
        $this->damsUser = $this->user('dams@example.com', 'DAMS', $damsRole);
        $this->learner = $this->user('learner@example.com', 'Learner', $volunteerRole);
        $this->platformUserManager = $this->user('users@example.com', 'Users', $platformRole);
        $this->adminUser = $this->user('admin@example.com', 'Admin', $adminRole);
        $this->superAdminUser = $this->user('super@example.com', 'Super', $superRole);
        $this->emsUser = $this->user('ems@example.com', 'EMS', $emsRole);

        // Grant explicit application access for testing independent gates
        $appAccessService = app(\App\Services\ApplicationAccessService::class);
        $appAccessService->grant($this->cmsUser, 'cms');
        $appAccessService->grant($this->damsUser, 'dams');
        $appAccessService->grant($this->platformUserManager, 'admin-portal');
        $appAccessService->grant($this->emsUser, 'ems');
    }

    private function perm(string $slug, string $module): Permission
    {
        return Permission::firstOrCreate(
            ['slug' => $slug],
            [
                'uuid' => (string) Str::uuid(),
                'name' => ucfirst(str_replace(['_', '.'], ' ', $slug)),
                'module' => $module,
                'description' => $slug,
            ]
        );
    }

    private function role(string $slug, string $name): Role
    {
        return Role::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'uuid' => (string) Str::uuid(), 'description' => $name]
        );
    }

    private function user(string $email, string $name, Role $role): User
    {
        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $user->roles()->attach($role);

        return $user;
    }

    /** @test */
    public function missing_token_is_rejected_on_admin_surfaces(): void
    {
        $this->getJson(route('api.admin.cms.homepage.index'))->assertStatus(401);
        $this->getJson(route('api.admin.courses.index'))->assertStatus(401);
        $this->getJson('/api/v1/ems/events')->assertStatus(401);
    }

    /** @test */
    public function cms_permission_allows_cms_and_denies_dams_ems_platform_users(): void
    {
        $this->actingAs($this->cmsUser)
            ->getJson(route('api.admin.cms.homepage.index'))
            ->assertStatus(200);

        $this->actingAs($this->cmsUser)
            ->getJson(route('api.admin.courses.index'))
            ->assertStatus(403);

        $this->actingAs($this->cmsUser)
            ->getJson('/api/v1/ems/events')
            ->assertStatus(403);

        $this->actingAs($this->cmsUser)
            ->getJson(route('api.admin.users.index'))
            ->assertStatus(403);
    }

    /** @test */
    public function dams_permission_allows_dams_and_denies_cms_ems_platform_users(): void
    {
        $this->actingAs($this->damsUser)
            ->getJson(route('api.admin.courses.index'))
            ->assertStatus(200);

        $this->actingAs($this->damsUser)
            ->getJson(route('api.admin.cms.homepage.index'))
            ->assertStatus(403);

        $this->actingAs($this->damsUser)
            ->getJson('/api/v1/ems/events')
            ->assertStatus(403);

        $this->actingAs($this->damsUser)
            ->getJson(route('api.admin.users.index'))
            ->assertStatus(403);
    }

    /** @test */
    public function academy_learner_allows_learner_apis_and_denies_dams_admin(): void
    {
        $this->actingAs($this->learner)
            ->getJson(route('api.academy.courses'))
            ->assertStatus(200);

        $this->actingAs($this->learner)
            ->getJson(route('api.admin.courses.index'))
            ->assertStatus(403);

        $this->actingAs($this->learner)
            ->getJson(route('api.admin.cms.homepage.index'))
            ->assertStatus(403);
    }

    /** @test */
    public function ems_permission_allows_ems_and_denies_cms_and_dams(): void
    {
        $this->assertTrue($this->emsUser->hasPermission(EmsPermissions::EVENTS_VIEW));

        $token = $this->emsUser->createToken('phase6')->plainTextToken;
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

    /** @test */
    public function manage_events_legacy_cms_slug_does_not_grant_ems_administration(): void
    {
        $legacy = $this->perm('manage_events', 'Website');
        $role = $this->role('legacy-events', 'Legacy Events');
        $role->permissions()->attach($legacy);
        $user = $this->user('legacy-events@example.com', 'Legacy', $role);

        $this->assertFalse($user->hasPermission(EmsPermissions::EVENTS_VIEW));

        $token = $user->createToken('phase6')->plainTextToken;
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/ems/events')->assertStatus(403);
    }

    /** @test */
    public function admin_and_super_admin_bypass_still_works(): void
    {
        $this->actingAs($this->adminUser)
            ->getJson(route('api.admin.courses.index'))
            ->assertStatus(200);

        $this->actingAs($this->superAdminUser)
            ->getJson(route('api.admin.cms.homepage.index'))
            ->assertStatus(200);
    }

    /** @test */
    public function non_admin_manage_students_permission_completeness(): void
    {
        $this->actingAs($this->damsUser)
            ->getJson(route('api.admin.academy.students.index'))
            ->assertStatus(200);
    }

    /** @test */
    public function course_assets_remain_outside_cms_media(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('c.jpg', 40, 'image/jpeg');

        $this->actingAs($this->damsUser)
            ->postJson(route('api.admin.academy.assets.upload'), ['file' => $file])
            ->assertStatus(201)
            ->assertJsonPath('owner', 'academy');

        $this->assertDatabaseCount('media', 0);

        $this->actingAs($this->damsUser)
            ->postJson(route('api.admin.cms.assets.upload'), ['file' => $file])
            ->assertStatus(403);
    }

    /** @test */
    public function main_website_systems_metrics_report_ems_as_event_source(): void
    {
        $viewer = $this->user('sys@example.com', 'Sys', $this->role('sys', 'Sys'));
        $viewer->roles()->first()->permissions()->attach(
            $this->perm('system.view', 'System')
        );
        app(\App\Services\ApplicationAccessService::class)->grant($viewer, 'admin-portal');

        $this->actingAs($viewer)
            ->getJson('/api/v1/admin/systems/main-website/metrics')
            ->assertStatus(200)
            ->assertJsonPath('metrics.events_source', 'ems');
    }

    /** @test */
    public function public_ems_events_endpoint_is_authoritative_for_website_display(): void
    {
        $this->getJson('/api/v1/ems/public/events')
            ->assertStatus(200);

        // Phase 9 — legacy CMS website events API is retired (410 Gone)
        $this->getJson('/api/v1/website/events')
            ->assertStatus(410)
            ->assertJsonPath('retired', true)
            ->assertJsonPath('replacement', '/api/v1/ems/public/events');
    }

    /** @test */
    public function systems_registry_exposes_five_applications(): void
    {
        $viewer = $this->user('reg@example.com', 'Reg', $this->role('reg', 'Reg'));
        $viewer->roles()->first()->permissions()->attach(
            $this->perm('system.view', 'System')
        );
        app(\App\Services\ApplicationAccessService::class)->grant($viewer, 'admin-portal');

        foreach (['main-website', 'cms', 'dawah-academy', 'dams', 'ems'] as $slug) {
            $this->actingAs($viewer)
                ->getJson("/api/v1/admin/systems/{$slug}")
                ->assertStatus(200)
                ->assertJsonPath('success', true);
        }
    }
}
