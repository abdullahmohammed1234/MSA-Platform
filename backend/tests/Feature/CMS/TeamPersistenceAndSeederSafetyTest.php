<?php

namespace Tests\Feature\CMS;

use App\Models\CMS\TeamMember;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeamPersistenceAndSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $normalUser;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('website_team');

        $adminRole = Role::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Administrator with CMS permissions',
        ]);

        $manageTeamPerm = Permission::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Manage Team',
            'slug' => 'manage_team',
            'module' => 'Website',
            'description' => 'Manage CMS team members',
        ]);
        $adminRole->permissions()->attach($manageTeamPerm);

        $this->adminUser = User::factory()->create([
            'name' => 'Admin Tester',
            'email' => 'admin.test@example.com',
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($adminRole);

        $this->normalUser = User::factory()->create([
            'name' => 'Normal Member',
            'email' => 'normal.member@example.com',
            'is_active' => true,
        ]);
    }

    /**
     * Requirement 1 & 2: A CMS administrator can save a modified team list and it persists.
     */
    public function test_cms_administrator_can_create_and_persist_team_member(): void
    {
        $payload = [
            'name' => 'Sarah Al-Mansoor',
            'role' => 'President',
            'dept' => 'President',
            'bio' => 'Serving SFU MSA students with excellence.',
            'email' => 'president@sfumsa.ca',
            'linkedin' => 'https://linkedin.com/in/sarah-al-mansoor',
            'status' => 'published',
            'display_order' => 1,
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/cms/team', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('member.name', 'Sarah Al-Mansoor');

        $this->assertDatabaseHas('team_members', [
            'name' => 'Sarah Al-Mansoor',
            'role' => 'President',
            'email' => 'president@sfumsa.ca',
            'status' => 'published',
        ]);
    }

    /**
     * Requirement 3 & 4: Public endpoint returns modified team list, and repeated requests return modified values.
     */
    public function test_public_endpoint_returns_cms_saved_team_and_not_original_defaults(): void
    {
        // 1. Create a customized team roster
        $member = TeamMember::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Zayd Abdullah',
            'role' => 'Lead Outreach Director',
            'dept' => 'Directors',
            'status' => 'published',
            'display_order' => 1,
        ]);

        // 2. Query public endpoint
        $res1 = $this->getJson('/api/v1/website/team');
        $res1->assertStatus(200);
        $teamData1 = $res1->json('team');

        $this->assertCount(1, $teamData1);
        $this->assertEquals('Zayd Abdullah', $teamData1[0]['name']);
        $this->assertEquals('Lead Outreach Director', $teamData1[0]['role']);

        // 3. Repeated request (cached response) still returns CMS values, never default config
        $res2 = $this->getJson('/api/v1/website/team');
        $res2->assertStatus(200);
        $teamData2 = $res2->json('team');

        $this->assertCount(1, $teamData2);
        $this->assertEquals('Zayd Abdullah', $teamData2[0]['name']);
    }

    /**
     * Requirement 5: Updating a team member preserves all supported fields.
     */
    public function test_updating_team_member_updates_storage_and_preserves_supported_fields(): void
    {
        $member = TeamMember::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Original Name',
            'role' => 'Original Role',
            'dept' => 'Directors',
            'img' => '/Team/old_avatar.webp',
            'bio' => 'Original bio.',
            'email' => 'old@example.com',
            'linkedin' => 'https://linkedin.com/in/old',
            'display_order' => 5,
            'status' => 'published',
        ]);

        $updatePayload = [
            'name' => 'Updated Name',
            'role' => 'Updated Role',
            'dept' => 'Executive',
            'img' => '/Team/new_avatar.webp',
            'bio' => 'Updated bio with Ihsan.',
            'email' => 'updated@example.com',
            'linkedin' => 'https://linkedin.com/in/updated',
            'status' => 'published',
            'display_order' => 2,
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/v1/cms/team/{$member->uuid}", $updatePayload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('member.name', 'Updated Name')
            ->assertJsonPath('member.role', 'Updated Role')
            ->assertJsonPath('member.email', 'updated@example.com');

        $this->assertDatabaseHas('team_members', [
            'uuid' => $member->uuid,
            'name' => 'Updated Name',
            'role' => 'Updated Role',
            'email' => 'updated@example.com',
            'bio' => 'Updated bio with Ihsan.',
        ]);
    }

    /**
     * Requirement 6: Removing or reordering team members behaves correctly and invalidates cache.
     */
    public function test_deleting_and_reordering_team_members_updates_public_endpoint(): void
    {
        $m1 = TeamMember::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Member Alpha',
            'role' => 'Lead Alpha',
            'dept' => 'Directors',
            'display_order' => 1,
            'status' => 'published',
        ]);

        $m2 = TeamMember::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Member Beta',
            'role' => 'Lead Beta',
            'dept' => 'Directors',
            'display_order' => 2,
            'status' => 'published',
        ]);

        // Reorder via API
        $reorderRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/cms/team/reorder', [
                'uuids' => [$m2->uuid, $m1->uuid],
            ]);
        $reorderRes->assertStatus(200);

        $this->assertEquals(0, $m2->fresh()->display_order);
        $this->assertEquals(1, $m1->fresh()->display_order);

        // Delete Member Alpha
        $deleteRes = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/v1/cms/team/{$m1->uuid}");
        $deleteRes->assertStatus(200);

        $this->assertSoftDeleted('team_members', ['id' => $m1->id]);

        // Public site returns only Member Beta
        $publicRes = $this->getJson('/api/v1/website/team');
        $publicRes->assertStatus(200);
        $team = $publicRes->json('team');

        $this->assertCount(1, $team);
        $this->assertEquals('Member Beta', $team[0]['name']);
    }

    /**
     * Requirement 7 & 8: Re-running CmsSeeder does NOT restore original default team members or overwrite CMS edits.
     */
    public function test_running_cms_seeder_does_not_revert_cms_team_changes(): void
    {
        // 1. Initial fresh install seeding
        $this->seed(CmsSeeder::class);
        $initialCount = TeamMember::count();
        $this->assertGreaterThan(0, $initialCount);

        // 2. Admin edits Fatima Hayat to a new name and role
        $president = TeamMember::where('role', 'President')->first();
        $this->assertNotNull($president);

        $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/v1/cms/team/{$president->uuid}", [
                'name' => 'Dr. Fatima H. Al-Sayed',
                'role' => 'Executive President & Founder',
                'dept' => 'President',
                'status' => 'published',
            ])->assertStatus(200);

        // 3. Admin deletes one of the coordinators
        $coordinator = TeamMember::where('role', 'IT Coordinator')->first();
        $this->assertNotNull($coordinator);
        $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/v1/cms/team/{$coordinator->uuid}")
            ->assertStatus(200);

        // 4. Re-run CmsSeeder (simulating test, artisan db:seed, or deploy synchronization)
        $this->seed(CmsSeeder::class);

        // 5. Assert: The edited name still exists and was NOT overwritten
        $this->assertDatabaseHas('team_members', [
            'uuid' => $president->uuid,
            'name' => 'Dr. Fatima H. Al-Sayed',
            'role' => 'Executive President & Founder',
        ]);

        // 6. Assert: The original unedited "FATIMA HAYAT" default was NOT re-inserted as a duplicate
        $fatimaOriginalCount = TeamMember::where('name', 'FATIMA HAYAT')->where('role', 'President')->count();
        $this->assertEquals(0, $fatimaOriginalCount, 'CmsSeeder re-inserted original Fatima Hayat default!');

        // 7. Assert: The deleted IT Coordinator was NOT resurrected
        $itCoordActiveCount = TeamMember::where('name', $coordinator->name)->count();
        $this->assertEquals(0, $itCoordActiveCount, 'CmsSeeder resurrected deleted team member!');

        // 8. Public website reflects the CMS edits after seeder execution
        $publicRes = $this->getJson('/api/v1/website/team');
        $publicRes->assertStatus(200);
        $team = collect($publicRes->json('team'));

        $this->assertTrue($team->contains('name', 'Dr. Fatima H. Al-Sayed'));
        $this->assertFalse($team->contains('name', 'FATIMA HAYAT'));
        $this->assertFalse($team->contains('name', $coordinator->name));
    }

    /**
     * Requirement 9: Empty-install defaults are initialized safely only when table is completely empty.
     */
    public function test_empty_install_defaults_initialized_safely_when_empty_only(): void
    {
        $this->assertEquals(0, TeamMember::count());

        // Run seeder on genuinely empty database
        $this->seed(CmsSeeder::class);

        $this->assertGreaterThan(0, TeamMember::count());
        $seededCount = TeamMember::count();

        // If admin unpublishes all members, public site returns empty array (does not revert to defaults)
        TeamMember::query()->update(['status' => 'draft']);
        Cache::forget('website_team');

        $publicRes = $this->getJson('/api/v1/website/team');
        $publicRes->assertStatus(200);
        $this->assertCount(0, $publicRes->json('team'));
    }

    /**
     * Requirement 10: Cache is properly invalidated on CMS changes.
     */
    public function test_cache_is_invalidated_immediately_on_cms_updates(): void
    {
        $member = TeamMember::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Initial Name',
            'role' => 'Lead',
            'dept' => 'Directors',
            'status' => 'published',
            'display_order' => 1,
        ]);

        // Prime cache
        $res1 = $this->getJson('/api/v1/website/team');
        $this->assertEquals('Initial Name', $res1->json('team.0.name'));
        $this->assertNotNull(Cache::get('website_team'));

        // Update via CMS
        $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/v1/cms/team/{$member->uuid}", [
                'name' => 'Brand New Name',
                'role' => 'Lead',
                'dept' => 'Directors',
                'status' => 'published',
            ])->assertStatus(200);

        // Cache must have been cleared
        $this->assertNull(Cache::get('website_team'));

        // Next fetch serves updated value
        $res2 = $this->getJson('/api/v1/website/team');
        $this->assertEquals('Brand New Name', $res2->json('team.0.name'));
    }

    /**
     * Requirement 11: Unauthorized users cannot edit CMS team content.
     */
    public function test_unauthorized_users_cannot_edit_team_content(): void
    {
        $member = TeamMember::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Protected Member',
            'role' => 'Lead',
            'dept' => 'Directors',
            'status' => 'published',
        ]);

        // Unauthenticated
        $this->postJson('/api/v1/cms/team', ['name' => 'Hacker'])->assertStatus(401);
        $this->putJson("/api/v1/cms/team/{$member->uuid}", ['name' => 'Hacker'])->assertStatus(401);
        $this->deleteJson("/api/v1/cms/team/{$member->uuid}")->assertStatus(401);

        // Authenticated without manage_team permission
        $this->actingAs($this->normalUser, 'sanctum')
            ->postJson('/api/v1/cms/team', ['name' => 'Unauthorized'])
            ->assertStatus(403);

        $this->actingAs($this->normalUser, 'sanctum')
            ->putJson("/api/v1/cms/team/{$member->uuid}", ['name' => 'Unauthorized'])
            ->assertStatus(403);

        $this->actingAs($this->normalUser, 'sanctum')
            ->deleteJson("/api/v1/cms/team/{$member->uuid}")
            ->assertStatus(403);
    }

    /**
     * Requirement: Development user seeders are idempotent and do not crash on soft-deleted users.
     */
    public function test_development_user_seeder_is_idempotent_and_handles_soft_deleted_users(): void
    {
        config(['ems.seed_development_users' => true]);

        // Pre-create soft-deleted user with the EMS admin email
        $softDeletedUser = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Old EMS Admin',
            'email' => 'ems-admin@example.test',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'is_active' => true,
        ]);
        $softDeletedUser->delete(); // Soft delete

        $this->assertTrue($softDeletedUser->trashed());

        // Run EmsDevelopmentUserSeeder
        $this->seed(\Database\Seeders\Ems\EmsRolePermissionSeeder::class);
        $this->seed(\Database\Seeders\Ems\EmsDevelopmentUserSeeder::class);

        // User must be restored, active, and have the role attached
        $restoredUser = User::where('email', 'ems-admin@example.test')->first();
        $this->assertNotNull($restoredUser);
        $this->assertFalse($restoredUser->trashed());

        // Running a second time must also pass without UniqueConstraintViolationException
        $this->seed(\Database\Seeders\Ems\EmsDevelopmentUserSeeder::class);
        $this->assertDatabaseHas('users', ['email' => 'ems-admin@example.test']);
    }
}
