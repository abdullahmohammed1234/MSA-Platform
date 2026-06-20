<?php

namespace App\Http\Controllers\Admin\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\CMS\SaveEventRequest;
use App\Models\CMS\Event;
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
                'student_id' => $registration->student_id,
                'status' => $registration->status,
                'registered_at' => $registration->created_at?->toIso8601String(),
            ])->values(),
            'current_page' => $registrations->currentPage(),
            'last_page' => $registrations->lastPage(),
            'per_page' => $registrations->perPage(),
            'total' => $registrations->total(),
        ]);
    }
}
