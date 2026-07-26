<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Http\Requests\Notifications\StoreEventReminderRequest;
use App\Ems\Http\Requests\Notifications\UpdateEventReminderRequest;
use App\Ems\Http\Resources\EventReminderResource;
use App\Ems\Models\Event;
use App\Ems\Models\EventReminder;
use App\Ems\Services\Notifications\ReminderService;
use App\Ems\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class EventReminderController extends EmsController
{
    public function __construct(private readonly ReminderService $reminders)
    {
    }

    public function index(Event $event): JsonResponse
    {
        $this->authorize('viewNotifications', $event);

        return ApiResponse::success(
            EventReminderResource::collection($this->reminders->listForEvent($event)),
            'Reminders retrieved.'
        );
    }

    public function store(StoreEventReminderRequest $request, Event $event): JsonResponse
    {
        $this->authorize('manageNotifications', $event);

        $reminder = $this->reminders->create($event, $request->validated());

        return ApiResponse::created(
            new EventReminderResource($reminder),
            'Reminder created.'
        );
    }

    public function update(UpdateEventReminderRequest $request, Event $event, EventReminder $reminder): JsonResponse
    {
        $this->authorize('manageNotifications', $event);

        if ($reminder->event_id !== $event->id) {
            return ApiResponse::notFound('Reminder not found for this event.');
        }

        $reminder = $this->reminders->update($reminder, $request->validated());

        return ApiResponse::success(
            new EventReminderResource($reminder),
            'Reminder updated.'
        );
    }

    public function destroy(Event $event, EventReminder $reminder): JsonResponse
    {
        $this->authorize('manageNotifications', $event);

        if ($reminder->event_id !== $event->id) {
            return ApiResponse::notFound('Reminder not found for this event.');
        }

        $this->reminders->delete($reminder);

        return ApiResponse::deleted('Reminder deleted.');
    }
}
