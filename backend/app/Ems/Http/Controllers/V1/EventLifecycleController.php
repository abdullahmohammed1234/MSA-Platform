<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Http\Requests\TransitionEventRequest;
use App\Ems\Http\Resources\EventResource;
use App\Ems\Models\Event;
use App\Ems\Services\EventLifecycleService;
use App\Ems\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Event lifecycle transitions.
 *
 * A separate endpoint from the event update route on purpose: status is not an
 * editable field, it is the outcome of a named, authorized, validated
 * transition. Both checks happen here regardless of what the UI offered:
 * the policy confirms the caller may perform the action, and the lifecycle
 * service confirms the edge exists.
 */
class EventLifecycleController extends EmsController
{
    public function __construct(private readonly EventLifecycleService $lifecycle)
    {
    }

    /**
     * POST /api/v1/ems/events/{event}/transitions
     */
    public function store(TransitionEventRequest $request, Event $event): JsonResponse
    {
        $transition = $request->transition();

        $this->authorize('transition', [$event, $transition]);

        // Throws InvalidEventTransitionException (409) if the edge is illegal.
        $event = $this->lifecycle->apply($event, $transition, $request->user());

        $event->load(['category', 'organizer']);

        return ApiResponse::success(
            new EventResource($event),
            sprintf('Event is now %s.', $event->status->label())
        );
    }

    /**
     * GET /api/v1/ems/events/lifecycle
     *
     * Publishes the state machine so the frontend renders it from data rather
     * than from a second copy of the rules.
     */
    public function describe(): JsonResponse
    {
        $this->authorize('viewAny', Event::class);

        return ApiResponse::success($this->lifecycle->describe(), 'Event lifecycle retrieved successfully.');
    }
}
