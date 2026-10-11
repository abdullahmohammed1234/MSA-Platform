<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Models\Event;
use App\Ems\Models\EventSeries;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PublicEventsSeriesRemediationTest extends EmsTestCase
{
    /** @test */
    public function public_events_api_returns_authoritative_series_object_for_events_with_series(): void
    {
        $category = $this->category();

        $series = EventSeries::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Authoritative Weekly Halaqa',
            'description' => 'Weekly halaqa series description',
            'recurrence_pattern' => 'weekly',
            'recurrence_interval' => 1,
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addMonth()->toDateString(),
        ]);

        $eventWithSeries = Event::factory()->create([
            'name' => 'Weekly Halaqa Session 1',
            'slug' => 'weekly-halaqa-session-1',
            'category_id' => $category->id,
            'series_id' => $series->id,
            'status' => EventStatus::Published,
            'is_public' => true,
            'start_at' => Carbon::tomorrow()->setTime(18, 0),
            'end_at' => Carbon::tomorrow()->setTime(20, 0),
        ]);

        $eventWithoutSeries = Event::factory()->create([
            'name' => 'One-off Special Event',
            'slug' => 'one-off-special-event',
            'category_id' => $category->id,
            'series_id' => null,
            'status' => EventStatus::Published,
            'is_public' => true,
            'start_at' => Carbon::tomorrow()->addDay()->setTime(18, 0),
            'end_at' => Carbon::tomorrow()->addDay()->setTime(20, 0),
        ]);

        $response = $this->getJson('/api/v1/ems/public/events');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.series.uuid', $series->uuid);
        $response->assertJsonPath('data.0.series.name', 'Authoritative Weekly Halaqa');
        $response->assertJsonPath('data.1.series', null);
    }

    /** @test */
    public function public_event_detail_api_includes_series_object(): void
    {
        $category = $this->category();

        $series = EventSeries::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Authoritative Tafseer Series',
            'description' => 'Weekly Tafseer study circle',
            'recurrence_pattern' => 'weekly',
            'recurrence_interval' => 1,
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addMonth()->toDateString(),
        ]);

        $event = Event::factory()->create([
            'name' => 'Tafseer Surah Yasin Part 1',
            'slug' => 'tafseer-surah-yasin-part-1',
            'category_id' => $category->id,
            'series_id' => $series->id,
            'status' => EventStatus::Published,
            'is_public' => true,
            'start_at' => Carbon::tomorrow()->setTime(18, 0),
            'end_at' => Carbon::tomorrow()->setTime(20, 0),
        ]);

        $response = $this->getJson("/api/v1/ems/public/events/{$event->slug}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.series.uuid', $series->uuid);
        $response->assertJsonPath('data.series.name', 'Authoritative Tafseer Series');
    }
}
