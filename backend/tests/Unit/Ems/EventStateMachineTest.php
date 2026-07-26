<?php

namespace Tests\Unit\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\EventTransition;
use App\Ems\Support\EmsPermissions;
use PHPUnit\Framework\TestCase;

/**
 * The state machine in isolation, with no database or HTTP involved.
 *
 * These are the invariants the feature tests rely on, so a mistake in the
 * graph fails here first with a much clearer message.
 */
class EventStateMachineTest extends TestCase
{
    public function test_the_happy_path_forms_an_unbroken_chain(): void
    {
        $expected = [
            EventStatus::Draft,
            EventStatus::Published,
            EventStatus::RegistrationOpen,
            EventStatus::RegistrationClosed,
            EventStatus::Live,
            EventStatus::Completed,
            EventStatus::Archived,
        ];

        for ($i = 0; $i < count($expected) - 1; $i++) {
            $this->assertNotNull(
                EventTransition::between($expected[$i], $expected[$i + 1]),
                "Expected an edge from {$expected[$i]->value} to {$expected[$i + 1]->value}."
            );
        }
    }

    public function test_states_that_must_not_be_reachable_have_no_edge(): void
    {
        $forbidden = [
            [EventStatus::Draft, EventStatus::Completed],
            [EventStatus::Draft, EventStatus::Archived],
            [EventStatus::Draft, EventStatus::Live],
            [EventStatus::Draft, EventStatus::RegistrationOpen],
            [EventStatus::Completed, EventStatus::Live],
            [EventStatus::Completed, EventStatus::Draft],
            [EventStatus::Archived, EventStatus::Draft],
            [EventStatus::Archived, EventStatus::Published],
            [EventStatus::Published, EventStatus::Live],
            [EventStatus::RegistrationOpen, EventStatus::Live],
            [EventStatus::Live, EventStatus::Archived],
        ];

        foreach ($forbidden as [$from, $to]) {
            $this->assertNull(
                EventTransition::between($from, $to),
                "There must be no edge from {$from->value} to {$to->value}."
            );
        }
    }

    public function test_archived_is_terminal(): void
    {
        $this->assertTrue(EventStatus::Archived->isTerminal());
        $this->assertSame([], EventTransition::availableFrom(EventStatus::Archived));
    }

    public function test_a_draft_can_only_be_published(): void
    {
        $available = EventTransition::availableFrom(EventStatus::Draft);

        $this->assertCount(1, $available);
        $this->assertSame(EventTransition::Publish, $available[0]);
    }

    public function test_a_published_event_can_open_registration_go_back_to_draft_or_cancel(): void
    {
        $available = array_map(
            fn (EventTransition $t): string => $t->value,
            EventTransition::availableFrom(EventStatus::Published)
        );

        $this->assertEqualsCanonicalizing(['unpublish', 'open_registration', 'cancel'], $available);
    }

    public function test_cancel_is_available_from_active_states(): void
    {
        foreach ([
            EventStatus::Published,
            EventStatus::RegistrationOpen,
            EventStatus::RegistrationClosed,
            EventStatus::Live,
        ] as $status) {
            $this->assertContains(
                EventTransition::Cancel,
                EventTransition::availableFrom($status),
                "Cancel must be available from {$status->value}."
            );
        }

        $this->assertNotContains(
            EventTransition::Cancel,
            EventTransition::availableFrom(EventStatus::Draft)
        );
    }

    public function test_cancelled_is_terminal(): void
    {
        $this->assertTrue(EventStatus::Cancelled->isTerminal());
        $this->assertSame([], EventTransition::availableFrom(EventStatus::Cancelled));
        $this->assertFalse(EventStatus::Cancelled->isPubliclyVisible());
    }

    public function test_every_transition_maps_to_a_registered_permission(): void
    {
        foreach (EventTransition::cases() as $transition) {
            $this->assertContains(
                $transition->permission(),
                EmsPermissions::all(),
                "{$transition->value} requires a permission that is not in the registry."
            );
        }
    }

    public function test_every_transition_has_operator_facing_copy(): void
    {
        foreach (EventTransition::cases() as $transition) {
            $this->assertNotSame('', $transition->label());
            $this->assertNotSame('', $transition->confirmation());
        }
    }

    public function test_completing_archiving_and_cancelling_are_flagged_irreversible(): void
    {
        $this->assertTrue(EventTransition::Complete->isIrreversible());
        $this->assertTrue(EventTransition::Archive->isIrreversible());
        $this->assertTrue(EventTransition::Cancel->isIrreversible());
        $this->assertFalse(EventTransition::Publish->isIrreversible());
    }

    public function test_only_live_states_are_publicly_visible(): void
    {
        $this->assertFalse(EventStatus::Draft->isPubliclyVisible());
        $this->assertFalse(EventStatus::Archived->isPubliclyVisible());

        $this->assertTrue(EventStatus::Published->isPubliclyVisible());
        $this->assertTrue(EventStatus::RegistrationOpen->isPubliclyVisible());
        $this->assertTrue(EventStatus::RegistrationClosed->isPubliclyVisible());
        $this->assertTrue(EventStatus::Live->isPubliclyVisible());
        // Completed stays publicly discoverable so past-event browsing works.
        $this->assertTrue(EventStatus::Completed->isPubliclyVisible());
    }

    public function test_no_two_transitions_share_the_same_primary_edge(): void
    {
        // Cancel is multi-source; uniqueness is enforced on the (primary from, to) pair
        // used for describe()/legacy callers, plus full fromStatuses coverage elsewhere.
        $edges = array_map(
            fn (EventTransition $t): string => $t->fromStatus()->value . '->' . $t->toStatus()->value,
            array_filter(EventTransition::cases(), fn (EventTransition $t) => $t !== EventTransition::Cancel)
        );

        $this->assertSame(
            count($edges),
            count(array_unique($edges)),
            'Each state pair may have at most one named transition.'
        );
    }
}
