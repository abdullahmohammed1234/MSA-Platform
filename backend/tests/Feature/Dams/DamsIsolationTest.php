<?php

namespace Tests\Feature\Dams;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 5 — DAMS application boundary isolation.
 * APIs remain at /api/v1/admin/academy/*; SPA ownership is /dams.
 */
class DamsIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $damsUser;

    private User $cmsOnlyUser;

    private User $emsOnlyUser;

    private User $learner;

    private User $adminUser;

    private User $superAdminUser;

    private Permission $manageCourses;

    private Permission $manageHomepage;

    private Permission $manageStudents;

    private Permission $assignMentors;

    private Permission $viewStudentProgress;

    private Permission $manageQuestionBank;

    private Permission $eventsView;

    private Permission $systemView;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manageCourses = $this->makePermission('manage_courses', 'Academy');
        $this->manageHomepage = $this->makePermission('manage_homepage', 'Website');
        $this->manageStudents = $this->makePermission('manage_students', 'Academy');
        $this->assignMentors = $this->makePermission('assign_mentors', 'Academy');
        $this->viewStudentProgress = $this->makePermission('view_student_progress', 'Academy');
        $this->manageQuestionBank = $this->makePermission('manage_question_bank', 'Academy');
        $this->eventsView = $this->makePermission('events.view', 'EMS');
        $this->systemView = $this->makePermission('system.view', 'System');
        $this->makePermission('view_progress', 'Academy');
        $this->makePermission('manage_mentors', 'Academy');
        $this->makePermission('manage_quizzes', 'Academy');
        $this->makePermission('manage_modules', 'Academy');
        $this->makePermission('manage_lessons', 'Academy');
        $this->makePermission('manage_learning_paths', 'Academy');
        $this->makePermission('manage_discussions', 'Academy');
        $this->makePermission('manage_achievements', 'Academy');
        $this->makePermission('manage_badges', 'Academy');
        $this->makePermission('view_analytics', 'Analytics');

        $damsRole = $this->makeRole('dams-operator', 'DAMS Operator');
        $damsRole->permissions()->attach([
            $this->manageCourses->id,
            $this->manageStudents->id,
            Permission::where('slug', 'view_progress')->first()->id,
            Permission::where('slug', 'manage_mentors')->first()->id,
            Permission::where('slug', 'manage_quizzes')->first()->id,
            Permission::where('slug', 'manage_modules')->first()->id,
            Permission::where('slug', 'manage_lessons')->first()->id,
            Permission::where('slug', 'manage_learning_paths')->first()->id,
            Permission::where('slug', 'manage_discussions')->first()->id,
        ]);

        $cmsRole = $this->makeRole('cms-editor', 'CMS Editor');
        $cmsRole->permissions()->attach($this->manageHomepage);

        $emsRole = $this->makeRole('ems-staff', 'EMS Staff');
        $emsRole->permissions()->attach($this->eventsView);

        $volunteerRole = $this->makeRole('volunteer', 'Volunteer');

        $adminRole = $this->makeRole('admin', 'Admin');
        $superRole = $this->makeRole('super-admin', 'Super Admin');

        $this->damsUser = $this->makeUser('dams@example.com', 'DAMS User', $damsRole);
        $this->cmsOnlyUser = $this->makeUser('cms@example.com', 'CMS User', $cmsRole);
        $this->emsOnlyUser = $this->makeUser('ems@example.com', 'EMS User', $emsRole);
        $this->learner = $this->makeUser('learner@example.com', 'Learner', $volunteerRole);
        $this->adminUser = $this->makeUser('admin@example.com', 'Admin', $adminRole);
        $this->superAdminUser = $this->makeUser('super@example.com', 'Super Admin', $superRole);
    }

    private function makePermission(string $slug, string $module): Permission
    {
        return Permission::create([
            'uuid' => (string) Str::uuid(),
            'name' => ucfirst(str_replace(['_', '.'], ' ', $slug)),
            'slug' => $slug,
            'module' => $module,
            'description' => 'Test '.$slug,
        ]);
    }

    private function makeRole(string $slug, string $name): Role
    {
        return Role::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => $slug,
            'description' => $name,
        ]);
    }

    private function makeUser(string $email, string $name, Role $role): User
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
    public function dams_permission_allows_academy_admin_courses(): void
    {
        $this->actingAs($this->damsUser)
            ->getJson(route('api.admin.courses.index'))
            ->assertStatus(200);
    }

    /** @test */
    public function cms_only_user_is_denied_dams_admin_apis(): void
    {
        $this->actingAs($this->cmsOnlyUser)
            ->getJson(route('api.admin.courses.index'))
            ->assertStatus(403);

        $this->actingAs($this->cmsOnlyUser)
            ->getJson(route('api.admin.academy.students.index'))
            ->assertStatus(403);

        $this->actingAs($this->cmsOnlyUser)
            ->getJson(route('api.admin.academy.progress.index'))
            ->assertStatus(403);
    }

    /** @test */
    public function ems_only_user_is_denied_dams_admin_apis(): void
    {
        $this->actingAs($this->emsOnlyUser)
            ->getJson(route('api.admin.courses.index'))
            ->assertStatus(403);

        $this->actingAs($this->emsOnlyUser)
            ->getJson(route('api.admin.academy.quizzes.index'))
            ->assertStatus(403);
    }

    /** @test */
    public function academy_learner_is_denied_dams_admin_apis(): void
    {
        $this->actingAs($this->learner)
            ->getJson(route('api.admin.courses.index'))
            ->assertStatus(403);

        $this->actingAs($this->learner)
            ->postJson(route('api.admin.academy.assets.upload'), [
                'file' => UploadedFile::fake()->create('x.jpg', 40, 'image/jpeg'),
            ])
            ->assertStatus(403);
    }

    /** @test */
    public function admin_and_super_admin_bypass_allows_dams_apis(): void
    {
        $this->actingAs($this->adminUser)
            ->getJson(route('api.admin.courses.index'))
            ->assertStatus(200);

        $this->actingAs($this->superAdminUser)
            ->getJson(route('api.admin.courses.index'))
            ->assertStatus(200);
    }

    /** @test */
    public function dams_permission_does_not_grant_cms_admin(): void
    {
        $this->actingAs($this->damsUser)
            ->getJson(route('api.admin.cms.homepage.index'))
            ->assertStatus(403);

        $this->actingAs($this->damsUser)
            ->getJson(route('api.admin.cms.dashboard'))
            ->assertStatus(403);
    }

    /** @test */
    public function cms_only_user_can_still_access_cms_homepage(): void
    {
        $this->actingAs($this->cmsOnlyUser)
            ->getJson(route('api.admin.cms.homepage.index'))
            ->assertStatus(200);
    }

    /** @test */
    public function learner_academy_apis_remain_role_gated_not_dams_permissions(): void
    {
        // Learner with volunteer role may access learner catalog; DAMS manage_courses does not replace learner role gate.
        $this->actingAs($this->learner)
            ->getJson(route('api.academy.courses'))
            ->assertStatus(200);

        // DAMS operator without volunteer/mentor/admin role is denied learner APIs.
        $this->actingAs($this->damsUser)
            ->getJson(route('api.academy.courses'))
            ->assertStatus(403);
    }

    /** @test */
    public function representative_dams_endpoints_authorize_correctly(): void
    {
        $ok = [
            'api.admin.courses.index',
            'api.admin.academy.quizzes.index',
            'api.admin.academy.students.index',
            'api.admin.academy.mentors.index',
            'api.admin.academy.progress.index',
            'api.admin.discussions.reports',
            'api.admin.academy.learning-paths.index',
        ];

        foreach ($ok as $routeName) {
            $this->actingAs($this->damsUser)
                ->getJson(route($routeName))
                ->assertStatus(200, "Expected 200 for {$routeName}");

            $this->actingAs($this->cmsOnlyUser)
                ->getJson(route($routeName))
                ->assertStatus(403, "Expected 403 for CMS on {$routeName}");
        }
    }

    /** @test */
    public function course_asset_upload_stays_academy_owned_not_cms_media(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('thumb.jpg', 50, 'image/jpeg');

        $response = $this->actingAs($this->damsUser)
            ->postJson(route('api.admin.academy.assets.upload'), ['file' => $file])
            ->assertStatus(201)
            ->assertJsonPath('owner', 'academy');

        $this->assertStringContainsString('uploads/academy/', $response->json('url'));
        $this->assertDatabaseCount('media', 0);

        $this->actingAs($this->damsUser)
            ->postJson(route('api.admin.cms.assets.upload'), ['file' => $file])
            ->assertStatus(403);
    }

    /** @test */
    public function dams_systems_registry_requires_system_view_or_admin(): void
    {
        $this->actingAs($this->damsUser)
            ->getJson('/api/v1/admin/systems/dams')
            ->assertStatus(403);

        $viewer = $this->makeUser('sys@example.com', 'System Viewer', $this->makeRole('sys-viewer', 'System Viewer'));
        $viewer->roles()->first()->permissions()->attach($this->systemView);

        $this->actingAs($viewer)
            ->getJson('/api/v1/admin/systems/dams')
            ->assertStatus(200)
            ->assertJsonPath('system.slug', 'dams');

        $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/systems/dams/metrics')
            ->assertStatus(200);
    }

    /**
     * @test
     * Phase 6 decisions:
     * - manage_students: SEEDED (was gap)
     * - assign_mentors / view_student_progress / manage_question_bank: REMOVED as dead aliases
     */
    public function rbac_gap_resolutions_are_reflected_in_seed_and_routes(): void
    {
        $this->assertTrue(
            collect([
                'manage_courses',
                'manage_modules',
                'manage_lessons',
                'manage_quizzes',
                'manage_certificates',
                'manage_volunteers',
                'manage_mentors',
                'manage_students',
                'manage_learning_paths',
                'view_progress',
                'manage_progress',
                'manage_discussions',
            ])->contains('manage_students')
        );

        $studentsRoute = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->first(fn ($r) => $r->getName() === 'api.admin.academy.students.index');
        $this->assertNotNull($studentsRoute);
        $this->assertTrue(
            collect($studentsRoute->gatherMiddleware())->contains('permission:manage_students')
        );

        $questionsRoute = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->first(fn ($r) => $r->getName() === 'api.admin.academy.questions.index');
        $this->assertNotNull($questionsRoute);
        $this->assertTrue(
            collect($questionsRoute->gatherMiddleware())->contains('permission:manage_quizzes')
        );

        // Dead aliases must not be required by middleware
        $this->assertFalse(
            collect($studentsRoute->gatherMiddleware())->contains('permission:assign_mentors')
        );
    }
}
