<?php

namespace App\Volunteering\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Signup;
use App\Volunteering\Services\VolunteerOpportunityService;
use App\Volunteering\Services\VolunteerSignupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicVolunteerController extends Controller
{
    public function __construct(
        protected VolunteerOpportunityService $opportunityService,
        protected VolunteerSignupService $signupService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $opportunities = $this->opportunityService->listPublicOpportunities([
            'search' => $request->query('search'),
            'event_id' => $request->query('event_id'),
            'per_page' => (int) $request->query('per_page', 15),
        ]);

        return response()->json([
            'success' => true,
            'data' => $opportunities->items(),
            'meta' => [
                'current_page' => $opportunities->currentPage(),
                'last_page' => $opportunities->lastPage(),
                'per_page' => $opportunities->perPage(),
                'total' => $opportunities->total(),
            ],
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $opportunity = $this->opportunityService->getOpportunityBySlug($slug);

        return response()->json([
            'success' => true,
            'data' => $opportunity,
        ]);
    }

    public function signup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'opportunity_id' => 'required|exists:volunteering_opportunities,id',
            'team_id' => 'nullable|exists:volunteering_teams,id',
            'shift_id' => 'nullable|exists:volunteering_shifts,id',
            'name' => 'required|string|max:180',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:32',
            'experience' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $userId = $request->user()?->id;
        $signup = $this->signupService->registerSignup($validated, $userId);

        return response()->json([
            'success' => true,
            'message' => 'Successfully signed up as a volunteer.',
            'data' => $signup,
        ], 201);
    }

    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $signup = Signup::where('uuid', $uuid)->firstOrFail();

        if ($request->user() && $signup->user_id && $signup->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.',
            ], 403);
        }

        $cancelledSignup = $this->signupService->cancelSignup($signup, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Volunteer registration cancelled successfully.',
            'data' => $cancelledSignup,
        ]);
    }

    public function myHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $history = $this->signupService->getUserHistory($user->id);

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }
}
