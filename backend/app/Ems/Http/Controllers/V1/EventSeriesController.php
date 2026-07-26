<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Models\Event;
use App\Ems\Models\EventSeries;
use App\Ems\Support\ApiResponse;
use App\Ems\Enums\EventStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventSeriesController extends EmsController
{
    /**
     * GET /api/v1/ems/event-series
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EventSeries::class);

        $series = EventSeries::withCount('events')->get();

        return ApiResponse::success($series, 'Event series retrieved.');
    }

    /**
     * POST /api/v1/ems/event-series
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', EventSeries::class);

        $validated = $request->validate([
            'name' => 'required|string|max:180',
            'description' => 'nullable|string',
            'recurrence_pattern' => 'required|string|in:daily,weekly,monthly,custom',
            'recurrence_interval' => 'integer|min:1',
            'recurrence_days' => 'nullable|array',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            
            // Base Event fields to clone to occurrences
            'category_id' => 'nullable|integer|exists:ems_event_categories,id',
            'location' => 'nullable|string|max:255',
            'timezone' => 'nullable|string|max:64',
            'capacity' => 'nullable|integer|min:1',
            'waitlist_enabled' => 'boolean',
            'max_tickets_per_order' => 'nullable|integer|min:1',
            'max_registrations_per_attendee' => 'nullable|integer|min:1',
            'is_public' => 'boolean',
            'start_time' => 'required|string', // e.g. "18:00"
            'end_time' => 'nullable|string',   // e.g. "20:00"
        ]);

        $series = DB::transaction(function () use ($validated, $request) {
            $series = EventSeries::create([
                'uuid' => (string) Str::uuid(),
                'name' => $validated['name'],
                'description' => $validated['description'],
                'recurrence_pattern' => $validated['recurrence_pattern'],
                'recurrence_interval' => $validated['recurrence_interval'] ?? 1,
                'recurrence_days' => $validated['recurrence_days'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'created_by' => $request->user()?->id,
            ]);

            // Generate occurrences dates
            $dates = $this->generateDates(
                Carbon::parse($validated['start_date']),
                Carbon::parse($validated['end_date']),
                $validated['recurrence_pattern'],
                $validated['recurrence_interval'] ?? 1,
                $validated['recurrence_days'] ?? []
            );

            // Create Event occurrences (up to 100 limit)
            $count = 0;
            foreach ($dates as $date) {
                if ($count >= 100) break;

                $startAt = Carbon::parse($date->format('Y-m-d') . ' ' . $validated['start_time']);
                $endAt = !empty($validated['end_time']) 
                    ? Carbon::parse($date->format('Y-m-d') . ' ' . $validated['end_time']) 
                    : (clone $startAt)->addHours(2);

                $event = new Event();
                $event->fill([
                    'uuid' => (string) Str::uuid(),
                    'name' => $series->name . ' - ' . $date->format('M d, Y'),
                    'slug' => Str::slug($series->name) . '-' . $date->format('Y-m-d') . '-' . Str::random(4),
                    'description' => $series->description,
                    'category_id' => $validated['category_id'] ?? null,
                    'location' => $validated['location'] ?? null,
                    'timezone' => $validated['timezone'] ?? config('ems.defaults.timezone', 'America/Vancouver'),
                    'capacity' => $validated['capacity'] ?? null,
                    'waitlist_enabled' => $validated['waitlist_enabled'] ?? false,
                    'max_tickets_per_order' => $validated['max_tickets_per_order'] ?? null,
                    'max_registrations_per_attendee' => $validated['max_registrations_per_attendee'] ?? 1,
                    'is_public' => $validated['is_public'] ?? false,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'status' => EventStatus::Draft->value,
                    'series_id' => $series->id,
                    'organizer_id' => $request->user()?->id,
                    'created_by' => $request->user()?->id,
                    'updated_by' => $request->user()?->id,
                ]);
                $event->save();
                $count++;
            }

            return $series;
        });

        $series->load('events');

        return ApiResponse::created($series, 'Event series and occurrences created.');
    }

    /**
     * GET /api/v1/ems/event-series/{series}
     */
    public function show(EventSeries $series): JsonResponse
    {
        $this->authorize('view', $series);

        $series->load(['events' => fn($q) => $q->orderBy('start_at', 'asc')]);

        return ApiResponse::success($series, 'Event series details.');
    }

    /**
     * PUT /api/v1/ems/event-series/{series}
     * Propagates changes to ALL events in the series
     */
    public function update(Request $request, EventSeries $series): JsonResponse
    {
        $this->authorize('update', $series);

        $validated = $request->validate([
            'name' => 'required|string|max:180',
            'description' => 'nullable|string',
            'category_id' => 'nullable|integer|exists:ems_event_categories,id',
            'location' => 'nullable|string|max:255',
            'timezone' => 'nullable|string|max:64',
            'capacity' => 'nullable|integer|min:1',
            'waitlist_enabled' => 'boolean',
            'max_tickets_per_order' => 'nullable|integer|min:1',
            'max_registrations_per_attendee' => 'nullable|integer|min:1',
            'is_public' => 'boolean',
        ]);

        DB::transaction(function () use ($series, $validated) {
            $series->update([
                'name' => $validated['name'],
                'description' => $validated['description'],
            ]);

            // Update all child events
            foreach ($series->events as $event) {
                $event->update([
                    'category_id' => $validated['category_id'] ?? $event->category_id,
                    'location' => $validated['location'] ?? $event->location,
                    'timezone' => $validated['timezone'] ?? $event->timezone,
                    'capacity' => $validated['capacity'] ?? $event->capacity,
                    'waitlist_enabled' => $validated['waitlist_enabled'] ?? $event->waitlist_enabled,
                    'max_tickets_per_order' => $validated['max_tickets_per_order'] ?? $event->max_tickets_per_order,
                    'max_registrations_per_attendee' => $validated['max_registrations_per_attendee'] ?? $event->max_registrations_per_attendee,
                    'is_public' => $validated['is_public'] ?? $event->is_public,
                ]);
            }
        });

        $series->load('events');

        return ApiResponse::success($series, 'Event series and all occurrences updated.');
    }

    /**
     * PUT /api/v1/ems/event-series/{series}/events/{event}
     * Propagates changes to FUTURE events in the series starting from this occurrence
     */
    public function updateFuture(Request $request, EventSeries $series, Event $event): JsonResponse
    {
        $this->authorize('update', $series);

        $validated = $request->validate([
            'location' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:1',
            'waitlist_enabled' => 'boolean',
            'is_public' => 'boolean',
        ]);

        DB::transaction(function () use ($series, $event, $validated) {
            // Find all events in this series starting on or after the target event's start_at date
            $futureEvents = $series->events()
                ->where('start_at', '>=', $event->start_at)
                ->get();

            foreach ($futureEvents as $future) {
                $future->update([
                    'location' => $validated['location'] ?? $future->location,
                    'capacity' => $validated['capacity'] ?? $future->capacity,
                    'waitlist_enabled' => $validated['waitlist_enabled'] ?? $future->waitlist_enabled,
                    'is_public' => $validated['is_public'] ?? $future->is_public,
                ]);
            }
        });

        $series->load('events');

        return ApiResponse::success($series, 'Future occurrences updated successfully.');
    }

    /**
     * DELETE /api/v1/ems/event-series/{series}
     * Cancels the whole series (marks events as Cancelled)
     */
    public function destroy(EventSeries $series): JsonResponse
    {
        $this->authorize('delete', $series);

        DB::transaction(function () use ($series) {
            foreach ($series->events as $event) {
                if ($event->status !== EventStatus::Completed) {
                    $event->status = EventStatus::Cancelled;
                    $event->cancelled_at = now();
                    $event->save();
                }
            }
        });

        return ApiResponse::success(null, 'Event series cancelled.');
    }

    /**
     * Helper to generate dates based on recurrence pattern
     */
    private function generateDates(Carbon $start, Carbon $end, string $pattern, int $interval, array $days): array
    {
        $dates = [];
        $curr = clone $start;

        if ($pattern === 'daily') {
            while ($curr->lessThanOrEqualTo($end)) {
                $dates[] = clone $curr;
                $curr->addDays($interval);
            }
        } elseif ($pattern === 'weekly') {
            // If specific days of week are selected, we evaluate each week
            if (!empty($days)) {
                $lowercaseDays = array_map('strtolower', $days);
                while ($curr->lessThanOrEqualTo($end)) {
                    for ($i = 0; $i < 7; $i++) {
                        $evalDate = (clone $curr)->addDays($i);
                        if ($evalDate->greaterThan($end)) break;
                        
                        $dayName = strtolower($evalDate->format('l'));
                        if (in_array($dayName, $lowercaseDays, true)) {
                            // Only add if it's on or after the start date
                            if ($evalDate->greaterThanOrEqualTo($start)) {
                                $dates[] = clone $evalDate;
                            }
                        }
                    }
                    $curr->addWeeks($interval);
                }
            } else {
                while ($curr->lessThanOrEqualTo($end)) {
                    $dates[] = clone $curr;
                    $curr->addWeeks($interval);
                }
            }
        } elseif ($pattern === 'monthly') {
            while ($curr->lessThanOrEqualTo($end)) {
                $dates[] = clone $curr;
                $curr->addMonths($interval);
            }
        } else { // custom intervals (e.g. custom intervals of X days)
            while ($curr->lessThanOrEqualTo($end)) {
                $dates[] = clone $curr;
                $curr->addDays($interval);
            }
        }

        return $dates;
    }
}
