<?php

namespace App\Store\Http\Controllers\Admin;

use App\Ems\Support\ApiResponse;
use App\Http\Controllers\Controller;
use App\Store\Http\Requests\StoreProductRequest;
use App\Store\Http\Resources\StoreProductResource;
use App\Store\Models\StoreProduct;
use App\Store\Services\StoreProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStoreProductController extends Controller
{
    protected StoreProductService $productService;

    public function __construct(StoreProductService $productService)
    {
        $this->productService = $productService;
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StoreProduct::class);

        $paginator = $this->productService->listAdminProducts(
            $request->only(['search', 'status']),
            (int) $request->query('per_page', 15)
        );

        return ApiResponse::paginated(
            $paginator,
            'Store products retrieved successfully.',
            StoreProductResource::class
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $this->authorize('create', StoreProduct::class);

        $product = $this->productService->createProduct(
            $request->validated(),
            $request->user()?->id
        );

        return ApiResponse::created(
            new StoreProductResource($product),
            'Merchandise product created successfully.'
        );
    }

    public function show(StoreProduct $product): JsonResponse
    {
        $this->authorize('view', $product);

        $product->load(['variants', 'images']);

        return ApiResponse::success(
            new StoreProductResource($product),
            'Product details retrieved successfully.'
        );
    }

    public function update(StoreProductRequest $request, StoreProduct $product): JsonResponse
    {
        $this->authorize('update', $product);

        $updated = $this->productService->updateProduct(
            $product,
            $request->validated(),
            $request->user()?->id
        );

        return ApiResponse::success(
            new StoreProductResource($updated),
            'Merchandise product updated successfully.'
        );
    }

    public function destroy(Request $request, StoreProduct $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $this->productService->deleteProduct($product, $request->user()?->id);

        return ApiResponse::deleted('Merchandise product archived successfully.');
    }

    public function uploadImage(Request $request, StoreProduct $product): JsonResponse
    {
        $this->authorize('update', $product);

        $request->validate([
            'image_type' => 'required|string|in:sister,brother,neutral',
            'view_type' => 'required|string|in:front,back',
            'file' => 'nullable|file|image|mimes:jpeg,png,webp,jpg|max:10240',
            'image_url' => 'nullable|string|max:2048',
        ]);

        if (!$request->hasFile('file') && empty($request->input('image_url'))) {
            return ApiResponse::error('Either a file or an image_url must be provided.', 422);
        }

        $fileOrUrl = $request->file('file') ?? $request->input('image_url');

        $image = $this->productService->saveMerchandiseImage(
            $product,
            $request->input('image_type'),
            $request->input('view_type'),
            $fileOrUrl
        );

        return ApiResponse::success(
            [
                'uuid' => $image->uuid,
                'image_type' => $image->image_type,
                'view_type' => $image->view_type,
                'is_private' => $image->is_private,
                'image_url' => $image->image_url,
            ],
            'Image uploaded and associated successfully.'
        );
    }
}
