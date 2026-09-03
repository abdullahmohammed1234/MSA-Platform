<?php

namespace App\Store\Http\Controllers\Public;

use App\Ems\Support\ApiResponse;
use App\Http\Controllers\Controller;
use App\Store\Http\Resources\StoreProductResource;
use App\Store\Services\StoreProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreCatalogueController extends Controller
{
    protected StoreProductService $productService;

    public function __construct(StoreProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * List active store products for public catalogue.
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->productService->listPublicProducts(
            $request->only(['search', 'sort_by', 'sort_order']),
            (int) $request->query('per_page', 12)
        );

        return ApiResponse::paginated(
            $paginator,
            'Products retrieved successfully.',
            StoreProductResource::class
        );
    }

    /**
     * Show single product details for public page.
     */
    public function show(string $slug): JsonResponse
    {
        $product = $this->productService->getPublicProductBySlug($slug);

        return ApiResponse::success(
            new StoreProductResource($product),
            'Product details retrieved successfully.'
        );
    }
}
