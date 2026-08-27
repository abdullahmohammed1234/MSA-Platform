<?php

namespace Tests\Feature\Systems;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SystemsControlPlaneTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    private User $denied;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $systemView = Permission::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'View System',
            'slug' => 'system.view',
            'module' => 'System',
            'description' => 'View systems',
        ]);

        $viewerRole = Role::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Systems Viewer',
            'slug' => 'systems-viewer',
        ]);
        $viewerRole->permissions()->attach($systemView);

        $deniedRole = Role::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'CMS Editor',
            'slug' => 'cms-only',
        ]);
        $homepage = Permission::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Manage Homepage',
            'slug' => 'manage_homepage',
            'module' => 'Website',
            'description' => 'CMS only',
        ]);
        $deniedRole->permissions()->attach($homepage);

        $adminRole = Role::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin',
            'slug' => 'admin',
        ]);

        $this->viewer = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Viewer',
            'email' => 'systems-viewer@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $this->viewer->roles()->attach($viewerRole);

        app(\App\Services\ApplicationAccessService::class)->grant($this->viewer, 'admin-portal');

        $this->denied = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'CMS',
            'email' => 'cms-only-systems@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $this->denied->roles()->attach($deniedRole);

        $this->admin = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin',
            'email' => 'admin-systems@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $this->admin->roles()->attach($adminRole);
    }

    /** @test */
    public function unauthorized_users_cannot_view_systems_overview(): void
    {
        $this->getJson('/api/v1/admin/systems')->assertStatus(401);

        $this->actingAs($this->denied)
            ->getJson('/api/v1/admin/systems')
            ->assertStatus(403);
    }

    /** @test */
    public function authorized_viewer_gets_five_applications_and_platform_services(): void
    {
        $response = $this->actingAs($this->viewer)
            ->getJson('/api/v1/admin/systems')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $ids = collect($response->json('applications'))->pluck('id')->all();
        $this->assertSame(
            ['main-website', 'cms', 'dawah-academy', 'dams', 'ems'],
            $ids
        );
        $this->assertCount(5, $ids);
        $this->assertCount(5, array_unique($ids));

        $urls = collect($response->json('applications'))->pluck('url', 'id');
        $this->assertSame('/', $urls['main-website']);
        $this->assertSame('/cms', $urls['cms']);
        $this->assertSame('/academy', $urls['dawah-academy']);
        $this->assertSame('/dams', $urls['dams']);
        $this->assertSame('/ems', $urls['ems']);

        $serviceIds = collect($response->json('platform_services'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing(['queues', 'database', 'email', 'storage'], $serviceIds);

        $this->assertSame('security-center', $response->json('security.id'));
        $this->assertFalse($response->json('incidents_supported'));

        foreach ($response->json('applications') as $app) {
            $this->assertContains($app['status'], ['operational', 'degraded', 'unavailable', 'unknown']);
            $this->assertArrayHasKey('last_checked_at', $app);
            $this->assertArrayHasKey('connection_status', $app);
            $this->assertArrayHasKey('dependencies', $app);
            // Must not leak secrets
            $encoded = json_encode($app);
            $this->assertStringNotContainsString('password', strtolower($encoded));
            $this->assertStringNotContainsString('secret', strtolower($encoded));
            $this->assertStringNotContainsString('token', strtolower($encoded));
        }
    }

    /** @test */
    public function admin_bypass_can_view_systems(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/systems')
            ->assertStatus(200);
    }

    /** @test */
    public function registry_detail_and_health_endpoints_work(): void
    {
        $this->actingAs($this->viewer)
            ->getJson('/api/v1/admin/systems/registry/cms')
            ->assertStatus(200)
            ->assertJsonPath('system.id', 'cms')
            ->assertJsonPath('system.url', '/cms');

        $this->actingAs($this->viewer)
            ->getJson('/api/v1/admin/systems/registry/dams/health')
            ->assertStatus(200)
            ->assertJsonStructure(['health' => ['status', 'last_checked_at']]);

        $this->actingAs($this->viewer)
            ->getJson('/api/v1/admin/systems/registry/not-a-system')
            ->assertStatus(404);
    }

    /** @test */
    public function ownership_boundaries_are_represented_in_registry(): void
    {
        $response = $this->actingAs($this->viewer)
            ->getJson('/api/v1/admin/systems')
            ->assertStatus(200);

        $byId = collect($response->json('applications'))->keyBy('id');

        $this->assertContains('ems_events', $byId['ems']['owns']);
        $this->assertContains('cms_content', $byId['main-website']['does_not_own']);
        $this->assertContains('learner_experience', $byId['dawah-academy']['owns']);
        $this->assertContains('courses_admin', $byId['dams']['owns']);
        $this->assertContains('ems_events', $byId['cms']['does_not_own']);
    }

    /** @test */
    public function dams_health_endpoint_is_not_hardcoded_true_booleans(): void
    {
        $response = $this->actingAs($this->viewer)
            ->getJson('/api/v1/admin/systems/dams/health')
            ->assertStatus(200);

        $checks = $response->json('health.checks');
        $this->assertIsArray($checks);
        $this->assertArrayNotHasKey('courses', $checks);
        $this->assertArrayNotHasKey('quizzes', $checks);
    }

    /** @test */
    public function applications_include_status_reason_and_dependency_details(): void
    {
        $response = $this->actingAs($this->viewer)
            ->getJson('/api/v1/admin/systems?refresh=1')
            ->assertStatus(200);

        $cms = collect($response->json('applications'))->firstWhere('id', 'cms');
        $this->assertNotNull($cms);
        $this->assertArrayHasKey('status_reason', $cms);
        $this->assertNotEmpty($cms['dependency_details']);
        $this->assertArrayHasKey('label', $cms['dependency_details'][0]);
        $this->assertArrayHasKey('status', $cms['dependency_details'][0]);
    }

    /** @test */
    public function platform_service_detail_exposes_queue_partitions_without_payloads(): void
    {
        $response = $this->actingAs($this->viewer)
            ->getJson('/api/v1/admin/systems/services/queues?refresh=1')
            ->assertStatus(200)
            ->assertJsonPath('service.id', 'queues');

        $encoded = json_encode($response->json());
        $this->assertStringNotContainsString('password', strtolower($encoded));
        $this->assertIsArray($response->json('service.partitions'));
    }

    /** @test */
    public function systems_overview_does_not_mutate_cms_or_academy_data(): void
    {
        $beforeAnnouncements = \App\Models\CMS\Announcement::count();
        $beforeCourses = \App\Models\Course::count();
        $beforeEvents = \App\Ems\Models\Event::count();

        $this->actingAs($this->viewer)
            ->getJson('/api/v1/admin/systems?refresh=1')
            ->assertStatus(200);

        $this->assertSame($beforeAnnouncements, \App\Models\CMS\Announcement::count());
        $this->assertSame($beforeCourses, \App\Models\Course::count());
        $this->assertSame($beforeEvents, \App\Ems\Models\Event::count());
    }
}
