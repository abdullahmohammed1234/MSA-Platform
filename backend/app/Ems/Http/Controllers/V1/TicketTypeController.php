<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Http\Requests\ReorderTicketTypesRequest;
use App\Ems\Http\Requests\StoreTicketTypeRequest;
use App\Ems\Http\Requests\UpdateTicketTypeRequest;
use App\Ems\Http\Resources\TicketTypeResource;
use App\Ems\Models\Event;
use App\Ems\Models\TicketType;
use App\Ems\Services\EventPaymentSummaryService;
use App\Ems\Services\TicketTypeService;
use App\Ems\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TicketTypeController extends EmsController
{
    public function __construct(
        private readonly TicketTypeService $ticketTypes,
        private readonly EventPaymentSummaryService $summaries,
    ) {
    }

    /**
     * GET /api/v1/ems/events/{event}/tickets
     */
    public function index(Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        return ApiResponse::success(
            TicketTypeResource::collection($this->ticketTypes->listForEvent($event)),
            'Ticket types retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/ems/events/{event}/tickets
     */
    public function store(StoreTicketTypeRequest $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $ticketType = $this->ticketTypes->create($event, $request->validated(), $request->user());

        return ApiResponse::created(
            new TicketTypeResource($ticketType),
            'Ticket type created successfully.'
        );
    }

    /**
     * GET /api/v1/ems/events/{event}/tickets/{ticketType}
     */
    public function show(Event $event, TicketType $ticketType): JsonResponse
    {
        $this->authorize('view', $event);
        $this->assertBelongsToEvent($event, $ticketType);

        return ApiResponse::success(
            new TicketTypeResource($ticketType),
            'Ticket type retrieved successfully.'
        );
    }

    /**
     * PUT /api/v1/ems/events/{event}/tickets/{ticketType}
     */
    public function update(UpdateTicketTypeRequest $request, Event $event, TicketType $ticketType): JsonResponse
    {
        $this->authorize('update', $event);
        $this->assertBelongsToEvent($event, $ticketType);

        $ticketType = $this->ticketTypes->update($ticketType, $request->validated(), $request->user());

        return ApiResponse::success(
            new TicketTypeResource($ticketType),
            'Ticket type updated successfully.'
        );
    }

    /**
     * POST /api/v1/ems/events/{event}/tickets/{ticketType}/disable
     */
    public function disable(Event $event, TicketType $ticketType): JsonResponse
    {
        $this->authorize('update', $event);
        $this->assertBelongsToEvent($event, $ticketType);

        return ApiResponse::success(
            new TicketTypeResource($this->ticketTypes->disable($ticketType, request()->user())),
            'Ticket type disabled successfully.'
        );
    }

    /**
     * POST /api/v1/ems/events/{event}/tickets/{ticketType}/duplicate
     */
    public function duplicate(Event $event, TicketType $ticketType): JsonResponse
    {
        $this->authorize('update', $event);
        $this->assertBelongsToEvent($event, $ticketType);

        return ApiResponse::created(
            new TicketTypeResource($this->ticketTypes->duplicate($ticketType, request()->user())),
            'Ticket type duplicated successfully.'
        );
    }

    /**
     * POST /api/v1/ems/events/{event}/tickets/reorder
     */
    public function reorder(ReorderTicketTypesRequest $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $items = $this->ticketTypes->reorder(
            $event,
            $request->validated('ordered_uuids'),
            $request->user()
        );

        return ApiResponse::success(
            TicketTypeResource::collection($items),
            'Ticket types reordered successfully.'
        );
    }

    /**
     * DELETE /api/v1/ems/events/{event}/tickets/{ticketType}
     */
    public function destroy(Event $event, TicketType $ticketType): JsonResponse
    {
        $this->authorize('update', $event);
        $this->assertBelongsToEvent($event, $ticketType);

        $this->ticketTypes->delete($ticketType, request()->user());

        return ApiResponse::deleted('Ticket type deleted successfully.');
    }

    /**
     * GET /api/v1/ems/events/{event}/payment-summary
     */
    public function paymentSummary(Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        return ApiResponse::success(
            $this->summaries->summarize($event),
            'Payment summary retrieved successfully.'
        );
    }

    private function assertBelongsToEvent(Event $event, TicketType $ticketType): void
    {
        abort_unless($ticketType->event_id === $event->id, 404);
    }
}
