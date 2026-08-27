<?php

namespace Tests\Feature\Academy;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AcademyAssetOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private User $courseManager;

    private User $cmsOnlyUser;

    private User $learner;

    protected function setUp(): void
    {
        parent::setUp();

        $manageCourses = Permission::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Manage Courses',
            'slug' => 'manage_courses',
            'module' => 'Academy',
            'description' => 'Manage courses',
        ]);

        $manageHomepage = Permission::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Manage Homepage',
            'slug' => 'manage_homepage',
            'module' => 'Website',
            'description' => 'Manage homepage',
        ]);

        $courseRole = Role::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Course Manager',
            'slug' => 'course-manager',
            'description' => 'DAMS course manager',
        ]);
        $courseRole->permissions()->attach($manageCourses);

        $cmsRole = Role::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'CMS Editor',
            'slug' => 'cms-editor',
            'description' => 'CMS only',
        ]);
        $cmsRole->permissions()->attach($manageHomepage);

        $this->courseManager = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Course Manager',
            'email' => 'courses@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $this->courseManager->roles()->attach($courseRole);

        $this->cmsOnlyUser = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'CMS Editor',
            'email' => 'cms@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $this->cmsOnlyUser->roles()->attach($cmsRole);

        $this->learner = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Volunteer',
            'email' => 'volunteer@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        // Grant explicit application access for testing independent gates
        $appAccessService = app(\App\Services\ApplicationAccessService::class);
        $appAccessService->grant($this->courseManager, 'dams');
        $appAccessService->grant($this->cmsOnlyUser, 'cms');
    }

    /** @test */
    public function course_manager_can_upload_academy_asset_without_cms_media_row(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('course-thumb.jpg', 80, 'image/jpeg');

        $response = $this->actingAs($this->courseManager)
            ->postJson(route('api.admin.academy.assets.upload'), ['file' => $file])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('owner', 'academy');

        $url = $response->json('url');
        $this->assertNotNull($url);
        $this->assertStringContainsString('uploads/academy/', $url);

        $pathParts = explode('/storage/', $url);
        Storage::disk('public')->assertExists(end($pathParts));

        $this->assertDatabaseCount('media', 0);
    }

    /** @test */
    public function cms_only_user_cannot_upload_academy_assets(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('course-thumb.jpg', 80, 'image/jpeg');

        $this->actingAs($this->cmsOnlyUser)
            ->postJson(route('api.admin.academy.assets.upload'), ['file' => $file])
            ->assertStatus(403);

        $this->assertDatabaseCount('media', 0);
    }

    /** @test */
    public function course_manager_cannot_use_cms_contextual_upload(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('banner.jpg', 80, 'image/jpeg');

        $this->actingAs($this->courseManager)
            ->postJson(route('api.admin.cms.assets.upload'), ['file' => $file])
            ->assertStatus(403);
    }

    /** @test */
    public function cms_editor_can_use_cms_contextual_upload_into_cms_namespace(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('hero.jpg', 80, 'image/jpeg');

        // manage_homepage is in the CMS assets OR chain
        $response = $this->actingAs($this->cmsOnlyUser)
            ->postJson(route('api.admin.cms.assets.upload'), ['file' => $file])
            ->assertStatus(201)
            ->assertJsonPath('owner', 'cms');

        $url = $response->json('url');
        $this->assertStringContainsString('uploads/cms/', $url);
        $this->assertDatabaseCount('media', 0);
    }

    /** @test */
    public function learner_cannot_access_cms_or_academy_admin_uploads(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('x.jpg', 40, 'image/jpeg');

        $this->actingAs($this->learner)
            ->postJson(route('api.admin.cms.assets.upload'), ['file' => $file])
            ->assertStatus(403);

        $this->actingAs($this->learner)
            ->postJson(route('api.admin.academy.assets.upload'), ['file' => $file])
            ->assertStatus(403);
    }
}
