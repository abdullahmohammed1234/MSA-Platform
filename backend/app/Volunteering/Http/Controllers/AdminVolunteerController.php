<?php

namespace App\Volunteering\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Volunteering\Models\Opportunity;
use App\Volunteering\Models\Signup;
use App\Volunteering\Services\VolunteerOpportunityService;
use App\Volunteering\Services\VolunteerSignupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminVolunteerController extends Controller
{
    public function __construct(
        protected VolunteerOpportunityService $opportunityService,
        protected VolunteerSignupService $signupService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $opportunities = $this->opportunityService->listAdminOpportunities([
            'status' => $request->query('status'),
            'search' => $request->query('search'),
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:volunteering_opportunities,slug',
            'description' => 'nullable|string',
            'event_id' => 'nullable|exists:ems_events,id',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'location' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:1',
            'status' => 'nullable|string|in:draft,open,closed,archived',
            'teams' => 'nullable|array',
            'teams.*.name' => 'required|string|max:255',
            'teams.*.description' => 'nullable|string',
            'teams.*.capacity' => 'nullable|integer|min:1',
            'teams.*.status' => 'nullable|string|in:open,closed',
            'teams.*.shifts' => 'nullable|array',
            'teams.*.shifts.*.name' => 'nullable|string|max:255',
            'teams.*.shifts.*.start_at' => 'required|date',
            'teams.*.shifts.*.end_at' => 'required|date|after_or_equal:teams.*.shifts.*.start_at',
            'teams.*.shifts.*.capacity' => 'nullable|integer|min:1',
            'teams.*.shifts.*.status' => 'nullable|string|in:open,closed',
            'shifts' => 'nullable|array',
            'shifts.*.name' => 'nullable|string|max:255',
            'shifts.*.start_at' => 'required|date',
            'shifts.*.end_at' => 'required|date|after_or_equal:shifts.*.start_at',
            'shifts.*.capacity' => 'nullable|integer|min:1',
            'shifts.*.status' => 'nullable|string|in:open,closed',
        ]);

        $opportunity = $this->opportunityService->createOpportunity($validated, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Volunteer opportunity created successfully.',
            'data' => $opportunity,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $opportunity = Opportunity::with(['event', 'teams.shifts', 'shifts', 'creator:id,name', 'updater:id,name'])
            ->withCount(['signups' => function ($q) {
                $q->whereIn('status', ['signed_up', 'confirmed', 'completed']);
            }])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $opportunity,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $opportunity = Opportunity::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:volunteering_opportunities,slug,' . $id,
            'description' => 'nullable|string',
            'event_id' => 'nullable|exists:ems_events,id',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date',
            'location' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:1',
            'status' => 'nullable|string|in:draft,open,closed,archived',
        ]);

        $updated = $this->opportunityService->updateOpportunity($opportunity, $validated, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Volunteer opportunity updated successfully.',
            'data' => $updated,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $opportunity = Opportunity::findOrFail($id);
        $opportunity->delete();

        return response()->json([
            'success' => true,
            'message' => 'Volunteer opportunity archived successfully.',
        ]);
    }

    public function listSignups(Request $request, int $id): JsonResponse
    {
        $opportunity = Opportunity::findOrFail($id);

        $query = Signup::with(['team:id,name', 'shift:id,name,start_at,end_at', 'user:id,name,email'])
            ->where('opportunity_id', $opportunity->id);

        if ($request->query('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->query('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('email', 'like', $search);
            });
        }

        $signups = $query->orderBy('created_at', 'desc')->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $signups->items(),
            'meta' => [
                'current_page' => $signups->currentPage(),
                'last_page' => $signups->lastPage(),
                'per_page' => $signups->perPage(),
                'total' => $signups->total(),
            ],
        ]);
    }

    public function updateSignupStatus(Request $request, int $signupId): JsonResponse
    {
        $signup = Signup::findOrFail($signupId);

        $validated = $request->validate([
            'status' => 'required|string|in:signed_up,confirmed,cancelled,completed,no_show',
            'admin_notes' => 'nullable|string',
        ]);

        $updatedSignup = $this->signupService->updateStatus(
            $signup,
            $validated['status'],
            $validated['admin_notes'] ?? null,
            $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Volunteer signup status updated.',
            'data' => $updatedSignup,
        ]);
    }

    public function analytics(): JsonResponse
    {
        $analytics = $this->opportunityService->getAnalytics();

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }
}
