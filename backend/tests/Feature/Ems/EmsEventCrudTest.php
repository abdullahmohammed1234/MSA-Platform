<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Models\Event;
use App\Ems\Support\EmsRoles;

class EmsEventCrudTest extends EmsTestCase
{
    public function test_an_event_can_be_created(): void
    {
        $administrator = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $category = $this->category();
        $startAt = now()->addWeeks(2)->startOfHour();

        $response = $this->actingAsEms($administrator)->postJson($this->url('events'), [
            'name' => 'MSA Welcome Night',
            'short_description' => 'Kick off the semester.',
            'description' => 'An evening for new and returning students.',
            'category_id' => $category->id,
            'location' => 'SFU Burnaby, Images Theatre',
            'start_at' => $startAt->toDateTimeString(),
            'end_at' => $startAt->copy()->addHours(3)->toDateTimeString(),
            'capacity' => 250,
            'banner_url' => '/storage/uploads/test_image.jpg',
        ]);

        $response->assertCreated();
        $this->assertSuccessEnvelope($response);
        $response->assertJsonPath('message', 'Event created successfully.');
        $response->assertJsonPath('data.name', 'MSA Welcome Night');
        $response->assertJsonPath('data.slug', 'msa-welcome-night');
        $response->assertJsonPath('data.status', EventStatus::Draft->value);
        $response->assertJsonPath('data.category.id', $category->id);
        $response->assertJsonPath('data.banner_url', \Illuminate\Support\Facades\Storage::disk('public')->url('uploads/test_image.jpg'));

        $this->assertDatabaseHas('ems_events', [
            'name' => 'MSA Welcome Night',
            'status' => EventStatus::Draft->value,
            'created_by' => $administrator->id,
            'capacity' => 250,
            'banner_url' => '/storage/uploads/test_image.jpg',
        ]);
    }

    public function test_a_new_event_defaults_its_organizer_to_the_creator(): void
    {
        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);

        $this->actingAsEms($organizer)->postJson($this->url('events'), [
            'name' => 'Halaqa Series',
            'start_at' => now()->addWeek()->toDateTimeString(),
        ])->assertCreated();

