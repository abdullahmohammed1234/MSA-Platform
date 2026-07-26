<?php

namespace App\Ems\Services;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\EventTransition;
use App\Ems\Events\EventStatusChanged;
use App\Ems\Exceptions\InvalidEventTransitionException;
use App\Ems\Models\Event;
use App\Ems\Services\Notifications\EventCancellationService;
use App\Ems\Services\Notifications\PostEventCommunicationService;
use App\Ems\Services\Notifications\ReminderService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The single authority on event state changes.
 *
 * Nothing else in the EMS may write Event::status. Every caller — controller,
 * console command or future job — goes through apply(), which validates the
 * edge against the EventTransition state machine, stamps the matching
 * lifecycle timestamp and dispatches EventStatusChanged.
 *
 * Extending the lifecycle in a later phase (an administrative "reopen", say)
 * means adding a case to EventTransition. This service does not change.
 */
class EventLifecycleService
{
    public function __construct(
        private readonly EmsActivityLogger $activityLogger,
        private readonly EventCancellationService $cancellations,
        private readonly PostEventCommunicationService $postEvent,
        private readonly ReminderService $reminders,
    ) {
    }

    /**
     * Apply a transition to an event.
     *
     * @throws InvalidEventTransitionException when the edge does not exist
     */
    public function apply(Event $event, EventTransition $transition, ?User $actor = null): Event
    {
        $from = $event->status;

        if (! $this->canApply($event, $transition)) {
            $this->activityLogger->failed(
                'event.transition_rejected',
                $event,
                sprintf(
                    'Rejected transition "%s" on event "%s" from state "%s".',
                    $transition->value,
                    $event->name,
                    $from->value
                ),
                ['transition' => $transition->value, 'from' => $from->value],
            );

            throw InvalidEventTransitionException::forTransition($from, $transition);
        }

        $to = $transition->toStatus();

        DB::transaction(function () use ($event, $to, $transition, $actor): void {
            $event->status = $to;
            $event->updated_by = $actor?->id ?? $event->updated_by;

            foreach ($this->timestampsFor($transition) as $column => $value) {
                $event->{$column} = $value;
            }

            $event->save();
        });

        EventStatusChanged::dispatch($event->refresh(), $from, $to, $transition, $actor);

        if ($transition === EventTransition::Cancel) {
            $this->cancellations->handleCancelled($event);
        }

        if ($transition === EventTransition::Complete) {
            $this->postEvent->handleCompleted($event);
        }

        if ($transition === EventTransition::OpenRegistration) {
            $this->reminders->seedDefaults($event);
        }

        return $event;
    }

    /**
     * Whether the transition is legal from the event's current state. This is
     * a state-machine question only; permissions are the policy's job.
     */
    public function canApply(Event $event, EventTransition $transition): bool
    {
        return in_array($event->status, $transition->fromStatuses(), true);
    }

    /**
     * The transitions legal from this event's state that the given user is
     * also permitted to perform. Drives the lifecycle panel's action buttons.
     *
     * @return array<int, EventTransition>
     */
    public function availableTo(Event $event, ?User $user): array
    {
        $transitions = EventTransition::availableFrom($event->status);

        if ($user === null) {
            return [];
        }

        return array_values(array_filter(
            $transitions,
            fn (EventTransition $transition): bool => $user->hasPermission($transition->permission())
        ));
    }

    /**
     * Lifecycle timestamps stamped as a side effect of a transition.
     *
     * `registration_closed_at` is deliberately cleared when registration
     * reopens so the column always describes the current cycle.
     *
     * @return array<string, \Illuminate\Support\Carbon|null>
     */
    private function timestampsFor(EventTransition $transition): array
    {
        return match ($transition) {
            EventTransition::Publish => ['published_at' => now()],
            EventTransition::Unpublish => ['published_at' => null],
            EventTransition::OpenRegistration => [
                'registration_open_at' => now(),
                'registration_closed_at' => null,
            ],
            EventTransition::CloseRegistration => ['registration_closed_at' => now()],
            EventTransition::MarkLive => [],
            EventTransition::Complete => ['completed_at' => now()],
            EventTransition::Archive => ['archived_at' => now()],
            EventTransition::Cancel => ['cancelled_at' => now()],
        };
    }

    /**
     * A serialisable description of the whole state machine, published at
     * GET /api/v1/ems/events/lifecycle so the frontend never hard-codes the
     * graph.
     *
     * @return array<string, mixed>
     */
    public function describe(): array
    {
        return [
            'states' => EventStatus::options(),
            'transitions' => array_map(fn (EventTransition $transition): array => [
                'action' => $transition->value,
                'label' => $transition->label(),
                'from' => $transition->fromStatus()->value,
                'from_states' => array_map(fn (EventStatus $s) => $s->value, $transition->fromStatuses()),
                'to' => $transition->toStatus()->value,
                'permission' => $transition->permission(),
                'confirmation' => $transition->confirmation(),
                'irreversible' => $transition->isIrreversible(),
            ], EventTransition::cases()),
        ];
    }
}
