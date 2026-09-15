<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Models\Event;
use App\Ems\Models\EventVolunteer;
use App\Ems\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventVolunteerController extends EmsController
{
    /**
     * POST /api/v1/ems/public/events/{slug}/volunteers
     * Public submission for event volunteer registration.
     */
    public function publicStore(Request $request, string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:180',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:32',
            'interests' => 'nullable|array',
            'interests.*' => 'string|max:50',
            'availability' => 'nullable|string|max:255',
            'experience' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        $email = strtolower($validated['email']);

        // Prevent duplicate pending applications for the same event and email
        $existingPending = EventVolunteer::where('event_id', $event->id)
            ->where('email', $email)
            ->where('status', 'pending')
            ->first();

        if ($existingPending) {
            return ApiResponse::error('You already have a pending volunteer application for this event.', [], 409);
        }

        $volunteer = EventVolunteer::create([
            'event_id' => $event->id,
            'user_id' => $request->user()?->id,
            'name' => $validated['name'],
            'email' => $email,
            'phone' => $validated['phone'] ?? null,
            'interests' => $validated['interests'] ?? [],
            'availability' => $validated['availability'] ?? null,
            'experience' => $validated['experience'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        return ApiResponse::created([
            'volunteer' => $volunteer,
            'event_name' => $event->name,
        ], 'Your volunteer application has been submitted successfully! We will contact you soon.');
    }

    /**
     * GET /api/v1/ems/admin/events/{eventId}/volunteers
     * List and filter volunteers for an event (Admin).
     */
    public function index(Request $request, int|string $eventId): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasRole('super-admin') && !$user->roles()->where('slug', 'super-admin')->exists() && !$user->hasPermissionTo('manage-events'))) {
            // Also allow if user is assigned to event team/organizer
        }

        $event = Event::findOrFail($eventId);

        $query = EventVolunteer::where('event_id', $event->id);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $volunteers = $query->orderBy('created_at', 'desc')->get();

        $metrics = [
            'total' => EventVolunteer::where('event_id', $event->id)->count(),
            'pending' => EventVolunteer::where('event_id', $event->id)->where('status', 'pending')->count(),
            'approved' => EventVolunteer::where('event_id', $event->id)->where('status', 'approved')->count(),
            'declined' => EventVolunteer::where('event_id', $event->id)->where('status', 'declined')->count(),
        ];

        return ApiResponse::success([
            'metrics' => $metrics,
            'volunteers' => $volunteers,
        ], 'Event volunteers retrieved.');
    }

    /**
     * PATCH /api/v1/ems/admin/events/{eventId}/volunteers/{volunteerId}/status
     * Update volunteer status and admin notes.
     */
    public function updateStatus(Request $request, int|string $eventId, int $volunteerId): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending,approved,declined',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $volunteer = EventVolunteer::where('event_id', $eventId)
            ->where('id', $volunteerId)
            ->firstOrFail();

        $volunteer->update([
            'status' => $validated['status'],
            'admin_notes' => $validated['admin_notes'] ?? $volunteer->admin_notes,
            'processed_by' => $request->user()?->id,
            'processed_at' => now(),
        ]);

        return ApiResponse::success($volunteer, 'Volunteer status updated successfully.');
    }

    /**
     * GET /api/v1/ems/admin/events/{eventId}/volunteers/export
     * CSV export of event volunteers.
     */
    public function exportCsv(Request $request, int|string $eventId): StreamedResponse
    {
        $event = Event::findOrFail($eventId);
        $volunteers = EventVolunteer::where('event_id', $event->id)->orderBy('created_at', 'desc')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="volunteers-event-' . $event->id . '.csv"',
        ];

        $callback = function () use ($volunteers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Name', 'Email', 'Phone', 'Interests', 'Availability', 'Experience', 'Notes', 'Status', 'Admin Notes', 'Applied At']);

            foreach ($volunteers as $v) {
                fputcsv($file, [
                    $v->id,
                    $v->name,
                    $v->email,
                    $v->phone ?? '',
                    is_array($v->interests) ? implode(', ', $v->interests) : ($v->interests ?? ''),
                    $v->availability ?? '',
                    $v->experience ?? '',
                    $v->notes ?? '',
                    $v->status,
                    $v->admin_notes ?? '',
                    $v->created_at?->toIso8601String(),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
