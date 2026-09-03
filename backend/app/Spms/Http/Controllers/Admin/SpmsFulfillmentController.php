<?php

namespace App\Spms\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Spms\Models\Deliverable;
use App\Spms\Models\Sponsorship;
use App\Spms\Policies\SponsorshipPolicy;
use App\Spms\Services\SponsorshipFulfillmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SpmsFulfillmentController extends Controller
{
    public function __construct(private readonly SponsorshipFulfillmentService $fulfillmentService) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Sponsorship::class);

        $query = Deliverable::with(['sponsorship.organization', 'assignee', 'fulfillments.completer']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('deliverable_type', $request->input('type'));
        }

        $deliverables = $query->orderBy('due_date', 'asc')->paginate($request->input('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $deliverables->items(),
            'meta' => [
                'current_page' => $deliverables->currentPage(),
                'last_page' => $deliverables->lastPage(),
                'per_page' => $deliverables->perPage(),
                'total' => $deliverables->total(),
            ],
        ]);
    }

    public function storeDeliverable(Request $request, string $sponsorshipUuid): JsonResponse
    {
        Gate::authorize('manageFulfillment', Sponsorship::class);

        $sponsorship = Sponsorship::where('uuid', $sponsorshipUuid)->firstOrFail();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'deliverable_type' => 'nullable|string|max:50',
            'due_date' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $deliverable = $this->fulfillmentService->addDeliverable($sponsorship, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Deliverable added successfully.',
            'data' => $deliverable->load('assignee'),
        ], 201);
    }

    public function completeFulfillment(Request $request, string $deliverableUuid): JsonResponse
    {
        Gate::authorize('manageFulfillment', Sponsorship::class);

        $deliverable = Deliverable::where('uuid', $deliverableUuid)->firstOrFail();

        $validated = $request->validate([
            'proof_url' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
            'completed_at' => 'nullable|date',
        ]);

        $fulfillment = $this->fulfillmentService->completeFulfillment($deliverable, $validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Deliverable marked as fulfilled.',
            'data' => $fulfillment->load('completer'),
        ]);
    }
}
