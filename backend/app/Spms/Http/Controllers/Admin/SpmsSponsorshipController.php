<?php

namespace App\Spms\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Spms\Models\Sponsorship;
use App\Spms\Policies\SponsorshipPolicy;
use App\Spms\Services\SponsorshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SpmsSponsorshipController extends Controller
{
    public function __construct(private readonly SponsorshipService $sponsorshipService) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Sponsorship::class);

        $query = Sponsorship::with(['organization', 'contact', 'opportunity', 'package', 'relationshipManager']);

        if ($request->filled('search')) {
            $term = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($request->input('search'))) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('sponsorship_number', 'like', $term)
                  ->orWhere('title', 'like', $term)
                  ->orWhereHas('organization', fn ($oq) => $oq->where('display_name', 'like', $term));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('financial_status')) {
            $query->where('financial_status', $request->input('financial_status'));
        }

        if ($request->filled('fulfillment_status')) {
            $query->where('fulfillment_status', $request->input('fulfillment_status'));
        }

        $sponsorships = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $sponsorships->items(),
            'meta' => [
                'current_page' => $sponsorships->currentPage(),
                'last_page' => $sponsorships->lastPage(),
                'per_page' => $sponsorships->perPage(),
                'total' => $sponsorships->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Sponsorship::class);

        $validated = $request->validate([
            'organization_id' => 'required|exists:spms_organizations,id',
            'contact_id' => 'nullable|exists:spms_contacts,id',
            'opportunity_id' => 'nullable|exists:spms_opportunities,id',
            'package_id' => 'nullable|exists:spms_packages,id',
            'title' => 'required|string|max:255',
            'sponsorship_type' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
            'total_committed_cents' => 'nullable|integer|min:0',
            'in_kind_estimated_cents' => 'nullable|integer|min:0',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
            'relationship_manager_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        try {
            $sponsorship = $this->sponsorshipService->createSponsorship($validated, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Sponsorship deal created successfully.',
                'data' => $sponsorship->load(['organization', 'opportunity', 'package', 'commitments', 'deliverables']),
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(string $uuid): JsonResponse
    {
        Gate::authorize('viewAny', Sponsorship::class);

        $sponsorship = Sponsorship::where('uuid', $uuid)
            ->with([
                'organization',
                'contact',
                'opportunity.event',
                'package',
                'relationshipManager',
                'agreement',
                'commitments.payments',
                'payments',
                'inKindContributions',
                'deliverables.fulfillments',
                'communications',
                'followUps',
                'renewals',
            ])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $sponsorship,
        ]);
    }

    public function updateStatus(Request $request, string $uuid): JsonResponse
    {
        Gate::authorize('update', Sponsorship::class);

        $sponsorship = Sponsorship::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'status' => 'required|string|max:50',
        ]);

        $updated = $this->sponsorshipService->updateStatus($sponsorship, $validated['status'], $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Sponsorship status updated.',
            'data' => $updated,
        ]);
    }

    public function executeAgreement(Request $request, string $uuid): JsonResponse
    {
        Gate::authorize('manageAgreements', Sponsorship::class);

        $sponsorship = Sponsorship::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'terms_and_conditions' => 'nullable|string',
            'document_url' => 'nullable|string|max:500',
            'executed_by_name' => 'nullable|string|max:255',
            'executed_by_title' => 'nullable|string|max:255',
        ]);

        $agreement = $this->sponsorshipService->executeAgreement($sponsorship, $validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Sponsorship agreement executed successfully.',
            'data' => $agreement,
        ]);
    }
}
