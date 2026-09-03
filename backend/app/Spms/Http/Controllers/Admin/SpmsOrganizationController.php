<?php

namespace App\Spms\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Spms\Models\Organization;
use App\Spms\Policies\SponsorshipPolicy;
use App\Spms\Services\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SpmsOrganizationController extends Controller
{
    public function __construct(private readonly OrganizationService $organizationService) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Organization::class);

        $query = Organization::with(['contacts', 'accountOwner']);

        if ($request->filled('search')) {
            $term = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($request->input('search'))) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('legal_name', 'like', $term)
                  ->orWhere('display_name', 'like', $term)
                  ->orWhere('email', 'like', $term)
                  ->orWhere('industry', 'like', $term);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('relationship_type')) {
            $query->where('relationship_type', $request->input('relationship_type'));
        }

        $organizations = $query->orderBy('display_name')->paginate($request->input('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $organizations->items(),
            'meta' => [
                'current_page' => $organizations->currentPage(),
                'last_page' => $organizations->lastPage(),
                'per_page' => $organizations->perPage(),
                'total' => $organizations->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Organization::class);

        $validated = $request->validate([
            'legal_name' => 'required|string|max:255',
            'display_name' => 'required|string|max:255',
            'relationship_type' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
            'industry' => 'nullable|string|max:100',
            'website_url' => 'nullable|url|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address_line1' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:50',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:50',
            'account_owner_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
            'logo_url' => 'nullable|string|max:500',
            'is_publicly_featured' => 'nullable|boolean',
        ]);

        $organization = $this->organizationService->createOrganization($validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Organization created successfully.',
            'data' => $organization->load(['contacts', 'accountOwner']),
        ], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        Gate::authorize('viewAny', Organization::class);

        $organization = Organization::where('uuid', $uuid)
            ->with(['contacts', 'accountOwner', 'sponsorships.opportunity', 'sponsorships.commitments', 'communications', 'followUps'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $organization,
        ]);
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        Gate::authorize('update', Organization::class);

        $organization = Organization::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'legal_name' => 'sometimes|required|string|max:255',
            'display_name' => 'sometimes|required|string|max:255',
            'relationship_type' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
            'industry' => 'nullable|string|max:100',
            'website_url' => 'nullable|url|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address_line1' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:50',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:50',
            'account_owner_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
            'logo_url' => 'nullable|string|max:500',
            'is_publicly_featured' => 'nullable|boolean',
        ]);

        $updated = $this->organizationService->updateOrganization($organization, $validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Organization updated successfully.',
            'data' => $updated->load(['contacts', 'accountOwner']),
        ]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        Gate::authorize('delete', Organization::class);

        $organization = Organization::where('uuid', $uuid)->firstOrFail();
        $organization->delete();

        return response()->json([
            'success' => true,
            'message' => 'Organization archived successfully.',
        ]);
    }

    public function checkDuplicates(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Organization::class);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        $duplicates = $this->organizationService->findDuplicates(
            $request->input('name'),
            $request->input('email')
        );

        return response()->json([
            'success' => true,
            'count' => $duplicates->count(),
            'data' => $duplicates,
        ]);
    }
}
