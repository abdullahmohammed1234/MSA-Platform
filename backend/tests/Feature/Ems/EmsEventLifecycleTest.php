<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\EventTransition;
use App\Ems\Models\Event;
use App\Ems\Support\EmsRoles;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;

class EmsEventLifecycleTest extends EmsTestCase
{
    // -----------------------------------------------------------------
    // Valid transitions
    // -----------------------------------------------------------------

    /**
     * @return array<string, array{0: EventStatus, 1: string, 2: EventStatus}>
     */
    public static function validTransitions(): array
    {
        return [
            'draft to published' => [EventStatus::Draft, 'publish', EventStatus::Published],
            'published to registration open' => [EventStatus::Published, 'open_registration', EventStatus::RegistrationOpen],
            'registration open to closed' => [EventStatus::RegistrationOpen, 'close_registration', EventStatus::RegistrationClosed],
            'registration closed to live' => [EventStatus::RegistrationClosed, 'mark_live', EventStatus::Live],
            'live to completed' => [EventStatus::Live, 'complete', EventStatus::Completed],
            'completed to archived' => [EventStatus::Completed, 'archive', EventStatus::Archived],
            'published back to draft' => [EventStatus::Published, 'unpublish', EventStatus::Draft],
        ];
    }

    #[DataProvider('validTransitions')]
    public function test_valid_transitions_are_accepted(EventStatus $from, string $action, EventStatus $to): void
    {
        $event = Event::factory()->status($from)->create();

        $response = $this->actingAsEms($this->superAdmin())
            ->postJson($this->url("events/{$event->uuid}/transitions"), ['action' => $action]);

        $response->assertOk();
        $this->assertSuccessEnvelope($response);
        $response->assertJsonPath('data.status', $to->value);
        $response->assertJsonPath('message', sprintf('Event is now %s.', $to->label()));

        $this->assertSame($to, $event->fresh()->status);
    }

    public function test_an_event_can_walk_the_whole_lifecycle_end_to_end(): void
    {
        $superAdmin = $this->superAdmin();
        $event = Event::factory()->create();

        $chain = [
            'publish' => EventStatus::Published,
            'open_registration' => EventStatus::RegistrationOpen,
            'close_registration' => EventStatus::RegistrationClosed,
            'mark_live' => EventStatus::Live,
            'complete' => EventStatus::Completed,
            'archive' => EventStatus::Archived,
        ];

        foreach ($chain as $action => $expected) {
            $this->actingAsEms($superAdmin)
                ->postJson($this->url("events/{$event->uuid}/transitions"), ['action' => $action])
                ->assertOk()
                ->assertJsonPath('data.status', $expected->value);
        }

        $this->assertSame(EventStatus::Archived, $event->fresh()->status);
    }

    // -----------------------------------------------------------------
    // Invalid transitions
    // -----------------------------------------------------------------

    /**
     * @return array<string, array{0: EventStatus, 1: string}>
     */
    public static function invalidTransitions(): array
    {
        return [
            'draft to completed' => [EventStatus::Draft, 'complete'],
            'draft to archived' => [EventStatus::Draft, 'archive'],
            'draft to live' => [EventStatus::Draft, 'mark_live'],
            'draft to registration open' => [EventStatus::Draft, 'open_registration'],
            'completed back to live' => [EventStatus::Completed, 'mark_live'],
            'completed to published' => [EventStatus::Completed, 'publish'],
            'archived back to draft' => [EventStatus::Archived, 'unpublish'],
            'archived to published' => [EventStatus::Archived, 'publish'],
            'published straight to live' => [EventStatus::Published, 'mark_live'],
            'registration open to live' => [EventStatus::RegistrationOpen, 'mark_live'],
            'live to archived' => [EventStatus::Live, 'archive'],
            'closing registration that never opened' => [EventStatus::Published, 'close_registration'],
        ];
    }

    #[DataProvider('invalidTransitions')]
    public function test_invalid_transitions_are_blocked(EventStatus $from, string $action): void
    {
        $event = Event::factory()->status($from)->create();

        $response = $this->actingAsEms($this->superAdmin())
            ->postJson($this->url("events/{$event->uuid}/transitions"), ['action' => $action]);

        $response->assertStatus(409);
        $this->assertErrorEnvelope($response);
        $response->assertJsonStructure(['errors' => ['status']]);

        // The event is untouched.
        $this->assertSame($from, $event->fresh()->status);
    }

