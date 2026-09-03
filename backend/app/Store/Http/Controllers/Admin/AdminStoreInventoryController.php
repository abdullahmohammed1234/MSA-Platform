<?php

namespace App\Store\Http\Controllers\Admin;

use App\Ems\Support\ApiResponse;
use App\Http\Controllers\Controller;
use App\Store\Models\StoreInventoryAdjustment;
use App\Store\Models\StoreProduct;
use App\Store\Models\StoreProductVariant;
use App\Store\Services\StoreProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStoreInventoryController extends Controller
{
    protected StoreProductService $productService;

    public function __construct(StoreProductService $productService)
    {
        $this->productService = $productService;
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StoreInventoryAdjustment::class);

        $adjustments = StoreInventoryAdjustment::query()
            ->with(['product', 'variant', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate((int) $request->query('per_page', 20));

        return ApiResponse::paginated(
            $adjustments,
            'Inventory adjustments retrieved successfully.'
        );
    }

    public function adjust(Request $request): JsonResponse
    {
        $this->authorize('create', StoreInventoryAdjustment::class);

        $request->validate([
            'product_id' => 'required|integer|exists:store_products,id',
            'variant_id' => 'nullable|integer|exists:store_product_variants,id',
            'new_quantity' => 'required|integer|min:0',
            'reason' => 'nullable|string|max:500',
        ]);

        $product = StoreProduct::findOrFail((int) $request->input('product_id'));
        $variant = $request->input('variant_id')
            ? StoreProductVariant::where('id', $request->input('variant_id'))->where('product_id', $product->id)->firstOrFail()
            : null;

        $this->productService->adjustInventory(
            $product,
            $variant,
            (int) $request->input('new_quantity'),
            $request->input('reason'),
            $request->user()?->id
        );

        return ApiResponse::success(null, 'Inventory adjusted successfully.');
    }
}
