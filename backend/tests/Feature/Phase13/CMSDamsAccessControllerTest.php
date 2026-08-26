<?php

namespace Tests\Feature\Phase13;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CMSDamsAccessControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $cmsUser;
    private User $damsUser;
    private User $adminUser;
    private User $guestUser;

    protected function setUp(): void
    {
        parent::setUp();

        $viewAnalytics = $this->perm('view_analytics', 'System');
        $manageHomepage = $this->perm('manage_homepage', 'Website');
        $manageCourses = $this->perm('manage_courses', 'Academy');

        $cmsRole = $this->role('cms-editor', 'CMS Editor');
        $cmsRole->permissions()->attach([$manageHomepage->id]);

        $damsRole = $this->role('dams-operator', 'DAMS Operator');
        $damsRole->permissions()->attach([$manageCourses->id, $viewAnalytics->id]);

        $adminRole = $this->role('admin', 'Admin');

        $this->cmsUser = $this->user('cms@phase13.test', 'CMS', $cmsRole);
        $this->damsUser = $this->user('dams@phase13.test', 'DAMS', $damsRole);
        $this->adminUser = $this->user('admin@phase13.test', 'Admin', $adminRole);
        $this->guestUser = User::factory()->create([
            'email' => 'guest@phase13.test',
            'name' => 'Guest',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
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

    /**
     * Test unauthenticated access fails with 401.
     */
    public function test_unauthenticated_requests_are_denied(): void
    {
        $this->getJson('/api/v1/cms/users/me')
            ->assertStatus(401);

        $this->getJson('/api/v1/dams/users/me')
            ->assertStatus(401);

        $this->getJson('/api/v1/dams/analytics')
            ->assertStatus(401);
    }

    /**
     * Test CMS access resolution.
     */
    public function test_cms_access_resolution(): void
    {
        // 1. CMS Editor
        $this->actingAs($this->cmsUser)
            ->getJson('/api/v1/cms/users/me')
            ->assertStatus(200)
            ->assertJsonPath('data.has_cms_access', true)
            ->assertJsonFragment(['manage_homepage']);

        // 2. Guest (No CMS permissions)
        $this->actingAs($this->guestUser)
            ->getJson('/api/v1/cms/users/me')
            ->assertStatus(200)
            ->assertJsonPath('data.has_cms_access', false);

        // 3. Admin (Bypass)
        $this->actingAs($this->adminUser)
            ->getJson('/api/v1/cms/users/me')
            ->assertStatus(200)
            ->assertJsonPath('data.has_cms_access', true);
    }

    /**
     * Test DAMS access resolution.
     */
    public function test_dams_access_resolution(): void
    {
        // 1. DAMS Operator
        $this->actingAs($this->damsUser)
            ->getJson('/api/v1/dams/users/me')
            ->assertStatus(200)
            ->assertJsonPath('data.has_dams_access', true)
            ->assertJsonFragment(['manage_courses']);

        // 2. Guest (No DAMS permissions)
        $this->actingAs($this->guestUser)
            ->getJson('/api/v1/dams/users/me')
            ->assertStatus(200)
            ->assertJsonPath('data.has_dams_access', false);

        // 3. Admin (Bypass)
        $this->actingAs($this->adminUser)
            ->getJson('/api/v1/dams/users/me')
            ->assertStatus(200)
            ->assertJsonPath('data.has_dams_access', true);
    }

    /**
     * Test DAMS analytics security and output.
     */
    public function test_dams_analytics_security_and_output(): void
    {
        // 1. DAMS operator has view_analytics, should get 200
        $this->actingAs($this->damsUser)
            ->getJson('/api/v1/dams/analytics')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'summary' => [
                    'courses_count',
                    'enrolled_users_count',
                    'certificates_issued',
                    'enrollments' => [
                        'active',
                        'completed',
                        'dropped',
                    ],
                    'course_performance',
                    'quiz_performance',
                ],
                'recent_activity',
                'alerts',
                'workload',
            ]);

        // 2. CMS editor does not have view_analytics, should get 403
        $this->actingAs($this->cmsUser)
            ->getJson('/api/v1/dams/analytics')
            ->assertStatus(403);
    }
}