    public function test_an_unrecognised_action_is_rejected_as_a_validation_error(): void
    {
        $event = Event::factory()->create();

        $this->actingAsEms($this->superAdmin())
            ->postJson($this->url("events/{$event->uuid}/transitions"), ['action' => 'obliterate'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['action']);
    }

    public function test_an_archived_event_offers_no_further_transitions(): void
    {
        $event = Event::factory()->status(EventStatus::Archived)->create();

        $this->actingAsEms($this->superAdmin())
            ->getJson($this->url("events/{$event->uuid}"))
            ->assertOk()
            ->assertJsonPath('data.available_transitions', []);
    }

    // -----------------------------------------------------------------
    // Side effects
    // -----------------------------------------------------------------

    public function test_transitions_stamp_their_lifecycle_timestamps(): void
    {
        $superAdmin = $this->superAdmin();
        $event = Event::factory()->create();

        $this->transition($superAdmin, $event, 'publish');
        $this->assertNotNull($event->fresh()->published_at);

        $this->transition($superAdmin, $event, 'open_registration');
        $event->refresh();
        $this->assertNotNull($event->registration_open_at);
        $this->assertNull($event->registration_closed_at);

        $this->transition($superAdmin, $event, 'close_registration');
        $this->assertNotNull($event->fresh()->registration_closed_at);

        $this->transition($superAdmin, $event, 'mark_live');
        $this->transition($superAdmin, $event, 'complete');
        $this->assertNotNull($event->fresh()->completed_at);

        $this->transition($superAdmin, $event, 'archive');
        $this->assertNotNull($event->fresh()->archived_at);
    }

    public function test_unpublishing_clears_the_published_timestamp(): void
    {
        $event = Event::factory()->status(EventStatus::Published)->create();

        $this->transition($this->superAdmin(), $event, 'unpublish');

        $event->refresh();
        $this->assertSame(EventStatus::Draft, $event->status);
        $this->assertNull($event->published_at);
    }

    public function test_reopening_registration_clears_the_previous_close_timestamp(): void
    {
        $superAdmin = $this->superAdmin();
        $event = Event::factory()->status(EventStatus::RegistrationClosed)->create();

        $this->assertNotNull($event->registration_closed_at);

        // Walk back to published, then reopen.
        $event->forceFill(['status' => EventStatus::Published])->save();
        $this->transition($superAdmin, $event, 'open_registration');

        $this->assertNull($event->fresh()->registration_closed_at);
    }

    public function test_every_transition_is_written_to_the_audit_trail(): void
    {
        $event = Event::factory()->create();

        $this->transition($this->superAdmin(), $event, 'publish');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ems.event.status_changed',
            'target_id' => $event->id,
        ]);
    }

    public function test_a_rejected_transition_is_also_recorded(): void
    {
        $event = Event::factory()->create();

        $this->actingAsEms($this->superAdmin())
            ->postJson($this->url("events/{$event->uuid}/transitions"), ['action' => 'archive'])
            ->assertStatus(409);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ems.event.transition_rejected',
            'target_id' => $event->id,
        ]);
    }

    // -----------------------------------------------------------------
    // Authorization and discovery
    // -----------------------------------------------------------------

    public function test_transition_permissions_are_enforced_per_action(): void
    {
        // The organizer role holds complete but not archive.
        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $event = Event::factory()->organizedBy($organizer)->status(EventStatus::Live)->create();

        $this->actingAsEms($organizer)
            ->postJson($this->url("events/{$event->uuid}/transitions"), ['action' => 'complete'])
            ->assertOk();

        $this->actingAsEms($organizer)
            ->postJson($this->url("events/{$event->uuid}/transitions"), ['action' => 'archive'])
            ->assertForbidden();

        $this->assertSame(EventStatus::Completed, $event->fresh()->status);
    }

    public function test_an_organizer_cannot_transition_an_event_outside_their_scope(): void
    {
        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $other = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $event = Event::factory()->organizedBy($other)->create();

        $this->actingAsEms($organizer)
            ->postJson($this->url("events/{$event->uuid}/transitions"), ['action' => 'publish'])
            ->assertForbidden();

        $this->assertSame(EventStatus::Draft, $event->fresh()->status);
    }

    public function test_available_transitions_report_what_the_viewer_may_actually_do(): void
    {
        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $event = Event::factory()->organizedBy($organizer)->status(EventStatus::Completed)->create();

        $transitions = $this->actingAsEms($organizer)
            ->getJson($this->url("events/{$event->uuid}"))
            ->json('data.available_transitions');

        $this->assertCount(1, $transitions);
        $this->assertSame('archive', $transitions[0]['action']);

        // Legal from this state, but this organizer may not perform it, and
        // the API says so rather than hiding it.
        $this->assertFalse($transitions[0]['permitted']);
        $this->assertTrue($transitions[0]['irreversible']);
    }

    public function test_the_state_machine_is_published_for_the_frontend(): void
    {
        $response = $this->actingAsEms($this->superAdmin())
            ->getJson($this->url('events/lifecycle'));

        $response->assertOk();
        $response->assertJsonCount(count(EventStatus::cases()), 'data.states');
        $response->assertJsonCount(count(EventTransition::cases()), 'data.transitions');
        $response->assertJsonStructure([
            'data' => [
                'states' => [['value', 'label', 'tone']],
                'transitions' => [['action', 'label', 'from', 'to', 'permission', 'confirmation', 'irreversible']],
            ],
        ]);
    }

    // -----------------------------------------------------------------

    private function superAdmin(): User
    {
        return $this->emsUser(EmsRoles::SUPER_ADMIN);
    }

    private function transition(User $user, Event $event, string $action): void
    {
        $this->actingAsEms($user)
            ->postJson($this->url("events/{$event->uuid}/transitions"), ['action' => $action])
            ->assertOk();
    }
}
