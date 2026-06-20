<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Permission;
use App\Models\Role;
use App\Models\PerformanceMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles & permissions
        $viewPermission = Permission::firstOrCreate([
            'slug' => 'view_queue_status',
            'name' => 'View Queue Status',
            'module' => 'System'
        ]);

        $manageCourses = Permission::firstOrCreate([
            'slug' => 'manage_courses',
            'name' => 'Manage Courses',
            'module' => 'Academy'
        ]);

        $adminRole = Role::firstOrCreate([
            'slug' => 'admin',
            'name' => 'Admin'
        ]);

        $adminRole->permissions()->sync([$viewPermission->id, $manageCourses->id]);

        // Create users
        $this->admin = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $this->admin->roles()->sync([$adminRole->id]);

        $this->user = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Test admin performance dashboard API requires permission.
     */
    public function test_performance_stats_api_requires_permission(): void
    {
        // 1. Unauthenticated request should fail
        $response = $this->getJson('/api/v1/admin/system/performance');
        $response->assertStatus(401);

        // 2. User without permission should fail
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/admin/system/performance');
        $response->assertStatus(403);

        // 3. Admin with permission should succeed
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/system/performance');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'recent_metrics',
                'averages' => [
                    'duration_ms',
                    'db_queries',
                    'db_queries_time_ms',
                    'memory_mb',
                    'total_requests',
                ],
                'cache' => [
                    'hit_rate',
                    'cached_requests_count',
                    'total_requests_24h',
                ],
                'queues',
                'slow_requests',
                'system',
            ]);
    }

    /**
     * Test performance middleware logs the request.
     */
    public function test_performance_middleware_records_metric(): void
    {
        // Initial database count
        $initialCount = PerformanceMetric::count();

        // Make any request (even to a non-existent url or standard url)
        $this->getJson('/api/v1/website/homepage');

        // Check if a metric record was logged
        $this->assertEquals($initialCount + 1, PerformanceMetric::count());

        $metric = PerformanceMetric::latest()->first();
        $this->assertEquals('/api/v1/website/homepage', $metric->url);
        $this->assertEquals('GET', $metric->method);
        $this->assertGreaterThan(0, $metric->duration_ms);
    }

    /**
     * Test website caching and cache invalidation.
     */
    public function test_caching_on_website_homepage(): void
    {
        // Set up initial cache value
        Cache::forget('website_homepage');

        $this->getJson('/api/v1/website/homepage')->assertStatus(200);

        // Assert cached
        $this->assertTrue(Cache::has('website_homepage'));

        // Modify cache value manually to prove it loads from cache
        Cache::forever('website_homepage', ['custom_key' => 'custom_val']);

        $response = $this->getJson('/api/v1/website/homepage');
        $response->assertStatus(200)
            ->assertJsonPath('homepage.custom_key', 'custom_val');

        // Forget cache manually
        Cache::forget('website_homepage');
        $this->assertFalse(Cache::has('website_homepage'));
    }

    /**
     * Test courses listing uses cache invalidation with versioning.
     */
    public function test_caching_on_academy_courses(): void
    {
        Cache::forget('academy_courses_version');

        $this->actingAs($this->user)->getJson('/api/v1/academy/courses')->assertStatus(200);

        // Get initial version
        $version = Cache::get('academy_courses_version', 1);
        $this->assertEquals(1, $version);

        // Verify version increments when cache is invalidated in administrative controller
        $this->actingAs($this->admin)->postJson('/api/v1/admin/academy/courses', [
            'title' => 'New Optimized Course',
            'slug' => 'new-optimized-course',
            'description' => 'Course description',
            'difficulty' => 'beginner',
            'status' => 'draft',
        ])->assertStatus(201);

        $newVersion = Cache::get('academy_courses_version');
        $this->assertEquals(2, $newVersion);
    }
}
