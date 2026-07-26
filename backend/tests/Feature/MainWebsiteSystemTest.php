<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MainWebsiteSystemTest extends TestCase
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
    public function guests_cannot_access_website_system_endpoints()
    {
        $this->getJson('/api/v1/admin/systems/main-website')->assertStatus(401);
        $this->getJson('/api/v1/admin/systems/main-website/health')->assertStatus(401);
        $this->getJson('/api/v1/admin/systems/main-website/metrics')->assertStatus(401);
    }

    /** @test */
    public function users_without_permission_are_forbidden()
    {
        $this->actingAs($this->user);

        $this->getJson('/api/v1/admin/systems/main-website')->assertStatus(403);
        $this->getJson('/api/v1/admin/systems/main-website/health')->assertStatus(403);
        $this->getJson('/api/v1/admin/systems/main-website/metrics')->assertStatus(403);
        $this->getJson('/api/v1/admin/systems/main-website/config')->assertStatus(403);
    }

    /** @test */
    public function admin_can_monitor_main_website_system()
    {
        $this->actingAs($this->admin);

        $this->getJson('/api/v1/admin/systems/main-website')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['system' => ['name', 'status', 'version']]);

        $this->getJson('/api/v1/admin/systems/main-website/health')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['health' => ['api', 'database', 'cache', 'storage']]);

        $this->getJson('/api/v1/admin/systems/main-website/metrics')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['metrics' => ['announcements', 'team_members', 'resources', 'media_assets', 'subscribers']]);
    }

    /** @test */
    public function admin_can_view_and_update_website_configurations()
    {
        $this->actingAs($this->admin);

        // 1. Retrieve config
        $this->getJson('/api/v1/admin/systems/main-website/config')
            ->assertStatus(200)
            ->assertJsonStructure(['config' => ['timezone', 'site_name', 'contact_recipient']]);

        // 2. Save config
        $this->putJson('/api/v1/admin/systems/main-website/config', [
            'timezone' => 'America/Toronto',
            'site_name' => 'SFU MSA Website Custom Name',
            'contact_recipient' => 'custom@sfumsa.org',
            'newsletter_enabled' => false,
            'social_facebook' => 'https://facebook.com/custommsa',
            'social_instagram' => 'https://instagram.com/custommsa',
            'cache_ttl' => 120
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true);

        // 3. Verify changes persist
        $this->getJson('/api/v1/admin/systems/main-website/config')
            ->assertStatus(200)
            ->assertJsonPath('config.timezone', 'America/Toronto')
            ->assertJsonPath('config.site_name', 'SFU MSA Website Custom Name')
            ->assertJsonPath('config.cache_ttl', 120);

        // Clean up
        @unlink(storage_path('app/website_config.json'));
    }
}
