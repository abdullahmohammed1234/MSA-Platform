<?php

namespace Tests\Feature\Phase41B;

use App\Ems\Enums\EventStatus;
use App\Ems\Models\Event;
use App\Models\CMS\Media;
use App\Models\Role;
use App\Models\User;
use App\Store\Enums\ProductStatus;
use App\Store\Models\StoreProduct;
use App\Store\Models\StoreProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase41BFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $maleUser;
    protected User $femaleUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        Role::firstOrCreate(['slug' => 'member'], ['name' => 'Member']);

        $this->adminUser = User::factory()->create([
            'email' => 'admin@sfu.ca',
            'gender' => 'male',
        ]);
        $this->adminUser->assignRole('admin');

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
    }

    /**
     * 1. Profile Gender Management
     */
    public function test_user_can_update_profile_gender(): void
    {
        $user = User::factory()->create([
            'email' => 'unset@sfu.ca',
            'gender' => null,
        ]);

        Sanctum::actingAs($user);

        // Update with valid gender
        $response = $this->putJson('/api/v1/users/profile', [
            'name' => $user->name,
            'gender' => 'female',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('female', $user->fresh()->gender);

        // Invalid gender is rejected
        $badResponse = $this->putJson('/api/v1/users/profile', [
            'name' => $user->name,
            'gender' => 'invalid_gender',
        ]);
        $badResponse->assertStatus(422)
            ->assertJsonValidationErrors(['gender']);
    }

    /**
     * 2. Event Audience Restrictions
     */
    public function test_event_audience_restrictions_enforced(): void
    {
        $maleOnlyEvent = new Event([
            'name' => 'Brothers Halqa',
            'slug' => 'brothers-halqa',
            'is_public' => true,
            'audience' => 'male',
            'start_at' => now()->addDays(5),
            'capacity' => 50,
        ]);
        $maleOnlyEvent->status = EventStatus::Published;
        $maleOnlyEvent->save();

        $femaleOnlyEvent = new Event([
            'name' => 'Sisters Gathering',
            'slug' => 'sisters-gathering',
            'is_public' => true,
            'audience' => 'female',
            'start_at' => now()->addDays(6),
            'capacity' => 50,
        ]);
        $femaleOnlyEvent->status = EventStatus::Published;
        $femaleOnlyEvent->save();

        // Unauthenticated guest discovery
        $guestEvents = $this->getJson('/api/v1/ems/public/events');
        $guestEvents->assertStatus(200);
        $guestSlugs = collect($guestEvents->json('data'))->pluck('slug')->all();
        $this->assertNotContains('brothers-halqa', $guestSlugs);
        $this->assertNotContains('sisters-gathering', $guestSlugs);

        // Male user access
        Sanctum::actingAs($this->maleUser);
        $maleDiscovery = $this->getJson('/api/v1/ems/public/events');
        $maleDiscovery->assertStatus(200);
        $maleSlugs = collect($maleDiscovery->json('data'))->pluck('slug')->all();
        $this->assertContains('brothers-halqa', $maleSlugs);
        $this->assertNotContains('sisters-gathering', $maleSlugs);

        // Male details access
        $this->getJson('/api/v1/ems/public/events/brothers-halqa')->assertStatus(200);
        $this->getJson('/api/v1/ems/public/events/sisters-gathering')->assertStatus(403);

        // Female user access
        Sanctum::actingAs($this->femaleUser);
        $femaleDiscovery = $this->getJson('/api/v1/ems/public/events');
        $femaleDiscovery->assertStatus(200);
        $femaleSlugs = collect($femaleDiscovery->json('data'))->pluck('slug')->all();
        $this->assertContains('sisters-gathering', $femaleSlugs);
        $this->assertNotContains('brothers-halqa', $femaleSlugs);

        $this->getJson('/api/v1/ems/public/events/sisters-gathering')->assertStatus(200);
        $this->getJson('/api/v1/ems/public/events/brothers-halqa')->assertStatus(403);
    }

    /**
     * 3. Media Audience Restriction & Deterministic Sort
     */
    public function test_media_audience_protection_and_deterministic_sort(): void
    {
        $oldMedia = Media::create([
            'uuid' => (string) Str::uuid(),
            'filename' => 'old.jpg',
            'display_name' => 'Old Photo',
            'filepath' => 'media/old.jpg',
            'url' => 'http://localhost/storage/media/old.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'audience' => 'both',
            'created_at' => now()->subDays(2),
        ]);

        $sistersMedia = Media::create([
            'uuid' => (string) Str::uuid(),
            'filename' => 'sisters.jpg',
            'display_name' => 'Sisters Gala',
            'filepath' => 'media/sisters.jpg',
            'url' => 'http://localhost/storage/media/sisters.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 2048,
            'audience' => 'female',
            'created_at' => now()->subDay(),
        ]);

        $newMedia = Media::create([
            'uuid' => (string) Str::uuid(),
            'filename' => 'new.jpg',
            'display_name' => 'New Campus Photo',
            'filepath' => 'media/new.jpg',
            'url' => 'http://localhost/storage/media/new.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'audience' => 'both',
            'created_at' => now(),
        ]);

        // Put a fake file for streaming
        Storage::disk('local')->put($sistersMedia->filepath, 'fake-image-bytes');

        // Public website media list: deterministic newest first
        $publicMedia = $this->getJson('/api/v1/website/media');
        $publicMedia->assertStatus(200);
        $mediaTitles = collect($publicMedia->json('media'))->pluck('title')->all();

        // Newest is first
        $this->assertEquals('New Campus Photo', $mediaTitles[0]);
        // Sisters-only media omitted for guest
        $this->assertNotContains('Sisters Gala', $mediaTitles);

        // Male user cannot see sisters media in list
        Sanctum::actingAs($this->maleUser);
        $maleMedia = $this->getJson('/api/v1/website/media');
        $maleMediaTitles = collect($maleMedia->json('media'))->pluck('title')->all();
        $this->assertNotContains('Sisters Gala', $maleMediaTitles);

        // Male user streaming attempt is rejected with 403
        $this->getJson('/api/v1/website/media/' . $sistersMedia->uuid . '/file')
            ->assertStatus(403);

        // Female user can see and stream sisters media
        Sanctum::actingAs($this->femaleUser);
        $femaleMedia = $this->getJson('/api/v1/website/media');
        $femaleMediaTitles = collect($femaleMedia->json('media'))->pluck('title')->all();
        $this->assertContains('Sisters Gala', $femaleMediaTitles);

        $femaleStream = $this->getJson('/api/v1/website/media/' . $sistersMedia->uuid . '/file');
        $femaleStream->assertStatus(200);
        $this->assertStringContainsString('private', $femaleStream->headers->get('Cache-Control'));
    }

    /**
     * 4. Merchandise Store Product 6-Image Audience Delivery & Streaming Protection
     */
    public function test_merchandise_audience_images_and_streaming_security(): void
    {
        $product = StoreProduct::create([
            'name' => 'SFU MSA Premium Hoodie',
            'slug' => 'sfu-msa-premium-hoodie',
            'description' => 'Official SFU MSA embroidered hoodie',
            'price_cents' => 4500,
            'currency' => 'CAD',
            'status' => ProductStatus::Active,
            'is_merchandise' => true,
        ]);

        // Create the 6 slots
        $slots = [
            ['image_type' => 'neutral', 'view_type' => 'front', 'is_private' => false, 'path' => 'store/neutral_front.jpg'],
            ['image_type' => 'neutral', 'view_type' => 'back', 'is_private' => false, 'path' => 'store/neutral_back.jpg'],
            ['image_type' => 'brother', 'view_type' => 'front', 'is_private' => true, 'path' => 'private/store_merchandise/' . $product->uuid . '/brother_front.jpg'],
            ['image_type' => 'brother', 'view_type' => 'back', 'is_private' => true, 'path' => 'private/store_merchandise/' . $product->uuid . '/brother_back.jpg'],
            ['image_type' => 'sister', 'view_type' => 'front', 'is_private' => true, 'path' => 'private/store_merchandise/' . $product->uuid . '/sister_front.jpg'],
            ['image_type' => 'sister', 'view_type' => 'back', 'is_private' => true, 'path' => 'private/store_merchandise/' . $product->uuid . '/sister_back.jpg'],
        ];

        $slotsMap = [];
        foreach ($slots as $slot) {
            $img = StoreProductImage::create([
                'product_id' => $product->id,
                'uuid' => (string) Str::uuid(),
                'image_path' => $slot['path'],
                'image_url' => $slot['path'],
                'image_type' => $slot['image_type'],
                'view_type' => $slot['view_type'],
                'is_private' => $slot['is_private'],
                'is_primary' => ($slot['image_type'] === 'neutral' && $slot['view_type'] === 'front'),
                'display_order' => 0,
            ]);
            $slotsMap[$slot['image_type'] . '_' . $slot['view_type']] = $img;
            Storage::disk($slot['is_private'] ? 'local' : 'public')->put($slot['path'], 'dummy-image-content');
        }

        // Test guest catalog response
        $guestResponse = $this->getJson('/api/v1/store/products');
        $guestResponse->assertStatus(200);
        $guestItem = collect($guestResponse->json('data'))->firstWhere('slug', 'sfu-msa-premium-hoodie');
        $this->assertNotNull($guestItem);
        $this->assertStringContainsString($slotsMap['neutral_front']->uuid, $guestItem['front_image_url']);
        $this->assertStringContainsString($slotsMap['neutral_back']->uuid, $guestItem['back_image_url']);

        // Test male user catalog response (receives brother model streaming URLs)
        Sanctum::actingAs($this->maleUser);
        $maleResponse = $this->getJson('/api/v1/store/products');
        $maleResponse->assertStatus(200);
        $maleItem = collect($maleResponse->json('data'))->firstWhere('slug', 'sfu-msa-premium-hoodie');
        $this->assertStringContainsString($slotsMap['brother_front']->uuid, $maleItem['front_image_url']);
        $this->assertStringContainsString($slotsMap['brother_back']->uuid, $maleItem['back_image_url']);

        // Test female user catalog response (receives sister model streaming URLs)
        Sanctum::actingAs($this->femaleUser);
        $femaleResponse = $this->getJson('/api/v1/store/products');
        $femaleResponse->assertStatus(200);
        $femaleItem = collect($femaleResponse->json('data'))->firstWhere('slug', 'sfu-msa-premium-hoodie');
        $this->assertStringContainsString($slotsMap['sister_front']->uuid, $femaleItem['front_image_url']);
        $this->assertStringContainsString($slotsMap['sister_back']->uuid, $femaleItem['back_image_url']);

        // Test streaming authorization security
        // 1. Male accessing sister front -> 403 Forbidden
        Sanctum::actingAs($this->maleUser);
        $this->getJson("/api/v1/store/products/{$product->uuid}/images/{$slotsMap['sister_front']->uuid}")
            ->assertStatus(403);

        // 2. Male accessing brother front -> 200 OK
        $maleStream = $this->getJson("/api/v1/store/products/{$product->uuid}/images/{$slotsMap['brother_front']->uuid}");
        $maleStream->assertStatus(200);
        $this->assertStringContainsString('no-store', $maleStream->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $maleStream->headers->get('Cache-Control'));

        // 3. Female accessing brother front -> 403 Forbidden
        Sanctum::actingAs($this->femaleUser);
        $this->getJson("/api/v1/store/products/{$product->uuid}/images/{$slotsMap['brother_front']->uuid}")
            ->assertStatus(403);

        // 4. Female accessing sister front -> 200 OK
        $femaleStream = $this->getJson("/api/v1/store/products/{$product->uuid}/images/{$slotsMap['sister_front']->uuid}");
        $femaleStream->assertStatus(200);
        $this->assertStringContainsString('no-store', $femaleStream->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $femaleStream->headers->get('Cache-Control'));

        // 5. Unauthenticated guest accessing sister front -> 403 Forbidden
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/store/products/{$product->uuid}/images/{$slotsMap['sister_front']->uuid}")
            ->assertStatus(403);

        // 6. Unauthenticated guest accessing neutral front -> 200 OK
        $guestStream = $this->getJson("/api/v1/store/products/{$product->uuid}/images/{$slotsMap['neutral_front']->uuid}");
        $guestStream->assertStatus(200);
    }

    /**
     * 5. Admin Merchandise Validation Requires 6 Images
     */
    public function test_admin_merchandise_validation_requires_all_six_images(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Submitting is_merchandise=true without 6 images fails
        $response = $this->postJson('/api/v1/store-admin/products', [
            'name' => 'MSA Jacket',
            'price_cents' => 6000,
            'is_merchandise' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'merchandise_images.sister_front',
            'merchandise_images.sister_back',
            'merchandise_images.brother_front',
            'merchandise_images.brother_back',
            'merchandise_images.neutral_front',
            'merchandise_images.neutral_back',
        ]);
    }

    /**
     * 6. Admin Targeted Email Broadcast Allows Male and Female
     */
    public function test_admin_broadcast_allows_gender_audiences(): void
    {
        Sanctum::actingAs($this->adminUser);

        $maleBroadcast = $this->postJson('/api/v1/admin/notifications/broadcast', [
            'title' => 'Brothers Notice',
            'message' => 'Please arrive early for Friday setup.',
            'audience' => 'Male',
            'channels' => ['email'],
        ]);
        $maleBroadcast->assertStatus(200);

        $femaleBroadcast = $this->postJson('/api/v1/admin/notifications/broadcast', [
            'title' => 'Sisters Notice',
            'message' => 'Sisters meeting tomorrow afternoon.',
            'audience' => 'Female',
            'channels' => ['email'],
        ]);
        $femaleBroadcast->assertStatus(200);
    }
}
