<?php

namespace App\Http\Controllers\Admin\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\CMS\SaveResourceRequest;
use App\Models\CMS\Resource;
use App\Services\CMS\ResourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ResourceController extends Controller
{
    protected $service;

    public function __construct(ResourceService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'category', 'status']);
        $resources = $this->service->list($filters, $request->input('per_page', 15));

        return response()->json($resources);
    }

    public function store(SaveResourceRequest $request): JsonResponse
    {
        $resource = $this->service->create($request->validated(), Auth::id());
        Cache::forget('website_resources');

        return response()->json([
            'success' => true,
            'message' => 'Resource created successfully.',
            'resource' => $resource
        ], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $resource = $this->service->findByUuid($uuid);
        if (!$resource) {
            return response()->json(['message' => 'Resource not found.'], 404);
        }

        return response()->json($resource);
    }

    public function update(SaveResourceRequest $request, string $uuid): JsonResponse
    {
        $resource = $this->service->findByUuid($uuid);
        if (!$resource) {
            return response()->json(['message' => 'Resource not found.'], 404);
        }

        $this->service->update($resource, $request->validated(), Auth::id());
        Cache::forget('website_resources');

        return response()->json([
            'success' => true,
            'message' => 'Resource updated successfully.',
            'resource' => $resource
        ]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $resource = $this->service->findByUuid($uuid);
        if (!$resource) {
            return response()->json(['message' => 'Resource not found.'], 404);
        }

        $this->service->delete($resource, Auth::id());
        Cache::forget('website_resources');

        return response()->json([
            'success' => true,
            'message' => 'Resource deleted successfully.'
        ]);
    }

    public function revisions(string $uuid): JsonResponse
    {
        $resource = $this->service->findByUuid($uuid);
        if (!$resource) {
            return response()->json(['message' => 'Resource not found.'], 404);
        }

        return response()->json([
            'revisions' => $this->service->getRevisions($resource)
        ]);
    }

    public function rollback(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['version' => 'required|integer']);

        $resource = $this->service->findByUuid($uuid);
        if (!$resource) {
            return response()->json(['message' => 'Resource not found.'], 404);
        }

        $rolledBack = $this->service->rollback($resource, $request->input('version'), Auth::id());

        if (!$rolledBack) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to rollback. Version not found.'
            ], 400);
        }

        Cache::forget('website_resources');

        return response()->json([
            'success' => true,
            'message' => 'Resource rolled back successfully.',
            'resource' => $resource->fresh()
        ]);
    }
}
