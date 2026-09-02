<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Enums\NotificationType;
use App\Ems\Http\Controllers\EmsController;
use App\Ems\Http\Requests\Notifications\ResendNotificationRequest;
use App\Ems\Http\Resources\EventNotificationResource;
use App\Ems\Models\Event;
use App\Ems\Models\EventNotification;
use App\Ems\Models\Registration;
use App\Ems\Services\Notifications\EventCommunicationService;
use App\Ems\Services\Notifications\NotificationHistoryService;
use App\Ems\Services\Notifications\QueuedEventNotificationDispatcher;
use App\Ems\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EventNotificationController extends EmsController
{
    public function __construct(
        private readonly NotificationHistoryService $history,
        private readonly EventCommunicationService $communications,
        private readonly QueuedEventNotificationDispatcher $dispatcher,
    ) {
    }

    public function summary(Event $event): JsonResponse
    {
        $this->authorize('viewNotifications', $event);

        return ApiResponse::success(
            $this->history->summary($event),
            'Notification summary retrieved.'
        );
    }

    public function index(Request $request, Event $event): JsonResponse
    {
        $this->authorize('viewNotifications', $event);

        $paginator = $this->history->paginateForEvent($event, $request->query());

        return ApiResponse::paginated(
            $paginator,
            'Notifications retrieved.',
            EventNotificationResource::class
        );
    }

    public function show(EventNotification $notification): JsonResponse
    {
        $notification->loadMissing(['event', 'registration']);

        if ($notification->event === null) {
            return ApiResponse::notFound('Notification not found.');
        }

        $this->authorize('viewNotifications', $notification->event);

        return ApiResponse::success(
            new EventNotificationResource($notification),
            'Notification retrieved.'
        );
    }

    public function resend(ResendNotificationRequest $request, Event $event): JsonResponse
    {
        $this->authorize('sendNotifications', $event);

        $registration = Registration::query()
            ->where('uuid', $request->validated('registration_uuid'))
            ->where('event_id', $event->id)
            ->firstOrFail();

        $type = NotificationType::from($request->validated('type'));

        $this->communications->resend($registration->loadMissing([
            'event', 'tickets', 'ticketType', 'order', 'settledPayment',
        ]), $type);

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.notifications.manual_resend', [
                'event_uuid' => $event->uuid,
                'registration_uuid' => $registration->uuid,
                'type' => $type->value,
                'actor_id' => $request->user()?->id,
            ]);

        return ApiResponse::success(null, 'Notification queued for resend.');
    }

    public function retry(Event $event, EventNotification $notification): JsonResponse
    {
        $this->authorize('sendNotifications', $event);

        if ($notification->event_id !== $event->id) {
            return ApiResponse::notFound('Notification not found for this event.');
        }

        $retried = $this->dispatcher->retry($notification);

        return ApiResponse::success(
            new EventNotificationResource($retried),
            'Notification queued for retry.'
        );
    }

    public function all(Request $request): JsonResponse
    {
        $this->authorize('viewAnyNotifications', Event::class);

        $paginator = $this->history->paginateAll($request->query());

        return ApiResponse::paginated(
            $paginator,
            'All notifications retrieved.',
            EventNotificationResource::class
        );
    }

    public function retryGlobal(Request $request, EventNotification $notification): JsonResponse
    {
        if ($notification->event !== null) {
            $this->authorize('sendNotifications', $notification->event);
        } else {
            if (! $request->user()->hasPermission(\App\Ems\Support\EmsPermissions::NOTIFICATIONS_SEND)) {
                abort(403, 'Unauthorized.');
            }
        }

        $retried = $this->dispatcher->retry($notification);

        return ApiResponse::success(
            new EventNotificationResource($retried),
            'Notification queued for retry.'
        );
    }
}
