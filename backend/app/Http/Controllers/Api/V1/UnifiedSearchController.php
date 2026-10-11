<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Search\UnifiedSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnifiedSearchController extends Controller
{
    protected UnifiedSearchService $searchService;

    public function __construct(UnifiedSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * Unified platform discovery search endpoint.
     * GET /api/v1/search?q={query}&type={type}&sort_by={sort_by}&page={page}&per_page={per_page}
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $type = $request->query('type') ?? $request->query('content_type');
        $sortBy = (string) $request->query('sort_by', 'relevance');
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 10);

        $results = $this->searchService->search(
            query: $query,
            typeFilter: $type,
            sortBy: $sortBy,
            page: $page,
            perPage: $perPage
        );

        return response()->json($results);
    }
}
