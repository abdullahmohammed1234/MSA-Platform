<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Models\Event;
use App\Ems\Models\EventFeedback;
use App\Ems\Models\Registration;
use App\Ems\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FeedbackController extends EmsController
{
    /**
     * POST /api/v1/ems/events/{event}/feedback
     * Submit feedback for an event. Available to registered attendees.
     */
    public function store(Request $request, Event $event): JsonResponse
    {
        $this->authorize('create', EventFeedback::class);

        $validated = $request->validate([
            'overall_rating' => 'required|integer|min:1|max:5',
            'organization_rating' => 'required|integer|min:1|max:5',
            'program_rating' => 'required|integer|min:1|max:5',
            'venue_rating' => 'required|integer|min:1|max:5',
            'text_feedback' => 'nullable|string|max:1000',
            'is_anonymous' => 'boolean',
            'email' => 'nullable|email', // optional identifier if guest
        ]);

        $user = $request->user();
        $email = $user ? $user->email : ($validated['email'] ?? null);

        if (!$email) {
            return response()->json([
                'success' => false,
                'message' => 'Email is required for anonymous/guest feedback submission validation.',
            ], 422);
        }

        // 1. Verify confirmed registration
        $registration = Registration::where('event_id', $event->id)
            ->where('status', 'confirmed')
            ->where(function ($q) use ($user, $email) {
                $q->where('attendee_email', strtolower($email));
                if ($user) {
                    $q->orWhere('user_id', $user->id);
                }
            })
            ->first();

        if (!$registration) {
            return response()->json([
                'success' => false,
                'message' => 'You must have a confirmed registration for this event to submit feedback.',
            ], 403);
        }

        // 2. Check if already submitted
        $exists = EventFeedback::where('event_id', $event->id)
            ->where(function ($q) use ($user, $registration) {
                $q->where('registration_id', $registration->id);
                if ($user) {
                    $q->orWhere('user_id', $user->id);
                }
            })
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'You have already submitted feedback for this event.',
            ], 409);
        }

        $feedback = EventFeedback::create([
            'uuid' => (string) Str::uuid(),
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'user_id' => $validated['is_anonymous'] ? null : ($user ? $user->id : null),
            'is_anonymous' => $validated['is_anonymous'] ?? false,
            'overall_rating' => $validated['overall_rating'],
            'organization_rating' => $validated['organization_rating'],
            'program_rating' => $validated['program_rating'],
            'venue_rating' => $validated['venue_rating'],
            'text_feedback' => $validated['text_feedback'] ?? null,
        ]);

        return ApiResponse::created($feedback, 'Feedback submitted successfully.');
    }

    /**
     * GET /api/v1/ems/events/{event}/feedback
     * Retrieve aggregated feedback analytics for an event.
     */
    public function index(Request $request, Event $event): JsonResponse
    {
        $this->authorize('viewAny', EventFeedback::class);

        $feedbacks = EventFeedback::where('event_id', $event->id)->get();
        $totalResponses = $feedbacks->count();

        $confirmedRegsCount = Registration::where('event_id', $event->id)
            ->where('status', 'confirmed')
            ->sum('quantity') ?: 1;

        $responseRate = round(($totalResponses / $confirmedRegsCount) * 100, 1);

        $avgOverall = round($feedbacks->avg('overall_rating') ?: 0.0, 2);
        $avgOrganization = round($feedbacks->avg('organization_rating') ?: 0.0, 2);
        $avgProgram = round($feedbacks->avg('program_rating') ?: 0.0, 2);
        $avgVenue = round($feedbacks->avg('venue_rating') ?: 0.0, 2);

        // List text comments, redacting name if anonymous
        $comments = $feedbacks->map(function ($f) {
            return [
                'uuid' => $f->uuid,
                'is_anonymous' => $f->is_anonymous,
                'author_name' => $f->is_anonymous ? 'Anonymous' : ($f->user ? $f->user->name : ($f->registration ? $f->registration->attendee_name : 'Guest')),
                'overall_rating' => $f->overall_rating,
                'text_feedback' => $f->text_feedback,
                'created_at' => $f->created_at->toIso8601String(),
            ];
        })->filter(fn($c) => !empty($c['text_feedback']))->values();

        return ApiResponse::success([
            'average_overall_rating' => $avgOverall,
            'average_organization_rating' => $avgOrganization,
            'average_program_rating' => $avgProgram,
            'average_venue_rating' => $avgVenue,
            'total_responses' => $totalResponses,
            'response_rate' => $responseRate,
            'comments' => $comments,
        ], 'Feedback analytics retrieved.');
    }
}
