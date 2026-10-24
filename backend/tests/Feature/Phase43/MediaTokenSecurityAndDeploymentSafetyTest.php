<?php

namespace Tests\Feature\Phase43;

use App\Models\CMS\Media;
use App\Models\CMS\MediaCategory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Repositories\CMS\MediaRepository;
use App\Services\CMS\MediaService;
use App\Services\CMS\RevisionService;
use App\Store\Models\StoreProduct;
use App\Store\Models\StoreProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MediaTokenSecurityAndDeploymentSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected User $maleUser;
    protected User $femaleUser;
    protected User $adminUser;
    protected MediaCategory $category;
    protected Media $brothersMedia;
    protected Media $bothMedia;
    protected string $malePlainTextToken;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        Role::firstOrCreate(['slug' => 'member'], ['name' => 'Member']);

        $manageMedia = Permission::firstOrCreate(['slug' => 'manage_media'], ['name' => 'Manage Media', 'module' => 'cms']);

        $this->maleUser = User::factory()->create([
            'email' => 'brother@sfu.ca',
            'gender' => 'male',
        ]);
        $this->maleUser->assignRole('member');
        $this->malePlainTextToken = $this->maleUser->createToken('test_token')->plainTextToken;

        $this->femaleUser = User::factory()->create([
            'email' => 'sister@sfu.ca',
            'gender' => 'female',
        ]);
        $this->femaleUser->assignRole('member');

        $this->adminUser = User::factory()->create([
            'email' => 'admin@sfu.ca',
            'gender' => 'male',
        ]);
        $this->adminUser->assignRole('admin');
        $this->adminUser->permissions()->syncWithoutDetaching([$manageMedia->id]);

        $this->category = MediaCategory::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Campus',
            'slug' => 'campus',
        ]);

        $bothPath = 'uploads/cms/public-campus.jpg';
        Storage::disk('public')->put($bothPath, 'public-file-content');
        $this->bothMedia = Media::create([
            'uuid' => (string) Str::uuid(),
            'filename' => 'public-campus.jpg',
            'display_name' => 'Public Campus',
            'filepath' => $bothPath,
            'url' => 'http://localhost/storage/' . $bothPath,
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'audience' => 'both',
            'category_id' => $this->category->id,
        ]);

        $brothersPath = 'private/uploads/cms/brothers-event.jpg';
        Storage::disk('local')->put($brothersPath, 'brothers-file-content');
        $brothersUuid = (string) Str::uuid();
        $this->brothersMedia = Media::create([
            'uuid' => $brothersUuid,
            'filename' => 'brothers-event.jpg',
            'display_name' => 'Brothers Halqa Event',
            'filepath' => $brothersPath,
            'url' => "/api/v1/website/media/{$brothersUuid}/file",
            'mime_type' => 'image/jpeg',
            'size' => 2048,
            'audience' => 'brothers',
            'category_id' => $this->category->id,
        ]);
    }

    /**
     * 1. Query-string bearer tokens MUST be rejected.
     */
    public function test_query_token_is_rejected_for_restricted_media_streaming(): void
    {
        // Unauthenticated request passing token in query string
        $response = $this->getJson("/api/v1/website/media/{$this->brothersMedia->uuid}/file?token={$this->malePlainTextToken}");

        // Must be rejected as 403 Forbidden because query-tokens are no longer accepted!
        $response->assertStatus(403);
    }

    public function test_query_token_is_rejected_for_website_media_listing(): void
    {
        // Passing token in query parameter does not authorize restricted media in public listing
        $response = $this->getJson("/api/v1/website/media?token={$this->malePlainTextToken}");
        $response->assertStatus(200);

        $titles = collect($response->json('media'))->pluck('title')->all();
        $this->assertContains('Public Campus', $titles);
        $this->assertNotContains('Brothers Halqa Event', $titles);
    }

    public function test_query_token_is_rejected_for_store_merchandise_streaming(): void
    {
        $product = StoreProduct::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'MSA Hoodie',
            'slug' => 'msa-hoodie',
            'sku' => 'HOODIE-01',
            'price_cents' => 4500,
            'currency' => 'CAD',
            'status' => 'active',
            'is_merchandise' => true,
        ]);

        $brotherImg = StoreProductImage::create([
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'image_type' => 'brother',
            'slot_position' => 'front',
            'is_private' => true,
            'image_url' => 'http://localhost/api/v1/store/products/' . $product->uuid . '/images/brother.jpg',
            'image_path' => 'private/store/brother.jpg',
        ]);
        Storage::disk('local')->put('private/store/brother.jpg', 'img-data');

        // Query token attempt
        $res = $this->getJson("/api/v1/store/products/{$product->uuid}/images/{$brotherImg->uuid}?token={$this->malePlainTextToken}");
        $res->assertStatus(403);
    }

    /**
     * 2. Header-based bearer authentication MUST succeed.
     */
    public function test_bearer_token_in_authorization_header_is_accepted_for_restricted_media(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->malePlainTextToken)
            ->get("/api/v1/website/media/{$this->brothersMedia->uuid}/file");

        $response->assertStatus(200);
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    /**
     * 3. Transition between restricted audiences (brothers -> sisters) stays on private disk.
     */
    public function test_brothers_to_sisters_transition_preserves_private_storage(): void
    {
        Sanctum::actingAs($this->adminUser);

        $this->assertTrue(Storage::disk('local')->exists($this->brothersMedia->filepath));

        $response = $this->putJson("/api/v1/cms/media/{$this->brothersMedia->uuid}", [
            'display_name' => 'Converted to Sisters Event',
            'audience' => 'sisters',
        ]);

        $response->assertStatus(200);
        $updated = $this->brothersMedia->fresh();
        $this->assertEquals('sisters', $updated->audience);
        // File remains in local disk
        $this->assertTrue(Storage::disk('local')->exists($updated->filepath));
        $this->assertFalse(Storage::disk('public')->exists($updated->filepath));
    }

    /**
     * 4. Audit media storage command detects exposed restricted files and fixes them.
     */
    public function test_media_storage_audit_command_dry_run_and_fix(): void
    {
        // Intentionally create a legacy restricted media record on the public disk!
        $exposedPath = 'uploads/cms/legacy-sister-photo.jpg';
        Storage::disk('public')->put($exposedPath, 'legacy-exposed-data');
        $exposedMedia = Media::create([
            'uuid' => (string) Str::uuid(),
            'filename' => 'legacy-sister-photo.jpg',
            'display_name' => 'Legacy Sister Photo',
            'filepath' => $exposedPath,
            'url' => 'http://localhost/storage/' . $exposedPath,
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'audience' => 'sisters',
            'category_id' => $this->category->id,
        ]);

        // 1. Dry-run audit: detects exposed file
        $this->artisan('cms:audit-media-storage', ['--dry-run' => true])
            ->expectsOutputToContain('Scanning CMS media storage isolation...')
            ->expectsOutputToContain('EXPOSED_PUBLIC')
            ->assertExitCode(1);

        // File is still on public disk
        $this->assertTrue(Storage::disk('public')->exists($exposedPath));

        // 2. Fix audit: remediates and relocates
        $this->artisan('cms:audit-media-storage', ['--fix' => true])
            ->expectsOutputToContain('REMEDIATED (Moved to private)')
            ->assertExitCode(0);

        // Verify file was moved to local disk and removed from public disk!
        $refreshed = $exposedMedia->fresh();
        $this->assertTrue(Storage::disk('local')->exists($refreshed->filepath));
        $this->assertFalse(Storage::disk('public')->exists($exposedPath));
    }

    /**
     * 5. File relocation failure compensation: if DB update fails, target file is cleaned up and source is preserved.
     */
    public function test_file_relocation_compensates_when_database_fails(): void
    {
        Sanctum::actingAs($this->adminUser);

        $sourcePath = $this->bothMedia->filepath;
        $this->assertTrue(Storage::disk('public')->exists($sourcePath));

        // Mock MediaRepository::update to throw an exception to simulate DB crash
        $mockRepo = $this->createMock(MediaRepository::class);
        $mockRepo->expects($this->once())
            ->method('update')
            ->willThrowException(new \RuntimeException('Simulated Database Deadlock'));

        $service = new MediaService($mockRepo, app(RevisionService::class));

        try {
            $service->update($this->bothMedia, $this->adminUser->id, ['audience' => 'sisters']);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertEquals('Simulated Database Deadlock', $e->getMessage());
        }

        // Compensation check:
        // 1. Source file on public disk MUST STILL EXIST!
        $this->assertTrue(Storage::disk('public')->exists($sourcePath));

        // 2. Target file on local disk MUST HAVE BEEN CLEANED UP!
        $targetPath = 'private/uploads/cms/' . basename($sourcePath);
        $this->assertFalse(Storage::disk('local')->exists($targetPath));
    }
}
