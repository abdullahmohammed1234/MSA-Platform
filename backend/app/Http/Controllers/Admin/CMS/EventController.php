<?php

namespace App\Http\Controllers\Admin\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\CMS\SaveEventRequest;
use App\Models\CMS\Event;
use App\Models\CMS\EventRegistration;
use App\Services\CMS\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class EventController extends Controller
{
    protected $service;

    public function __construct(EventService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status', 'category', 'featured']);
        $events = $this->service->list($filters, $request->input('per_page', 15));

        return response()->json($events);
    }

    public function store(SaveEventRequest $request): JsonResponse
    {
        $event = $this->service->create($request->validated(), Auth::id());
        Cache::forget('website_events');

        return response()->json([
            'success' => true,
            'message' => 'Event created successfully.',
            'event' => $event
        ], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $event = $this->service->findByUuid($uuid);
        if (!$event) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        return response()->json($event);
    }

    public function update(SaveEventRequest $request, string $uuid): JsonResponse
    {
        $event = $this->service->findByUuid($uuid);
        if (!$event) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        $this->service->update($event, $request->validated(), Auth::id());
        Cache::forget('website_events');

        return response()->json([
            'success' => true,
            'message' => 'Event updated successfully.',
            'event' => $event
        ]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $event = $this->service->findByUuid($uuid);
        if (!$event) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        $this->service->delete($event, Auth::id());
        Cache::forget('website_events');

        return response()->json([
            'success' => true,
            'message' => 'Event deleted successfully.'
        ]);
    }

    public function revisions(string $uuid): JsonResponse
    {
        $event = $this->service->findByUuid($uuid);
        if (!$event) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        return response()->json([
            'revisions' => $this->service->getRevisions($event)
        ]);
    }

    public function rollback(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['version' => 'required|integer']);

        $event = $this->service->findByUuid($uuid);
        if (!$event) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        $rolledBack = $this->service->rollback($event, $request->input('version'), Auth::id());

        if (!$rolledBack) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to rollback. Version not found.'
            ], 400);
        }

        Cache::forget('website_events');

        return response()->json([
            'success' => true,
            'message' => 'Event rolled back successfully.',
            'event' => $event->fresh()
        ]);
    }

    public function registrations(Request $request, string $uuid): JsonResponse
    {
        $event = $this->service->findByUuid($uuid);
        if (! $event) {
            return response()->json(['message' => 'Event not found.'], 404);
        }

        $registrations = $this->service->getRegistrations(
            $event,
            $request->integer('per_page', 25)
        );

        return response()->json([
            'event' => [
                'uuid' => $event->uuid,
                'title' => $event->title,
                'spots_left' => $event->spots_left,
                'registrations_count' => $registrations->total(),
            ],
            'data' => $registrations->getCollection()->map(fn ($registration) => [
                'uuid' => $registration->uuid,
                'first_name' => $registration->first_name,
                'last_name' => $registration->last_name,
                'full_name' => trim($registration->first_name.' '.$registration->last_name),
                'email' => $registration->email,
                'phone' => $registration->phone,
                'student_id' => $registration->student_id,
                'status' => $registration->status,
                'checked_in_at' => $registration->checked_in_at?->toIso8601String(),
                'registered_at' => $registration->created_at?->toIso8601String(),
            ])->values(),
            'current_page' => $registrations->currentPage(),
            'last_page' => $registrations->lastPage(),
            'per_page' => $registrations->perPage(),
            'total' => $registrations->total(),
        ]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:255',
            'eventUuid' => 'nullable|uuid',
        ]);

        $parsed = app(\App\Services\CMS\EventCheckInQrService::class)
            ->parsePayload($validated['code']);

        if (! $parsed) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid QR code. Please scan a valid event registration QR.',
            ], 422);
        }

        $registrationUuid = $parsed['registrationUuid'];
        $qrEventUuid = $parsed['eventUuid'];
        $requestedEventUuid = $validated['eventUuid'] ?? $qrEventUuid;

        // Resolve against the event's registrant list using the hidden check-in code.
        $registrationQuery = EventRegistration::query()
            ->with('event')
            ->where('uuid', $registrationUuid);

        if ($requestedEventUuid) {
            $registrationQuery->whereHas('event', function ($query) use ($requestedEventUuid) {
                $query->where('uuid', $requestedEventUuid);
            });
        }

        $registration = $registrationQuery->first();

        if (! $registration || ! $registration->event) {
            return response()->json([
                'success' => false,
                'message' => $requestedEventUuid
                    ? 'This check-in code was not found in that event\'s registration list.'
                    : 'No registration was found for this QR code.',
                'eventUuid' => $requestedEventUuid,
            ], 404);
        }

        // If QR embeds an event id, it must match the registration's event.
        if ($qrEventUuid && $registration->event->uuid !== $qrEventUuid) {
            return response()->json([
                'success' => false,
                'message' => 'This QR code belongs to a different event.',
                'eventUuid' => $registration->event->uuid,
                'registration' => $this->registrationSummary($registration),
            ], 422);
        }

        if (! empty($validated['eventUuid']) && $registration->event->uuid !== $validated['eventUuid']) {
            return response()->json([
                'success' => false,
                'message' => 'This QR code belongs to a different event.',
                'eventUuid' => $registration->event->uuid,
                'registration' => $this->registrationSummary($registration),
            ], 422);
        }

        if ($registration->status === EventRegistration::STATUS_CANCELLED) {
            return response()->json([
                'success' => false,
                'message' => 'This registration was cancelled.',
                'eventUuid' => $registration->event->uuid,
                'registration' => $this->registrationSummary($registration),
            ], 422);
        }

        if ($registration->status === EventRegistration::STATUS_ATTENDING) {
            return response()->json([
                'success' => true,
                'alreadyCheckedIn' => true,
                'message' => $registration->full_name.' is already marked as attending.',
                'eventUuid' => $registration->event->uuid,
                'registration' => $this->registrationSummary($registration),
            ]);
        }

        try {
            $registration->markAttending();
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::error('Event check-in save failed', [
                'error' => $exception->getMessage(),
                'registration_uuid' => $registration->uuid,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not save check-in to the database. Please run migrations and try again.',
            ], 500);
        }

        $registration->refresh()->load('event');

        return response()->json([
            'success' => true,
            'alreadyCheckedIn' => false,
            'message' => $registration->full_name.' checked in successfully.',
            'eventUuid' => $registration->event->uuid,
            'registration' => $this->registrationSummary($registration),
        ]);
    }

    public function recentCheckIns(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eventUuid' => 'nullable|uuid',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $limit = $validated['limit'] ?? 40;

        $query = EventRegistration::query()
            ->with('event:id,uuid,title')
            ->where('status', EventRegistration::STATUS_ATTENDING);

        if (! empty($validated['eventUuid'])) {
            $query->whereHas('event', function ($eventQuery) use ($validated) {
                $eventQuery->where('uuid', $validated['eventUuid']);
            });
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('event_registrations', 'checked_in_at')) {
            $query->orderByDesc('checked_in_at');
        }

        $checkIns = $query
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (EventRegistration $registration) => [
                'uuid' => $registration->uuid,
                'full_name' => $registration->full_name,
                'email' => $registration->email,
                'phone' => $registration->phone,
                'status' => $registration->status,
                'checked_in_at' => $registration->checked_in_at?->toIso8601String()
                    ?? $registration->updated_at?->toIso8601String(),
                'event' => [
                    'uuid' => $registration->event?->uuid,
                    'title' => $registration->event?->title,
                ],
            ])
            ->values();

        return response()->json([
            'data' => $checkIns,
            'total' => $checkIns->count(),
        ]);
    }

    private function registrationSummary(EventRegistration $registration): array
    {
        return [
            'uuid' => $registration->uuid,
            'full_name' => $registration->full_name,
            'email' => $registration->email,
            'phone' => $registration->phone,
            'status' => $registration->status,
            'checked_in_at' => $registration->checked_in_at?->toIso8601String(),
            'event' => [
                'uuid' => $registration->event?->uuid,
                'title' => $registration->event?->title,
            ],
        ];
    }
}
