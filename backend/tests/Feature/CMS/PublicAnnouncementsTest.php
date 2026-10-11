<?php

namespace Tests\Feature\CMS;

use App\Events\AnnouncementPublishedEvent;
use App\Models\CMS\Announcement;
use App\Models\User;
use App\Notifications\NewAnnouncementNotification;
use App\Services\CMS\AnnouncementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PublicAnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('website_announcements');
    }

    public function test_published_announcements_appear_in_public_listing(): void
    {
        $published = Announcement::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Important Jumuah Notice',
            'slug' => 'important-jumuah-notice',
            'content' => 'Jumuah prayer will be held at West Gym.',
            'summary' => 'Prayer',
            'status' => 'published',
            'published_at' => now()->subHours(2),
        ]);

        $response = $this->getJson('/api/v1/website/announcements');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'announcements' => [
                    '*' => ['id', 'title', 'slug', 'content', 'summary', 'date', 'category', 'featured_image']
                ]
            ]);

        $announcements = $response->json('announcements');
        $this->assertTrue(collect($announcements)->contains('slug', 'important-jumuah-notice'));
    }

    public function test_draft_and_future_published_announcements_are_excluded_from_public_listing(): void
    {
        $draft = Announcement::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Internal Draft Announcement',
            'slug' => 'internal-draft-announcement',
            'content' => 'This is a draft.',
            'summary' => 'General',
            'status' => 'draft',
            'published_at' => null,
        ]);

        $future = Announcement::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Scheduled Future Announcement',
            'slug' => 'scheduled-future-announcement',
            'content' => 'This will publish tomorrow.',
            'summary' => 'General',
            'status' => 'published',
            'published_at' => now()->addDay(),
        ]);

        $response = $this->getJson('/api/v1/website/announcements');

        $response->assertStatus(200);
        $slugs = collect($response->json('announcements'))->pluck('slug')->toArray();

        $this->assertNotContains('internal-draft-announcement', $slugs);
        $this->assertNotContains('scheduled-future-announcement', $slugs);
    }

    public function test_direct_access_to_unpublished_or_draft_detail_page_returns_404(): void
    {
        Announcement::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Secret Draft',
            'slug' => 'secret-draft',
            'content' => 'Secret content',
            'summary' => 'General',
            'status' => 'draft',
            'published_at' => null,
        ]);

        $response = $this->getJson('/api/v1/website/announcements/secret-draft');

        $response->assertStatus(404)
            ->assertJson(['message' => 'Announcement not found.']);
    }

    public function test_valid_published_detail_page_returns_expected_content_and_related(): void
    {
        $author = User::factory()->create(['name' => 'Admin Author']);

        $main = Announcement::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Ramadan Iftar Gathering',
            'slug' => 'ramadan-iftar-gathering',
            'content' => 'Join us for community Iftar every evening.',
            'summary' => 'Events',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'author_id' => $author->id,
        ]);

        $relatedItem = Announcement::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Evolutions of Dawah',
            'slug' => 'evolutions-of-dawah',
            'content' => 'New lecture series starting soon.',
            'summary' => 'Education',
            'status' => 'published',
            'published_at' => now()->subDays(2),
        ]);

        $response = $this->getJson('/api/v1/website/announcements/ramadan-iftar-gathering');

        $response->assertStatus(200)
            ->assertJson([
                'announcement' => [
                    'title' => 'Ramadan Iftar Gathering',
                    'slug' => 'ramadan-iftar-gathering',
                    'content' => 'Join us for community Iftar every evening.',
                    'summary' => 'Events',
                    'author' => [
                        'name' => 'Admin Author',
                    ],
                ]
            ]);

        $related = $response->json('related');
        $this->assertNotEmpty($related);
        $this->assertTrue(collect($related)->contains('slug', 'evolutions-of-dawah'));
    }

    public function test_missing_slug_returns_404(): void
    {
        $response = $this->getJson('/api/v1/website/announcements/non-existent-slug');
        $response->assertStatus(404);
    }

    public function test_search_and_category_filtering_work(): void
    {
        Announcement::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Unique Searchable Keyword Announcement',
            'slug' => 'unique-searchable-keyword-announcement',
            'content' => 'Content with special details.',
            'summary' => 'SpecialCategory',
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        Announcement::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Standard Announcement',
            'slug' => 'standard-announcement',
            'content' => 'Regular content.',
            'summary' => 'General',
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        $searchResponse = $this->getJson('/api/v1/website/announcements?search=Searchable');
        $searchResponse->assertStatus(200);
        $searchSlugs = collect($searchResponse->json('announcements'))->pluck('slug')->toArray();
        $this->assertContains('unique-searchable-keyword-announcement', $searchSlugs);
        $this->assertNotContains('standard-announcement', $searchSlugs);

        $categoryResponse = $this->getJson('/api/v1/website/announcements?category=SpecialCategory');
        $categoryResponse->assertStatus(200);
        $categorySlugs = collect($categoryResponse->json('announcements'))->pluck('slug')->toArray();
        $this->assertContains('unique-searchable-keyword-announcement', $categorySlugs);
        $this->assertNotContains('standard-announcement', $categorySlugs);
    }

    public function test_fallback_sample_announcements_return_full_content_on_detail_endpoint(): void
    {
        $response = $this->getJson('/api/v1/website/announcements/jumuah-location-update');

        $response->assertStatus(200)
            ->assertJson([
                'announcement' => [
                    'title' => "Jumu'ah Location Update",
                    'slug' => 'jumuah-location-update',
                    'category' => 'Prayer',
                ]
            ]);

        $this->assertNotEmpty($response->json('announcement.content'));
    }

    public function test_publishing_announcement_dispatches_event_without_duplicate_notifications_on_edit(): void
    {
        Event::fake([AnnouncementPublishedEvent::class]);
        Notification::fake();

        $activeUser = User::factory()->create(['is_active' => true]);

        /** @var AnnouncementService $service */
        $service = app(AnnouncementService::class);

        $announcement = $service->create([
            'title' => 'New Campus Halah',
            'content' => 'Weekly Halaqah details...',
            'summary' => 'Prayer',
            'status' => 'published',
        ], null);

        Event::assertDispatched(AnnouncementPublishedEvent::class, 1);

        // Editing an already published announcement should NOT re-trigger published event
        $service->update($announcement, [
            'content' => 'Updated weekly halaqah time to 5 PM.',
        ], null);

        Event::assertDispatched(AnnouncementPublishedEvent::class, 1);
    }

    public function test_unauthorized_users_cannot_perform_admin_cms_write_operations(): void
    {
        $guestResponse = $this->postJson('/api/v1/admin/cms/announcements', [
            'title' => 'Unauthorized Post',
            'content' => 'Content',
            'status' => 'published',
        ]);
        $guestResponse->assertStatus(401);

        $member = User::factory()->create();
        $memberResponse = $this->actingAs($member)->postJson('/api/v1/admin/cms/announcements', [
            'title' => 'Unauthorized Member Post',
            'content' => 'Content',
            'status' => 'published',
        ]);
        $memberResponse->assertStatus(403);
    }
}
