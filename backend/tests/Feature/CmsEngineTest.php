<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\CMS\Announcement;
use App\Models\CMS\Event;
use App\Models\CMS\TeamMember;
use App\Models\CMS\Resource;
use App\Models\CMS\HomepageSection;
use App\Models\CMS\CmsRevision;
use App\Models\CMS\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsEngineTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $normalUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup default roles and permissions
        $adminRole = Role::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'System administrator',
        ]);

        $permissions = [
            'manage_homepage',
            'manage_announcements',
            'manage_events',
            'manage_team',
            'manage_resources',
            'manage_media',
            'view_analytics'
        ];

        foreach ($permissions as $perm) {
            $p = Permission::create([
                'uuid' => (string) Str::uuid(),
                'name' => ucfirst(str_replace('_', ' ', $perm)),
                'slug' => $perm,
                'module' => 'Website',
                'description' => 'Permission ' . $perm,
            ]);
            $adminRole->permissions()->attach($p);
        }

        // 2. Create users
        $this->adminUser = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($adminRole);

        $this->normalUser = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Normal User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    /** @test */
    public function guests_and_normal_users_cannot_access_cms_admin_dashboard()
    {
        $this->getJson(route('api.admin.cms.dashboard'))
            ->assertStatus(401);

        $this->actingAs($this->normalUser)
            ->getJson(route('api.admin.cms.dashboard'))
            ->assertStatus(403);
    }

    /** @test */
    public function admins_can_access_cms_admin_dashboard_statistics()
    {
        $this->actingAs($this->adminUser)
            ->getJson(route('api.admin.cms.dashboard'))
            ->assertStatus(200)
            ->assertJsonStructure([
                'stats' => ['announcements', 'events', 'team', 'resources'],
                'recentLogs'
            ]);
    }

    /** @test */
    public function admin_can_create_announcement_which_stores_a_revision()
    {
        $data = [
            'title' => 'Important Community Update',
            'content' => '<p>This is rich text content.</p>',
            'summary' => 'Short summary tagline',
            'featured_image' => 'https://images.unsplash.com/photo-1519751138087-5bf79df62d5b',
            'status' => 'draft',
        ];

        $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.cms.announcements.store'), $data)
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('announcements', [
            'title' => 'Important Community Update',
            'status' => 'draft',
        ]);

        $announcement = Announcement::first();
        $this->assertDatabaseHas('cms_revisions', [
            'revisable_type' => Announcement::class,
            'revisable_id' => $announcement->id,
            'version' => 1,
        ]);
    }

    /** @test */
    public function draft_announcements_do_not_appear_publicly_but_published_ones_do()
    {
        // 1. Create a draft and a published announcement
        Announcement::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Draft Announcement',
            'slug' => 'draft-announcement',
            'content' => 'Content',
            'status' => 'draft',
        ]);

        Announcement::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Published Announcement',
            'slug' => 'published-announcement',
            'content' => 'Content',
            'status' => 'published',
            'published_at' => now(),
        ]);

        // 2. Fetch public endpoint
        $response = $this->getJson(route('api.website.announcements'))
            ->assertStatus(200);

        $announcements = $response->json('announcements');
        
        $this->assertCount(1, $announcements);
        $this->assertEquals('Published Announcement', $announcements[0]['title']);
    }

    /** @test */
    public function admin_can_rollback_announcement_to_previous_version()
    {
        // 1. Create announcement (Version 1)
        $ann = Announcement::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Original Title',
            'slug' => 'original-title',
            'content' => 'Original Content',
            'status' => 'draft',
        ]);

        CmsRevision::create([
            'revisable_type' => Announcement::class,
            'revisable_id' => $ann->id,
            'user_id' => $this->adminUser->id,
            'content' => [
                'title' => 'Original Title',
                'slug' => 'original-title',
                'content' => 'Original Content',
                'status' => 'draft',
            ],
            'version' => 1,
        ]);

        // 2. Update it (Version 2)
        $ann->update([
            'title' => 'Modified Title',
            'content' => 'Modified Content',
        ]);

        CmsRevision::create([
            'revisable_type' => Announcement::class,
            'revisable_id' => $ann->id,
            'user_id' => $this->adminUser->id,
            'content' => [
                'title' => 'Modified Title',
                'slug' => 'modified-title',
                'content' => 'Modified Content',
                'status' => 'draft',
            ],
            'version' => 2,
        ]);

        // 3. Rollback to Version 1
        $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.cms.announcements.rollback', $ann->uuid), ['version' => 1])
            ->assertStatus(200);

        $this->assertEquals('Original Title', $ann->fresh()->title);
        $this->assertEquals('Original Content', $ann->fresh()->content);
    }

    /** @test */
    public function admin_can_upload_and_delete_media()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('campus-flyer.pdf', 100, 'application/pdf');

        // 1. Upload
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.cms.media.store'), [
                'file' => $file
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $uuid = $response->json('media.uuid');
        $filepath = $response->json('media.filepath');

        $this->assertDatabaseHas('media', [
            'uuid' => $uuid,
            'filename' => 'campus-flyer.pdf',
        ]);

        Storage::disk('public')->assertExists($filepath);

        // 2. Delete
        $this->actingAs($this->adminUser)
            ->deleteJson(route('api.admin.cms.media.destroy', $uuid))
            ->assertStatus(200);

        $this->assertDatabaseMissing('media', [
            'uuid' => $uuid,
        ]);

        Storage::disk('public')->assertMissing($filepath);
    }

    /** @test */
    public function admin_can_upload_team_photo_without_creating_media_record()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('team-member.jpg', 100, 'image/jpeg');

        // 1. Unauthorized user cannot upload
        $this->postJson(route('api.admin.cms.team.upload'), [
            'file' => $file
        ])->assertStatus(401);

        $this->actingAs($this->normalUser)
            ->postJson(route('api.admin.cms.team.upload'), [
                'file' => $file
            ])->assertStatus(403);

        // 2. Admin with manage_team permission can upload
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.cms.team.upload'), [
                'file' => $file
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $url = $response->json('url');
        $this->assertNotNull($url);

        // Extract filepath relative to storage/app/public
        // e.g. URL is like http://localhost/storage/team/team-member-12345.jpg
        $pathParts = explode('/storage/', $url);
        $filepath = end($pathParts);

        Storage::disk('public')->assertExists($filepath);

        // 3. Verify NO database record is created in the media table
        $this->assertDatabaseMissing('media', [
            'filename' => 'team-member.jpg',
        ]);
    }
}
