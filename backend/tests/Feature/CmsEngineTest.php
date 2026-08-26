<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\CMS\Announcement;
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
                'stats' => ['announcements', 'team', 'resources'],
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
        $filepath = Media::where('uuid', $uuid)->value('filepath');

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
    public function admin_can_upload_image_with_custom_name_and_category()
    {
        Storage::fake('public');

        $categoryResponse = $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.cms.media.categories.store'), [
                'name' => 'Welcome Night',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $categoryId = $categoryResponse->json('category.id');

        $file = UploadedFile::fake()->create('IMG_4832.jpg', 200, 'image/jpeg');

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.cms.media.store'), [
                'file' => $file,
                'display_name' => 'MSA Welcome Night 2026',
                'category_id' => $categoryId,
            ])
            ->assertStatus(201)
            ->assertJsonPath('media.display_name', 'MSA Welcome Night 2026')
            ->assertJsonPath('media.media_type', 'image')
            ->assertJsonPath('media.category.id', $categoryId)
            ->assertJsonPath('media.filename', 'IMG_4832.jpg');

        $this->assertDatabaseHas('media', [
            'uuid' => $response->json('media.uuid'),
            'display_name' => 'MSA Welcome Night 2026',
            'category_id' => $categoryId,
            'filename' => 'IMG_4832.jpg',
        ]);
    }

    /** @test */
    public function display_name_cannot_influence_stored_filepath()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.cms.media.store'), [
                'file' => $file,
                'display_name' => 'Test/../../../file',
            ])
            ->assertStatus(201);

        $media = Media::where('uuid', $response->json('media.uuid'))->first();

        $this->assertSame('Test/../../../file', $media->display_name);
        $this->assertSame('photo.jpg', $media->filename);
        $this->assertStringStartsWith('uploads/', $media->filepath);
        $this->assertStringNotContainsString('..', $media->filepath);
        $this->assertStringNotContainsString('Test/', $media->filepath);
        $this->assertNull($response->json('media.filepath'));
    }

    /** @test */
    public function empty_display_name_is_stored_as_null_and_special_names_are_accepted()
    {
        Storage::fake('public');

        $empty = UploadedFile::fake()->create('raw.jpg', 50, 'image/jpeg');
        $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.cms.media.store'), [
                'file' => $empty,
                'display_name' => '   ',
            ])
            ->assertStatus(201)
            ->assertJsonPath('media.display_name', null)
            ->assertJsonPath('media.filename', 'raw.jpg');

        $special = UploadedFile::fake()->create('event.jpg', 50, 'image/jpeg');
        $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.cms.media.store'), [
                'file' => $special,
                'display_name' => 'Jummah & Community Event',
            ])
            ->assertStatus(201)
            ->assertJsonPath('media.display_name', 'Jummah & Community Event');

        $arabic = UploadedFile::fake()->create('arabic.jpg', 50, 'image/jpeg');
        $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.cms.media.store'), [
                'file' => $arabic,
                'display_name' => 'ليلة الترحيب',
            ])
            ->assertStatus(201)
            ->assertJsonPath('media.display_name', 'ليلة الترحيب');

        $tooLong = UploadedFile::fake()->create('long.jpg', 50, 'image/jpeg');
        $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.cms.media.store'), [
                'file' => $tooLong,
                'display_name' => str_repeat('A', 256),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['display_name']);
    }

    /** @test */
    public function admin_can_upload_supported_video_formats_and_rejects_invalid_videos()
    {
        Storage::fake('public');

        foreach ([
            'clip.mp4' => 'video/mp4',
            'clip.webm' => 'video/webm',
            'clip.mov' => 'video/quicktime',
            'iphone.mov' => 'video/x-quicktime',
            'hevc.mov' => 'video/x-m4v',
            'generic.mov' => 'application/octet-stream',
            'clip.ogv' => 'video/ogg',
        ] as $name => $mime) {
            $this->actingAs($this->adminUser)
                ->postJson(route('api.admin.cms.media.store'), [
                    'file' => UploadedFile::fake()->create($name, 500, $mime),
                ])
                ->assertStatus(201)
                ->assertJsonPath('media.media_type', 'video');
        }

        $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.cms.media.store'), [
                'file' => UploadedFile::fake()->create('clip.avi', 500, 'video/x-msvideo'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $oversizeKb = ((int) config('cms.media.max_video_kb', 51200)) + 1;
        $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.cms.media.store'), [
                'file' => UploadedFile::fake()->create('huge.mp4', $oversizeKb, 'video/mp4'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    /** @test */
    public function public_media_api_supports_legacy_rows_and_assigned_categories()
    {
        Storage::fake('public');

        $legacy = Media::create([
            'uuid' => (string) Str::uuid(),
            'filename' => 'legacy_photo.jpg',
            'display_name' => null,
            'category_id' => null,
            'filepath' => 'uploads/legacy_photo.jpg',
            'url' => '/storage/uploads/legacy_photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1234,
            'uploaded_by' => $this->adminUser->id,
        ]);

        $category = \App\Models\CMS\MediaCategory::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Fundraiser',
            'slug' => 'fundraiser',
        ]);

        $categorized = Media::create([
            'uuid' => (string) Str::uuid(),
            'filename' => 'IMG_1.jpg',
            'display_name' => 'MSA Welcome Night 2026',
            'category_id' => $category->id,
            'filepath' => 'uploads/img1.jpg',
            'url' => '/storage/uploads/img1.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 2345,
            'uploaded_by' => $this->adminUser->id,
        ]);

        $video = Media::create([
            'uuid' => (string) Str::uuid(),
            'filename' => 'ignore.mp4',
            'display_name' => 'Not In Gallery',
            'category_id' => $category->id,
            'filepath' => 'uploads/ignore.mp4',
            'url' => '/storage/uploads/ignore.mp4',
            'mime_type' => 'video/mp4',
            'size' => 999,
            'uploaded_by' => $this->adminUser->id,
        ]);

        $response = $this->getJson(route('api.website.media'))
            ->assertStatus(200);

        $media = collect($response->json('media'));

        $legacyItem = $media->firstWhere('id', $legacy->uuid);
        $this->assertNotNull($legacyItem);
        $this->assertSame('Community', $legacyItem['category']);
        $this->assertSame('Legacy Photo', $legacyItem['title']);

        $categorizedItem = $media->firstWhere('id', $categorized->uuid);
        $this->assertNotNull($categorizedItem);
        $this->assertSame('Fundraiser', $categorizedItem['category']);
        $this->assertSame('MSA Welcome Night 2026', $categorizedItem['title']);
        $this->assertSame('image', $categorizedItem['media_type']);

        $videoItem = $media->firstWhere('id', $video->uuid);
        $this->assertNotNull($videoItem);
        $this->assertSame('video', $videoItem['media_type']);
        $this->assertSame('Not In Gallery', $videoItem['title']);
    }

    /** @test */
    public function admin_can_upload_video_media()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('welcome.mp4', 1024, 'video/mp4');

        $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.cms.media.store'), [
                'file' => $file,
                'display_name' => 'Welcome Video',
            ])
            ->assertStatus(201)
            ->assertJsonPath('media.media_type', 'video')
            ->assertJsonPath('media.display_name', 'Welcome Video');

        $this->assertDatabaseHas('media', [
            'filename' => 'welcome.mp4',
            'display_name' => 'Welcome Video',
        ]);
    }

    /** @test */
    public function uploaded_media_stores_concrete_image_mime_for_previews()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('campus.jpg', 120, 'image/jpeg');

        $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.cms.media.store'), [
                'file' => $file,
            ])
            ->assertStatus(201)
            ->assertJsonPath('media.media_type', 'image');

        $mime = Media::where('filename', 'campus.jpg')->value('mime_type');
        $this->assertNotNull($mime);
        $this->assertTrue(
            str_starts_with((string) $mime, 'image/'),
            "Expected image/* mime for preview rendering, got: {$mime}"
        );
    }

    /** @test */
    public function admin_can_list_and_create_media_categories()
    {
        $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.cms.media.categories.store'), [
                'name' => 'Community',
            ])
            ->assertStatus(201);

        $this->actingAs($this->adminUser)
            ->getJson(route('api.admin.cms.media.categories.index'))
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Community']);

        $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.cms.media.categories.store'), [
                'name' => 'Community',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function unauthorized_users_cannot_manage_media_or_categories()
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg');

        $this->postJson(route('api.admin.cms.media.store'), [
            'file' => $file,
        ])->assertStatus(401);

        $this->actingAs($this->normalUser)
            ->postJson(route('api.admin.cms.media.store'), [
                'file' => $file,
            ])->assertStatus(403);

        $this->actingAs($this->normalUser)
            ->postJson(route('api.admin.cms.media.categories.store'), [
                'name' => 'Secret',
            ])->assertStatus(403);

        $this->actingAs($this->normalUser)
            ->getJson(route('api.admin.cms.media.categories.index'))
            ->assertStatus(403);
    }

    /** @test */
    public function media_upload_rejects_unsupported_file_types()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');

        $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.cms.media.store'), [
                'file' => $file,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
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

    /** @test */
    public function contextual_asset_upload_does_not_create_media_library_record()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('event-banner.jpg', 100, 'image/jpeg');

        $this->postJson(route('api.admin.cms.assets.upload'), [
            'file' => $file,
        ])->assertStatus(401);

        $this->actingAs($this->normalUser)
            ->postJson(route('api.admin.cms.assets.upload'), [
                'file' => $file,
            ])
            ->assertStatus(403);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.cms.assets.upload'), [
                'file' => $file,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $url = $response->json('url');
        $this->assertNotNull($url);
        $this->assertNull($response->json('media'));

        $pathParts = explode('/storage/', $url);
        $filepath = end($pathParts);
        Storage::disk('public')->assertExists($filepath);
        $this->assertStringContainsString('uploads/cms/', $filepath);

        $this->assertDatabaseMissing('media', [
            'filename' => 'event-banner.jpg',
        ]);
        $this->assertDatabaseCount('media', 0);
    }

    /** @test */
    public function media_library_upload_still_creates_media_record()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('gallery-photo.jpg', 100, 'image/jpeg');

        $this->actingAs($this->adminUser)
            ->postJson(route('api.admin.cms.media.store'), [
                'file' => $file,
                'display_name' => 'Gallery Photo',
            ])
            ->assertStatus(201)
            ->assertJsonPath('media.display_name', 'Gallery Photo');

        $this->assertDatabaseHas('media', [
            'filename' => 'gallery-photo.jpg',
            'display_name' => 'Gallery Photo',
        ]);
    }
}
