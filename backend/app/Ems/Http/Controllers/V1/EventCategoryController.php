<?php

namespace App\Ems\Http\Controllers\V1;

use App\Ems\Http\Controllers\EmsController;
use App\Ems\Http\Requests\StoreEventCategoryRequest;
use App\Ems\Http\Requests\UpdateEventCategoryRequest;
use App\Ems\Http\Resources\EventCategoryResource;
use App\Ems\Models\EventCategory;
use App\Ems\Services\EventCategoryService;
use App\Ems\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventCategoryController extends EmsController
{
    public function __construct(private readonly EventCategoryService $categories)
    {
    }

    /**
     * GET /api/v1/ems/event-categories
     *
     * Unpaginated: the taxonomy is small and the frontend needs the whole list
     * to populate category selects.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EventCategory::class);

        $validated = $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $categories = $this->categories->list($validated);

        return ApiResponse::success(
            EventCategoryResource::collection($categories),
            'Event categories retrieved successfully.',
            ['total' => $categories->count()]
        );
    }

    /**
     * POST /api/v1/ems/event-categories
     */
    public function store(StoreEventCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', EventCategory::class);

        $category = $this->categories->create($request->validated(), $request->user());

        return ApiResponse::created(
            new EventCategoryResource($category),
            'Event category created successfully.'
        );
    }

    /**
     * GET /api/v1/ems/event-categories/{category}
     */
    public function show(EventCategory $category): JsonResponse
    {
        $this->authorize('view', $category);

        return ApiResponse::success(
            new EventCategoryResource($category->loadCount('events')),
            'Event category retrieved successfully.'
        );
    }

    /**
     * PUT /api/v1/ems/event-categories/{category}
     */
    public function update(UpdateEventCategoryRequest $request, EventCategory $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category = $this->categories->update($category, $request->validated(), $request->user());

        return ApiResponse::success(
            new EventCategoryResource($category),
            'Event category updated successfully.'
        );
    }

    /**
     * DELETE /api/v1/ems/event-categories/{category}
     *
     * Answers 409 when events are still attached.
     */
    public function destroy(Request $request, EventCategory $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $this->categories->delete($category, $request->user());

        return ApiResponse::deleted('Event category deleted successfully.');
    }
}
