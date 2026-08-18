<?php

namespace Tests\Feature\Ems;

use App\Ems\Models\Event;
use App\Ems\Models\EventCategory;
use App\Ems\Support\EmsRoles;

class EmsEventCategoryTest extends EmsTestCase
{
    public function test_categories_can_be_listed(): void
    {
        $this->category(['name' => 'Brothers', 'sort_order' => 10]);
        $this->category(['name' => 'Sisters', 'sort_order' => 0]);

        $response = $this->actingAsEms($this->emsUser(EmsRoles::EVENT_ADMINISTRATOR))
            ->getJson($this->url('event-categories'));

        $response->assertOk();
        $this->assertSuccessEnvelope($response);
        $response->assertJsonCount(2, 'data');

        // Ordered by sort_order, then name.
        $response->assertJsonPath('data.0.name', 'Sisters');
        $response->assertJsonPath('data.1.name', 'Brothers');
    }

    public function test_the_category_list_reports_how_many_events_use_each_one(): void
    {
        $category = $this->category();
        Event::factory()->count(2)->create(['category_id' => $category->id]);

        $this->actingAsEms($this->emsUser(EmsRoles::EVENT_ADMINISTRATOR))
            ->getJson($this->url('event-categories'))
            ->assertOk()
            ->assertJsonPath('data.0.events_count', 2);
    }

    public function test_a_category_can_be_created(): void
    {
        $response = $this->actingAsEms($this->emsUser(EmsRoles::EVENT_ADMINISTRATOR))
            ->postJson($this->url('event-categories'), [
                'name' => 'Fundraising',
                'description' => 'Charity drives and fundraisers.',
                'color' => '#a8781a',
                'sort_order' => 5,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('message', 'Event category created successfully.');
        $response->assertJsonPath('data.slug', 'fundraising');
        $response->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('ems_event_categories', [
            'name' => 'Fundraising',
            'slug' => 'fundraising',
        ]);
    }

    public function test_category_names_must_be_unique(): void
    {
        $this->category(['name' => 'Ramadan']);

        $response = $this->actingAsEms($this->emsUser(EmsRoles::EVENT_ADMINISTRATOR))
            ->postJson($this->url('event-categories'), ['name' => 'Ramadan']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
        $response->assertJsonPath('errors.name.0', 'A category with this name already exists.');
    }

    public function test_a_category_colour_must_be_a_hex_value(): void
    {
        $this->actingAsEms($this->emsUser(EmsRoles::EVENT_ADMINISTRATOR))
            ->postJson($this->url('event-categories'), [
                'name' => 'Bad Colour',
                'color' => 'crimson',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['color']);
    }

    public function test_a_category_can_be_read_and_updated(): void
    {
        $category = $this->category(['name' => 'Social']);
        $administrator = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);

        $this->actingAsEms($administrator)
            ->getJson($this->url("event-categories/{$category->uuid}"))
            ->assertOk()
            ->assertJsonPath('data.name', 'Social');

        $this->actingAsEms($administrator)
            ->putJson($this->url("event-categories/{$category->uuid}"), [
                'name' => 'Community Social',
                'description' => 'Updated.',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Community Social');

        $this->assertDatabaseHas('ems_event_categories', [
            'id' => $category->id,
            'name' => 'Community Social',
        ]);
    }

    public function test_a_category_can_be_deactivated_rather_than_deleted(): void
    {
        $category = $this->category();

        $this->actingAsEms($this->emsUser(EmsRoles::EVENT_ADMINISTRATOR))
            ->putJson($this->url("event-categories/{$category->uuid}"), ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('ems_event_categories', [
            'id' => $category->id,
            'is_active' => false,
        ]);
    }

    public function test_an_unused_category_can_be_deleted(): void
    {
        $category = $this->category();

        $this->actingAsEms($this->emsUser(EmsRoles::EVENT_ADMINISTRATOR))
            ->deleteJson($this->url("event-categories/{$category->uuid}"))
            ->assertOk()
            ->assertJsonPath('message', 'Event category deleted successfully.');

        $this->assertSoftDeleted('ems_event_categories', ['id' => $category->id]);
    }

    public function test_deleting_a_category_that_still_has_events_is_refused_with_a_conflict(): void
    {
        $category = $this->category(['name' => 'In Use']);
        Event::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAsEms($this->emsUser(EmsRoles::EVENT_ADMINISTRATOR))
            ->deleteJson($this->url("event-categories/{$category->uuid}"));

        $response->assertStatus(409);
        $this->assertErrorEnvelope($response);
        $response->assertJsonStructure(['errors' => ['category']]);

        $this->assertDatabaseHas('ems_event_categories', [
            'id' => $category->id,
            'deleted_at' => null,
        ]);
    }

    // -----------------------------------------------------------------
    // Authorization
    // -----------------------------------------------------------------

    public function test_organizers_may_read_categories_but_not_change_them(): void
    {
        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $category = $this->category();

        $this->actingAsEms($organizer)
            ->getJson($this->url('event-categories'))
            ->assertOk();

        $this->actingAsEms($organizer)
            ->postJson($this->url('event-categories'), ['name' => 'Nope'])
            ->assertForbidden();

        $this->actingAsEms($organizer)
            ->putJson($this->url("event-categories/{$category->uuid}"), ['name' => 'Nope'])
            ->assertForbidden();

        $this->actingAsEms($organizer)
            ->deleteJson($this->url("event-categories/{$category->uuid}"))
            ->assertForbidden();
    }

    public function test_attendees_cannot_read_categories(): void
    {
        $this->actingAsEms($this->emsUser(EmsRoles::ATTENDEE))
            ->getJson($this->url('event-categories'))
            ->assertForbidden();
    }

    public function test_the_seeder_installs_the_starting_msa_taxonomy(): void
    {
        $this->seed(\Database\Seeders\Ems\EmsEventCategorySeeder::class);

        foreach (['social', 'education', 'other'] as $slug) {
            $this->assertDatabaseHas('ems_event_categories', ['slug' => $slug, 'deleted_at' => null]);
        }

        $this->assertDatabaseHas('ems_event_categories', [
            'slug' => 'education',
            'name' => 'Educational/Halaqahs',
        ]);

        $this->assertSame(3, EventCategory::count());

        // Re-running must not duplicate anything.
        $before = EventCategory::count();
        $this->seed(\Database\Seeders\Ems\EmsEventCategorySeeder::class);
        $this->assertSame($before, EventCategory::count());
    }

    public function test_the_seeder_retires_legacy_categories_and_reassigns_events(): void
    {
        $brothers = $this->category(['name' => 'Brothers', 'slug' => 'brothers']);
        $social = $this->category(['name' => 'Social', 'slug' => 'social']);
        $event = Event::factory()->create(['category_id' => $brothers->id]);

        $this->seed(\Database\Seeders\Ems\EmsEventCategorySeeder::class);

        $this->assertSame($social->id, $event->fresh()->category_id);
        $this->assertSoftDeleted('ems_event_categories', ['id' => $brothers->id]);
        $this->assertDatabaseHas('ems_event_categories', [
            'slug' => 'education',
            'name' => 'Educational/Halaqahs',
        ]);
    }
}
