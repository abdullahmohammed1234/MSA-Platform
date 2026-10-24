<?php

namespace Tests\Feature\Phase42;

use App\Models\CMS\Media;
use App\Models\CMS\MediaCategory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MediaAudienceSegregationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $unauthorizedUser;
    protected User $maleUser;
    protected User $femaleUser;
    protected User $unsetGenderUser;
    protected MediaCategory $category;

    protected Media $bothMedia;
    protected Media $brothersMedia;
    protected Media $sistersMedia;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        // Roles & Permissions
        Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        Role::firstOrCreate(['slug' => 'member'], ['name' => 'Member']);

        $manageMedia = Permission::firstOrCreate(['slug' => 'manage_media'], ['name' => 'Manage Media', 'module' => 'cms']);

        $this->adminUser = User::factory()->create([
            'email' => 'media-admin@sfu.ca',
            'gender' => 'male',
        ]);
        $this->adminUser->assignRole('admin');
        $this->adminUser->permissions()->syncWithoutDetaching([$manageMedia->id]);

        $this->unauthorizedUser = User::factory()->create([
            'email' => 'regular-member@sfu.ca',
            'gender' => 'male',
        ]);
        $this->unauthorizedUser->assignRole('member');

        $this->maleUser = User::factory()->create([
            'email' => 'brother@sfu.ca',
            'gender' => 'male',
        ]);
        $this->maleUser->assignRole('member');

        $this->femaleUser = User::factory()->create([
            'email' => 'sister@sfu.ca',
            'gender' => 'female',
        ]);
        $this->femaleUser->assignRole('member');

        $this->unsetGenderUser = User::factory()->create([
            'email' => 'unset@sfu.ca',
            'gender' => null,
        ]);
        $this->unsetGenderUser->assignRole('member');

        $this->category = MediaCategory::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Gatherings',
            'slug' => 'gatherings',
        ]);

        // Seed 3 media items: both, brothers, sisters
        // 1. Both (Public)
        $bothPath = 'uploads/cms/public-photo.jpg';
        Storage::disk('public')->put($bothPath, 'public-image-content');
        $this->bothMedia = Media::create([
            'uuid' => (string) Str::uuid(),
            'filename' => 'public-photo.jpg',
            'display_name' => 'Public Campus Gathering',
            'filepath' => $bothPath,
            'url' => 'http://localhost/storage/' . $bothPath,
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'audience' => 'both',
            'category_id' => $this->category->id,
            'created_at' => now()->subMinutes(10),
        ]);

        // 2. Brothers
        $brothersPath = 'private/uploads/cms/brothers-halqa.jpg';
        Storage::disk('local')->put($brothersPath, 'brothers-image-content');
        $brothersUuid = (string) Str::uuid();
        $this->brothersMedia = Media::create([
            'uuid' => $brothersUuid,
            'filename' => 'brothers-halqa.jpg',
            'display_name' => 'Brothers Halqa',
            'filepath' => $brothersPath,
            'url' => "/api/v1/website/media/{$brothersUuid}/file",
            'mime_type' => 'image/jpeg',
            'size' => 2048,
            'audience' => 'brothers',
            'category_id' => $this->category->id,
            'created_at' => now()->subMinutes(5),
        ]);

        // 3. Sisters
        $sistersPath = 'private/uploads/cms/sisters-tea.jpg';
        Storage::disk('local')->put($sistersPath, 'sisters-image-content');
        $sistersUuid = (string) Str::uuid();
        $this->sistersMedia = Media::create([
            'uuid' => $sistersUuid,
            'filename' => 'sisters-tea.jpg',
            'display_name' => 'Sisters Tea Social',
            'filepath' => $sistersPath,
            'url' => "/api/v1/website/media/{$sistersUuid}/file",
            'mime_type' => 'image/jpeg',
            'size' => 3072,
            'audience' => 'sisters',
            'category_id' => $this->category->id,
            'created_at' => now()->subMinutes(1),
        ]);
    }

    /**
     * 1. The 12-case Media Audience Matrix
     *
     * Viewer \ Audience   | brothers | sisters | both
     * --------------------|----------|---------|------
     * Guest (logged out)  | denied   | denied  | allowed
     * Male                | allowed  | denied  | allowed
     * Female              | denied   | allowed | allowed
     * Unset gender (null) | denied   | denied  | allowed
     */

    public function test_guest_media_audience_matrix(): void
    {
        // Public list
        $response = $this->getJson('/api/v1/website/media');
        $response->assertStatus(200);
        $titles = collect($response->json('media'))->pluck('title')->all();

        $this->assertContains('Public Campus Gathering', $titles);
        $this->assertNotContains('Brothers Halqa', $titles);
        $this->assertNotContains('Sisters Tea Social', $titles);

        // Streaming: Guest + both -> allowed (200)
        $streamBoth = $this->getJson("/api/v1/website/media/{$this->bothMedia->uuid}/file");
        $streamBoth->assertStatus(200);

        // Streaming: Guest + brothers -> denied (403)
        $this->getJson("/api/v1/website/media/{$this->brothersMedia->uuid}/file")->assertStatus(403);

        // Streaming: Guest + sisters -> denied (403)
        $this->getJson("/api/v1/website/media/{$this->sistersMedia->uuid}/file")->assertStatus(403);
    }

    public function test_male_user_media_audience_matrix(): void
    {
        Sanctum::actingAs($this->maleUser);

        // Media listing
        $response = $this->getJson('/api/v1/website/media');
        $response->assertStatus(200);
        $titles = collect($response->json('media'))->pluck('title')->all();

        $this->assertContains('Public Campus Gathering', $titles);
        $this->assertContains('Brothers Halqa', $titles);
        $this->assertNotContains('Sisters Tea Social', $titles);

        // Streaming: Male + both -> allowed (200)
        $this->getJson("/api/v1/website/media/{$this->bothMedia->uuid}/file")->assertStatus(200);

        // Streaming: Male + brothers -> allowed (200)
        $streamBrothers = $this->getJson("/api/v1/website/media/{$this->brothersMedia->uuid}/file");
        $streamBrothers->assertStatus(200);
        $this->assertStringContainsString('private', $streamBrothers->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $streamBrothers->headers->get('Cache-Control'));

        // Streaming: Male + sisters -> denied (403)
        $this->getJson("/api/v1/website/media/{$this->sistersMedia->uuid}/file")->assertStatus(403);
    }

    public function test_female_user_media_audience_matrix(): void
    {
        Sanctum::actingAs($this->femaleUser);

        // Media listing
        $response = $this->getJson('/api/v1/website/media');
        $response->assertStatus(200);
        $titles = collect($response->json('media'))->pluck('title')->all();

        $this->assertContains('Public Campus Gathering', $titles);
        $this->assertContains('Sisters Tea Social', $titles);
        $this->assertNotContains('Brothers Halqa', $titles);

        // Streaming: Female + both -> allowed (200)
        $this->getJson("/api/v1/website/media/{$this->bothMedia->uuid}/file")->assertStatus(200);

        // Streaming: Female + sisters -> allowed (200)
        $streamSisters = $this->getJson("/api/v1/website/media/{$this->sistersMedia->uuid}/file");
        $streamSisters->assertStatus(200);
        $this->assertStringContainsString('private', $streamSisters->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $streamSisters->headers->get('Cache-Control'));

        // Streaming: Female + brothers -> denied (403)
        $this->getJson("/api/v1/website/media/{$this->brothersMedia->uuid}/file")->assertStatus(403);
    }

    public function test_unset_gender_user_media_audience_matrix(): void
    {
        Sanctum::actingAs($this->unsetGenderUser);

        // Media listing
        $response = $this->getJson('/api/v1/website/media');
        $response->assertStatus(200);
        $titles = collect($response->json('media'))->pluck('title')->all();

        $this->assertContains('Public Campus Gathering', $titles);
        $this->assertNotContains('Brothers Halqa', $titles);
        $this->assertNotContains('Sisters Tea Social', $titles);

        // Streaming: Unset + both -> allowed (200)
        $this->getJson("/api/v1/website/media/{$this->bothMedia->uuid}/file")->assertStatus(200);

        // Streaming: Unset + brothers -> denied (403)
        $this->getJson("/api/v1/website/media/{$this->brothersMedia->uuid}/file")->assertStatus(403);

        // Streaming: Unset + sisters -> denied (403)
        $this->getJson("/api/v1/website/media/{$this->sistersMedia->uuid}/file")->assertStatus(403);
    }

    /**
     * 2. Admin Upload Validation for Audience Values
     */
    public function test_admin_upload_validates_required_audience(): void
    {
        Sanctum::actingAs($this->adminUser);

        $fakeFile = UploadedFile::fake()->image('test-photo.jpg', 600, 400);

        // Missing audience -> 422
        $resMissing = $this->postJson('/api/v1/cms/media', [
            'file' => $fakeFile,
            'display_name' => 'No Audience Provided',
        ]);
        $resMissing->assertStatus(422)->assertJsonValidationErrors(['audience']);

        // Invalid audience -> 422
        $resInvalid = $this->postJson('/api/v1/cms/media', [
            'file' => $fakeFile,
            'audience' => 'everyone',
        ]);
        $resInvalid->assertStatus(422)->assertJsonValidationErrors(['audience']);

        // Valid canonical choices: both, brothers, sisters
        $resBrothers = $this->postJson('/api/v1/cms/media', [
            'file' => UploadedFile::fake()->image('brothers-upload.jpg', 600, 400),
            'display_name' => 'Brothers Only Photo',
            'audience' => 'brothers',
        ]);
        $resBrothers->assertStatus(201);
        $createdBrothers = Media::where('uuid', $resBrothers->json('media.uuid'))->firstOrFail();
        $this->assertEquals('brothers', $createdBrothers->audience);

        // Restricted asset MUST be stored on local disk under private/uploads/cms
        $this->assertTrue(Storage::disk('local')->exists($createdBrothers->filepath));
        $this->assertStringStartsWith('private/uploads/cms', $createdBrothers->filepath);
        // Direct storage URL must NOT be in public disk
        $this->assertFalse(Storage::disk('public')->exists($createdBrothers->filepath));
    }

    /**
     * 3. Admin Update Validation and Authorization
     */
    public function test_unauthorized_user_cannot_update_media(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);

        $response = $this->putJson("/api/v1/cms/media/{$this->bothMedia->uuid}", [
            'display_name' => 'Hacked Name',
            'audience' => 'brothers',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_update_media_and_storage_relocates_safely(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Initial state: bothMedia is on 'public' disk
        $this->assertTrue(Storage::disk('public')->exists($this->bothMedia->filepath));
        $this->assertFalse(Storage::disk('local')->exists('private/uploads/cms/' . basename($this->bothMedia->filepath)));

        // Update audience from 'both' to 'sisters'
        $response = $this->putJson("/api/v1/cms/media/{$this->bothMedia->uuid}", [
            'display_name' => 'Sisters Gala Night',
            'audience' => 'sisters',
        ]);

        $response->assertStatus(200);
        $updated = $this->bothMedia->fresh();
        $this->assertEquals('sisters', $updated->audience);
        $this->assertEquals('Sisters Gala Night', $updated->display_name);

        // File should have been moved from 'public' disk to 'local' protected disk!
        $this->assertTrue(Storage::disk('local')->exists($updated->filepath));
        $this->assertStringStartsWith('private/uploads/cms', $updated->filepath);
        $this->assertFalse(Storage::disk('public')->exists($this->bothMedia->filepath));

        // Now reverse: update from 'sisters' to 'both'
        $resRevert = $this->putJson("/api/v1/cms/media/{$this->bothMedia->uuid}", [
            'display_name' => 'Public Gala Night',
            'audience' => 'both',
        ]);

        $resRevert->assertStatus(200);
        $reverted = $this->bothMedia->fresh();
        $this->assertEquals('both', $reverted->audience);
        // File moved back to 'public' disk
        $this->assertTrue(Storage::disk('public')->exists($reverted->filepath));
        $this->assertStringStartsWith('uploads/cms', $reverted->filepath);
        $this->assertFalse(Storage::disk('local')->exists($updated->filepath));
    }

    /**
     * 4. Admin Management Preview Bypass
     */
    public function test_admin_with_manage_media_permission_can_preview_restricted_assets(): void
    {
        // Admin user is male, but can stream sisters media because of manage_media permission
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson("/api/v1/website/media/{$this->sistersMedia->uuid}/file");
        $response->assertStatus(200);
    }
}
