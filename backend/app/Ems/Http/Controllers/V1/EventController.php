<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Http\Requests\IndexEventRequest;
use App\Ems\Http\Requests\StoreEventRequest;
use App\Ems\Http\Requests\UpdateEventRequest;
use App\Ems\Http\Resources\EventResource;
use App\Ems\Models\Event;
use App\Ems\Services\EventService;
use App\Ems\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends EmsController
{
    public function __construct(private readonly EventService $events)
    {
    }

    /**
     * GET /api/v1/ems/events
     */
    public function index(IndexEventRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Event::class);

        $paginator = $this->events->paginate($request->user(), $request->filters());

        return ApiResponse::paginated($paginator, 'Events retrieved successfully.', EventResource::class);
    }

    /**
     * POST /api/v1/ems/events
     */
    public function store(StoreEventRequest $request): JsonResponse
    {
        $this->authorize('create', Event::class);

        $event = $this->events->create($request->validated(), $request->user());

        return ApiResponse::created(
            new EventResource($event),
            'Event created successfully.'
        );
    }

    /**
     * GET /api/v1/ems/events/{event}
     */
    public function show(Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $event->load(['category', 'organizer', 'creator'])->loadCount('registrations');

        return ApiResponse::success(new EventResource($event), 'Event retrieved successfully.');
    }

    /**
     * PUT /api/v1/ems/events/{event}
     */
    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $event = $this->events->update($event, $request->payload(), $request->user());

        return ApiResponse::success(new EventResource($event), 'Event updated successfully.');
    }

    /**
     * DELETE /api/v1/ems/events/{event}
     */
    public function destroy(Request $request, Event $event): JsonResponse
    {
        $this->authorize('delete', $event);

        $this->events->delete($event, $request->user());

        return ApiResponse::deleted('Event deleted successfully.');
    }
}