        $this->assertDatabaseHas('ems_events', [
            'name' => 'Halaqa Series',
            'organizer_id' => $organizer->id,
        ]);
    }

    public function test_a_new_event_always_starts_as_a_draft_even_if_a_status_is_posted(): void
    {
        $administrator = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);

        $this->actingAsEms($administrator)->postJson($this->url('events'), [
            'name' => 'Sneaky Event',
            'start_at' => now()->addWeek()->toDateTimeString(),
            'status' => EventStatus::Live->value,
            'published_at' => now()->toDateTimeString(),
        ])->assertCreated()
            ->assertJsonPath('data.status', EventStatus::Draft->value)
            ->assertJsonPath('data.published_at', null);
    }

    public function test_slugs_are_made_unique_automatically(): void
    {
        $administrator = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);

        foreach (['first', 'second'] as $_) {
            $this->actingAsEms($administrator)->postJson($this->url('events'), [
                'name' => 'Eid Dinner',
                'start_at' => now()->addWeek()->toDateTimeString(),
            ])->assertCreated();
        }

        $this->assertDatabaseHas('ems_events', ['slug' => 'eid-dinner']);
        $this->assertDatabaseHas('ems_events', ['slug' => 'eid-dinner-2']);
    }

    public function test_creation_validates_its_input(): void
    {
        $administrator = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);

        $response = $this->actingAsEms($administrator)->postJson($this->url('events'), [
            'name' => '',
            'capacity' => -5,
            'category_id' => 99999,
        ]);

        $response->assertUnprocessable();
        $this->assertErrorEnvelope($response);
        $response->assertJsonPath('message', 'Validation failed.');
        $response->assertJsonValidationErrors(['name', 'start_at', 'capacity', 'category_id']);
        $response->assertJsonPath('errors.name.0', 'The event name field is required.');
    }

    public function test_an_event_cannot_end_before_it_starts(): void
    {
        $administrator = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);

        $this->actingAsEms($administrator)->postJson($this->url('events'), [
            'name' => 'Time Travelling Event',
            'start_at' => now()->addWeeks(2)->toDateTimeString(),
            'end_at' => now()->addWeek()->toDateTimeString(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['end_at']);
    }

    public function test_an_event_can_be_read(): void
    {
        $administrator = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = Event::factory()->create(['category_id' => $this->category()->id]);

        $response = $this->actingAsEms($administrator)->getJson($this->url("events/{$event->uuid}"));

        $response->assertOk();
        $this->assertSuccessEnvelope($response);
        $response->assertJsonPath('data.uuid', $event->uuid);
        $response->assertJsonStructure([
            'data' => [
                'id', 'uuid', 'name', 'slug', 'status', 'status_label',
                'start_at', 'available_transitions', 'category', 'organizer',
            ],
        ]);
    }

    public function test_reading_an_unknown_event_returns_a_structured_not_found(): void
    {
        $response = $this->actingAsEms($this->emsUser(EmsRoles::EVENT_ADMINISTRATOR))
            ->getJson($this->url('events/00000000-0000-0000-0000-000000000000'));

        $response->assertNotFound();
        $this->assertErrorEnvelope($response);
    }

    public function test_an_event_can_be_updated(): void
    {
        $administrator = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = Event::factory()->create();

        $response = $this->actingAsEms($administrator)->putJson($this->url("events/{$event->uuid}"), [
            'name' => 'Renamed Event',
            'location' => 'SFU Surrey',
            'capacity' => 80,
            'banner_url' => 'https://external.com/banner.png',
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Event updated successfully.');
        $response->assertJsonPath('data.name', 'Renamed Event');
        $response->assertJsonPath('data.banner_url', 'https://external.com/banner.png');

        $this->assertDatabaseHas('ems_events', [
            'id' => $event->id,
            'name' => 'Renamed Event',
            'location' => 'SFU Surrey',
            'capacity' => 80,
            'banner_url' => 'https://external.com/banner.png',
            'updated_by' => $administrator->id,
        ]);
    }

    public function test_a_partial_update_leaves_untouched_fields_alone(): void
    {
        $administrator = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = Event::factory()->create(['location' => 'Original Venue']);
        $originalStart = $event->start_at;

        $this->actingAsEms($administrator)
            ->putJson($this->url("events/{$event->uuid}"), ['name' => 'Just The Name'])
            ->assertOk();

        $event->refresh();

        $this->assertSame('Just The Name', $event->name);
        $this->assertSame('Original Venue', $event->location);
        $this->assertTrue($originalStart->equalTo($event->start_at));
    }

    public function test_an_update_cannot_change_the_status(): void
    {
        $administrator = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = Event::factory()->create();

        $this->actingAsEms($administrator)
            ->putJson($this->url("events/{$event->uuid}"), [
                'name' => 'Still A Draft',
                'status' => EventStatus::Completed->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', EventStatus::Draft->value);

        $this->assertSame(EventStatus::Draft, $event->fresh()->status);
    }

    public function test_an_event_can_be_deleted(): void
    {
        $administrator = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $event = Event::factory()->create();

        $response = $this->actingAsEms($administrator)
            ->deleteJson($this->url("events/{$event->uuid}"));

        $response->assertOk();
        $response->assertJsonPath('message', 'Event deleted successfully.');

        // Soft deleted: the row survives for audit, the API stops serving it.
        $this->assertSoftDeleted('ems_events', ['id' => $event->id]);

        $this->actingAsEms($administrator)
            ->getJson($this->url("events/{$event->uuid}"))
            ->assertNotFound();
    }

    public function test_the_list_endpoint_paginates_filters_and_sorts(): void
    {
        $administrator = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $category = $this->category();

        Event::factory()->count(3)->create(['category_id' => $category->id]);
        Event::factory()->count(2)->create();
        Event::factory()->create(['name' => 'Ramadan Iftar Night']);

        $paged = $this->actingAsEms($administrator)
            ->getJson($this->url('events?per_page=2'));

        $paged->assertOk();
        $paged->assertJsonCount(2, 'data');
        $paged->assertJsonPath('meta.pagination.per_page', 2);
        $paged->assertJsonPath('meta.pagination.total', 6);

        $filtered = $this->actingAsEms($administrator)
            ->getJson($this->url("events?category_id={$category->id}"));

        $filtered->assertOk()->assertJsonPath('meta.pagination.total', 3);

        $searched = $this->actingAsEms($administrator)
            ->getJson($this->url('events?search=Ramadan'));

        $searched->assertOk()->assertJsonPath('meta.pagination.total', 1);
        $searched->assertJsonPath('data.0.name', 'Ramadan Iftar Night');
    }

    public function test_the_list_rejects_an_unknown_sort_column(): void
    {
        $this->actingAsEms($this->emsUser(EmsRoles::EVENT_ADMINISTRATOR))
            ->getJson($this->url('events?sort_by=password'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort_by']);
    }

    public function test_event_writes_are_recorded_in_the_audit_trail(): void
    {
        $administrator = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);

        $uuid = $this->actingAsEms($administrator)->postJson($this->url('events'), [
            'name' => 'Audited Event',
            'start_at' => now()->addWeek()->toDateTimeString(),
        ])->json('data.uuid');

        $this->actingAsEms($administrator)
            ->putJson($this->url("events/{$uuid}"), ['name' => 'Audited Event v2'])
            ->assertOk();

        $this->actingAsEms($administrator)
            ->deleteJson($this->url("events/{$uuid}"))
            ->assertOk();

        foreach (['ems.event.created', 'ems.event.updated', 'ems.event.deleted'] as $action) {
            $this->assertDatabaseHas('audit_logs', [
                'action' => $action,
                'user_id' => $administrator->id,
            ]);
        }
    }
}
