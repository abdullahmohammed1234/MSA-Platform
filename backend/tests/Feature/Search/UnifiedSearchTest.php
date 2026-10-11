<?php

namespace Tests\Feature\Search;

use App\Ems\Models\Event;
use App\Models\CMS\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnifiedSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_or_empty_query_returns_helpful_message_and_empty_items(): void
    {
        $response = $this->getJson('/api/v1/search?q=a');

        $response->assertStatus(200)
            ->assertJsonPath('query', 'a')
            ->assertJsonPath('pagination.total', 0)
            ->assertJsonPath('items', []);

        $emptyResponse = $this->getJson('/api/v1/search?q=' . urlencode('  '));
        $emptyResponse->assertStatus(200)
            ->assertJsonPath('pagination.total', 0)
            ->assertJsonPath('items', []);
    }

    public function test_search_returns_matching_published_announcements(): void
    {
        Announcement::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Jumuah Location Change Notice',
            'slug' => 'jumuah-location-change-notice',
            'summary' => 'Jumuah prayer will move to West Gym.',
            'content' => 'Full details regarding Friday prayer room reallocation.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/v1/search?q=Jumuah');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('items.0.content_type', 'announcement')
            ->assertJsonPath('items.0.title', 'Jumuah Location Change Notice')
            ->assertJsonPath('items.0.destination', '/announcements/jumuah-location-change-notice');
    }

    public function test_search_returns_matching_public_ems_events(): void
    {
        Event::factory()->create([
            'name' => 'Campus Welcome Halaqah',
            'slug' => 'campus-welcome-halaqah',
            'short_description' => 'Orientation gathering for new SFU students.',
            'description' => 'Join us for talks, food, and games.',
            'status' => 'published',
            'is_public' => true,
            'start_at' => now()->addDays(2),
        ]);

        $response = $this->getJson('/api/v1/search?q=Halaqah');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('items.0.content_type', 'event')
            ->assertJsonPath('items.0.title', 'Campus Welcome Halaqah')
            ->assertJsonPath('items.0.destination', '/events/campus-welcome-halaqah');
    }

    public function test_draft_and_future_scheduled_content_are_excluded(): void
    {
        Announcement::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Secret Draft Announcement',
            'slug' => 'secret-draft',
            'summary' => 'Secret draft summary',
            'content' => 'Secret draft content',
            'status' => 'draft',
            'published_at' => null,
        ]);

        Announcement::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Future Scheduled Announcement',
            'slug' => 'future-scheduled',
            'summary' => 'Future scheduled summary',
            'content' => 'Future scheduled content',
            'status' => 'published',
            'published_at' => now()->addDays(5),
        ]);

        Event::factory()->create([
            'name' => 'Private Internal Meeting',
            'slug' => 'private-internal-meeting',
            'status' => 'published',
            'is_public' => false,
        ]);

        Event::factory()->create([
            'name' => 'Cancelled Mega Event',
            'slug' => 'cancelled-mega-event',
            'status' => 'cancelled',
            'is_public' => true,
        ]);

        $response = $this->getJson('/api/v1/search?q=Secret');
        $response->assertStatus(200)->assertJsonPath('pagination.total', 0);

        $response2 = $this->getJson('/api/v1/search?q=Private');
        $response2->assertStatus(200)->assertJsonPath('pagination.total', 0);

        $response3 = $this->getJson('/api/v1/search?q=Future');
        $response3->assertStatus(200)->assertJsonPath('pagination.total', 0);
    }

    public function test_content_type_filtering_works_correctly(): void
    {
        Announcement::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Unique Topic Announcement',
            'slug' => 'unique-topic-announcement',
            'summary' => 'Unique topic summary',
            'content' => 'Unique topic content',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        Event::factory()->create([
            'name' => 'Unique Topic Event',
            'slug' => 'unique-topic-event',
            'status' => 'published',
            'is_public' => true,
        ]);

        // Filter for event only
        $resEvent = $this->getJson('/api/v1/search?q=Unique&type=event');
        $resEvent->assertStatus(200)
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('items.0.content_type', 'event');

        // Filter for announcement only
        $resAnn = $this->getJson('/api/v1/search?q=Unique&type=announcement');
        $resAnn->assertStatus(200)
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('items.0.content_type', 'announcement');
    }

    public function test_pagination_and_result_limits(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            Announcement::create([
                'uuid' => (string) Str::uuid(),
                'title' => "Batch Announcement Number {$i}",
                'slug' => "batch-announcement-{$i}",
                'summary' => "Batch announcement summary {$i}",
                'content' => "Batch announcement content {$i}",
                'status' => 'published',
                'published_at' => now()->subMinutes($i),
            ]);
        }

        $resPage1 = $this->getJson('/api/v1/search?q=Batch&per_page=5&page=1');
        $resPage1->assertStatus(200)
            ->assertJsonPath('pagination.total', 15)
            ->assertJsonPath('pagination.per_page', 5)
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('pagination.last_page', 3)
            ->assertJsonCount(5, 'items');

        $resPage2 = $this->getJson('/api/v1/search?q=Batch&per_page=5&page=2');
        $resPage2->assertStatus(200)
            ->assertJsonPath('pagination.current_page', 2)
            ->assertJsonCount(5, 'items');
    }

    public function test_sql_injection_and_malformed_queries_are_handled_safely(): void
    {
        $malformedQuery = "' OR 1=1; DROP TABLE users; --";
        $response = $this->getJson('/api/v1/search?q=' . urlencode($malformedQuery));

        $response->assertStatus(200)
            ->assertJsonPath('pagination.total', 0);

        // Ensure users table still exists and app is healthy
        $this->assertDatabaseCount('users', 0);
    }
}
