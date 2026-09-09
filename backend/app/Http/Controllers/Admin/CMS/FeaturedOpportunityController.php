<?php

namespace App\Http\Controllers\Admin\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\CMS\SaveFeaturedOpportunityRequest;
use App\Services\CMS\FeaturedOpportunityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class FeaturedOpportunityController extends Controller
{
    protected $service;

    public function __construct(FeaturedOpportunityService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status']);
        $opportunities = $this->service->listAdmin($filters, (int) $request->input('per_page', 15));

        return response()->json($opportunities);
    }

    public function store(SaveFeaturedOpportunityRequest $request): JsonResponse
    {
        $opportunity = $this->service->create($request->validated(), Auth::id());
        Cache::forget('website_featured_opportunities');

        return response()->json([
            'success' => true,
            'message' => 'Featured opportunity created successfully.',
            'opportunity' => $opportunity,
        ], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $opportunity = $this->service->findByUuid($uuid);
        if (!$opportunity) {
            return response()->json(['message' => 'Featured opportunity not found.'], 404);
        }

        return response()->json($opportunity);
    }

    public function update(SaveFeaturedOpportunityRequest $request, string $uuid): JsonResponse
    {
        $opportunity = $this->service->findByUuid($uuid);
        if (!$opportunity) {
            return response()->json(['message' => 'Featured opportunity not found.'], 404);
        }

        $opportunity = $this->service->update($opportunity, $request->validated(), Auth::id());
        Cache::forget('website_featured_opportunities');

        return response()->json([
            'success' => true,
            'message' => 'Featured opportunity updated successfully.',
            'opportunity' => $opportunity,
        ]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $opportunity = $this->service->findByUuid($uuid);
        if (!$opportunity) {
            return response()->json(['message' => 'Featured opportunity not found.'], 404);
        }

        $this->service->delete($opportunity, Auth::id());
        Cache::forget('website_featured_opportunities');

        return response()->json([
            'success' => true,
            'message' => 'Featured opportunity deleted successfully.',
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate(['uuids' => 'required|array']);

        $this->service->reorder($request->input('uuids'), Auth::id());
        Cache::forget('website_featured_opportunities');

        return response()->json([
            'success' => true,
            'message' => 'Featured opportunities reordered successfully.',
        ]);
    }

    public function revisions(string $uuid): JsonResponse
    {
        $opportunity = $this->service->findByUuid($uuid);
        if (!$opportunity) {
            return response()->json(['message' => 'Featured opportunity not found.'], 404);
        }

        return response()->json([
            'revisions' => $this->service->getRevisions($opportunity),
        ]);
    }

    public function rollback(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['version' => 'required|integer']);

        $opportunity = $this->service->findByUuid($uuid);
        if (!$opportunity) {
            return response()->json(['message' => 'Featured opportunity not found.'], 404);
        }

        $rolledBack = $this->service->rollback($opportunity, $request->input('version'), Auth::id());
        if (!$rolledBack) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to rollback. Version not found.',
            ], 400);
        }

        Cache::forget('website_featured_opportunities');

        return response()->json([
            'success' => true,
            'message' => 'Featured opportunity rolled back successfully.',
            'opportunity' => $opportunity->fresh(),
        ]);
    }
}
