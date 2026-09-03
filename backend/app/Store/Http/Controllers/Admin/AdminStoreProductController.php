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
}
