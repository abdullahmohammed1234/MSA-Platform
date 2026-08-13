<?php

namespace App\Http\Controllers\Admin\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\CMS\StoreMediaCategoryRequest;
use App\Models\CMS\MediaCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class MediaCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = MediaCategory::query()
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'slug', 'created_at']);

        return response()->json([
            'categories' => $categories,
        ]);
    }

    public function store(StoreMediaCategoryRequest $request): JsonResponse
    {
        $name = trim($request->validated('name'));

        $category = MediaCategory::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => MediaCategory::uniqueSlugFromName($name),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Media category created successfully.',
            'category' => $category,
        ], 201);
    }
}
