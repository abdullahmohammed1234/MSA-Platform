<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Models\Event;
use App\Ems\Support\EmsRoles;

class EmsDashboardTest extends EmsTestCase
{
    public function test_the_dashboard_returns_summary_upcoming_activity_and_quick_actions(): void
    {
        $administrator = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);

        Event::factory()->count(2)->create();
        Event::factory()->status(EventStatus::Published)->create();
        Event::factory()->status(EventStatus::Completed)->past()->create();

        $response = $this->actingAsEms($administrator)->getJson($this->url('dashboard'));

        $response->assertOk();
        $this->assertSuccessEnvelope($response);
        $response->assertJsonStructure([
            'data' => [
                'summary' => ['total', 'upcoming', 'draft', 'published', 'completed', 'archived'],
                'upcoming_events',
                'recent_activity',
                'quick_actions',
            ],
        ]);

        $response->assertJsonPath('data.summary.total', 4);
        $response->assertJsonPath('data.summary.draft', 2);
        $response->assertJsonPath('data.summary.published', 1);
        $response->assertJsonPath('data.summary.completed', 1);
    }

    public function test_the_upcoming_count_excludes_finished_and_past_events(): void
    {
        $administrator = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);

        Event::factory()->count(2)->create();                                 // future drafts
        Event::factory()->past()->create();                                   // already happened
        Event::factory()->status(EventStatus::Completed)->create();           // future date, but done
        Event::factory()->status(EventStatus::Archived)->create();            // future date, but archived

        $this->actingAsEms($administrator)
            ->getJson($this->url('dashboard'))
            ->assertOk()
            ->assertJsonPath('data.summary.upcoming', 2);
    }

    public function test_upcoming_events_are_ordered_soonest_first_and_capped(): void
    {
        $administrator = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);

        $soonest = Event::factory()->create(['start_at' => now()->addDay()]);
        Event::factory()->create(['start_at' => now()->addWeeks(2)]);
        Event::factory()->count(8)->create(['start_at' => now()->addMonths(2)]);

        $response = $this->actingAsEms($administrator)->getJson($this->url('dashboard'));

        $upcoming = $response->json('data.upcoming_events');

        $this->assertCount((int) config('ems.dashboard.upcoming_limit'), $upcoming);
        $this->assertSame($soonest->uuid, $upcoming[0]['uuid']);
    }

    public function test_the_dashboard_is_scoped_to_what_the_viewer_may_see(): void
    {
        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $other = $this->emsUser(EmsRoles::EVENT_ORGANIZER);

        Event::factory()->count(2)->organizedBy($organizer)->create();
        Event::factory()->count(5)->organizedBy($other)->create();

        $this->actingAsEms($organizer)
            ->getJson($this->url('dashboard'))
            ->assertOk()
            ->assertJsonPath('data.summary.total', 2);
    }

    public function test_quick_actions_follow_the_viewers_permissions(): void
    {
        $administrator = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $staff = $this->emsUser(EmsRoles::EVENT_STAFF);

        $adminActions = array_column(
            $this->actingAsEms($administrator)->getJson($this->url('dashboard'))->json('data.quick_actions'),
            'key'
        );

        $this->assertEqualsCanonicalizing(
            ['create_event', 'manage_events', 'manage_categories'],
            $adminActions
        );

        $staffActions = array_column(
            $this->actingAsEms($staff)->getJson($this->url('dashboard'))->json('data.quick_actions'),
            'key'
        );

        // Staff can read events and categories but cannot create anything.
        $this->assertEqualsCanonicalizing(['manage_events', 'manage_categories'], $staffActions);
    }

    public function test_recent_activity_reflects_ems_actions(): void
    {
        $administrator = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);

        $this->actingAsEms($administrator)->postJson($this->url('events'), [
            'name' => 'Activity Feed Event',
            'start_at' => now()->addWeek()->toDateTimeString(),
        ])->assertCreated();

        $activity = $this->actingAsEms($administrator)
            ->getJson($this->url('dashboard'))
            ->json('data.recent_activity');

        $this->assertNotEmpty($activity);
        $this->assertSame('event.created', $activity[0]['action']);
        $this->assertSame($administrator->id, $activity[0]['actor']['id']);
    }

    public function test_attendees_cannot_reach_the_dashboard(): void
    {
        $this->actingAsEms($this->emsUser(EmsRoles::ATTENDEE))
            ->getJson($this->url('dashboard'))
            ->assertForbidden();
    }
}
