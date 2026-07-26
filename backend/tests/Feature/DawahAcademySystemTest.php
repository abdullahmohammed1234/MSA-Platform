<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DawahAcademySystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles & permissions
        $viewPermission = Permission::firstOrCreate([
            'slug' => 'system.view',
            'name' => 'View System Status',
            'module' => 'System'
        ]);
        
        $managePermission = Permission::firstOrCreate([
            'slug' => 'system.manage',
            'name' => 'Manage Systems',
            'module' => 'System'
        ]);

        $adminRole = Role::firstOrCreate([
            'slug' => 'admin',
            'name' => 'Admin'
        ]);

        $adminRole->permissions()->sync([$viewPermission->id, $managePermission->id]);

        // Create users
        $this->admin = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $this->admin->roles()->sync([$adminRole->id]);

        $this->user = User::factory()->create([
            'is_active' => true,
        ]);
    }

    /** @test */
    public function guests_cannot_access_academy_system_endpoints()
    {
        $this->getJson('/api/v1/admin/systems/dawah-academy')->assertStatus(401);
        $this->getJson('/api/v1/admin/systems/dawah-academy/health')->assertStatus(401);
        $this->getJson('/api/v1/admin/systems/dawah-academy/metrics')->assertStatus(401);
    }

    /** @test */
    public function users_without_permission_are_forbidden()
    {
        $this->actingAs($this->user);

        $this->getJson('/api/v1/admin/systems/dawah-academy')->assertStatus(403);
        $this->getJson('/api/v1/admin/systems/dawah-academy/health')->assertStatus(403);
        $this->getJson('/api/v1/admin/systems/dawah-academy/metrics')->assertStatus(403);
        $this->getJson('/api/v1/admin/systems/dawah-academy/config')->assertStatus(403);
    }

    /** @test */
    public function admin_can_monitor_dawah_academy_system()
    {
        $this->actingAs($this->admin);

        $this->getJson('/api/v1/admin/systems/dawah-academy')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['system' => ['name', 'status', 'version']]);

        $this->getJson('/api/v1/admin/systems/dawah-academy/health')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['health' => ['api', 'database', 'cache', 'discussions']]);

        $this->getJson('/api/v1/admin/systems/dawah-academy/metrics')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['metrics' => ['courses', 'modules', 'lessons', 'quizzes', 'questions', 'enrollments']]);
    }

    /** @test */
    public function admin_can_view_and_update_academy_configurations()
    {
        $this->actingAs($this->admin);

        // 1. Retrieve config
        $this->getJson('/api/v1/admin/systems/dawah-academy/config')
            ->assertStatus(200)
            ->assertJsonStructure(['config' => ['timezone', 'course_passing_score', 'max_quiz_attempts', 'daily_xp_limit']]);

        // 2. Save config
        $this->putJson('/api/v1/admin/systems/dawah-academy/config', [
            'timezone' => 'America/Toronto',
            'course_passing_score' => 90,
            'max_quiz_attempts' => 5,
            'email_notifications' => false,
            'gamification_enabled' => false,
            'daily_xp_limit' => 1000
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true);

        // 3. Verify changes persist
        $this->getJson('/api/v1/admin/systems/dawah-academy/config')
            ->assertStatus(200)
            ->assertJsonPath('config.timezone', 'America/Toronto')
            ->assertJsonPath('config.course_passing_score', 90)
            ->assertJsonPath('config.daily_xp_limit', 1000);

        // Clean up
        @unlink(storage_path('app/academy_config.json'));
    }
}
