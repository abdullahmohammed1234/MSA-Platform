<?php

namespace App\Spms\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Spms\Models\Opportunity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SpmsOpportunityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Opportunity::class);

        $query = Opportunity::with(['event', 'packages.benefits', 'creator']);

        if ($request->filled('search')) {
            $term = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($request->input('search'))) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                  ->orWhere('description', 'like', $term);
            });
        }

        if ($request->filled('type')) {
            $query->where('opportunity_type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $opportunities = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 25));

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
        Gate::authorize('create', Opportunity::class);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'opportunity_type' => 'nullable|string|max:50',
            'event_id' => 'nullable|exists:ems_events,id',
            'target_amount_cents' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'is_public' => 'nullable|boolean',
            'status' => 'nullable|string|max:50',
        ]);

        $validated['created_by'] = $request->user()->id;

        $opportunity = Opportunity::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Opportunity created successfully.',
            'data' => $opportunity->load(['event', 'packages']),
        ], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        Gate::authorize('viewAny', Opportunity::class);

        $opportunity = Opportunity::where('uuid', $uuid)
            ->with(['event', 'packages.benefits', 'sponsorships.organization', 'creator'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $opportunity,
        ]);
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        Gate::authorize('update', Opportunity::class);

        $opportunity = Opportunity::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'opportunity_type' => 'nullable|string|max:50',
            'event_id' => 'nullable|exists:ems_events,id',
            'target_amount_cents' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'is_public' => 'nullable|boolean',
            'status' => 'nullable|string|max:50',
        ]);

        $opportunity->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Opportunity updated successfully.',
            'data' => $opportunity->load(['event', 'packages']),
        ]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        Gate::authorize('delete', Opportunity::class);

        $opportunity = Opportunity::where('uuid', $uuid)->firstOrFail();
        $opportunity->delete();

        return response()->json([
            'success' => true,
            'message' => 'Opportunity deleted successfully.',
        ]);
    }
}
