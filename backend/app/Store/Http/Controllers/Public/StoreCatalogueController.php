<?php

namespace App\Store\Http\Controllers\Public;

use App\Ems\Support\ApiResponse;
use App\Http\Controllers\Controller;
use App\Store\Http\Resources\StoreProductResource;
use App\Store\Models\StoreProduct;
use App\Store\Services\StoreProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;

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
        )->header('Cache-Control', 'private, no-cache');
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
        )->header('Cache-Control', 'private, no-cache');
    }

    /**
     * Authenticated/Audience-checked image streaming endpoint.
     */
    public function streamImage(Request $request, string $productUuid, string $imageUuid)
    {
        $product = StoreProduct::where('uuid', $productUuid)->firstOrFail();
        $image = $product->images()->where('uuid', $imageUuid)->firstOrFail();

        if ($image->is_private) {
            $user = $request->user('sanctum');
            if (!$user && ($token = $request->bearerToken())) {
                $accessToken = PersonalAccessToken::findToken($token);
                if ($accessToken && (!$accessToken->expires_at || $accessToken->expires_at->isFuture())) {
                    $user = $accessToken->tokenable;
                }
            }

            if (!$user) {
                abort(403, 'Unauthorized audience.');
            }

            $isAdmin = $user->hasRole('admin')
                || $user->hasRole('super-admin')
                || $user->can('manage-store')
                || $user->can('viewAny', StoreProduct::class);

            if (!$isAdmin) {
                if (empty($user->gender)) {
                    abort(403, 'Unauthorized audience: gender profile required.');
                }
                if ($image->image_type === 'brother' && $user->gender !== 'male') {
                    abort(403, 'Unauthorized audience.');
                }
                if ($image->image_type === 'sister' && $user->gender !== 'female') {
                    abort(403, 'Unauthorized audience.');
                }
            }
        }

        $path = $image->image_path;
        $mimeType = 'image/jpeg';
        $filename = basename($path ?: 'product-image.jpg');
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
        ];

        if ($path && Storage::disk('local')->exists($path)) {
            $fullPath = Storage::disk('local')->path($path);
            $mimeType = Storage::disk('local')->mimeType($path) ?: 'image/jpeg';
            $headers['Content-Type'] = $mimeType;
            $fileResponse = response()->file($fullPath, $headers);
            $fileResponse->headers->set('Cache-Control', 'private, no-store, max-age=0, must-revalidate');
            $fileResponse->headers->set('Pragma', 'no-cache');
            return $fileResponse;
        } elseif ($path && Storage::disk('public')->exists($path)) {
            $fullPath = Storage::disk('public')->path($path);
            $mimeType = Storage::disk('public')->mimeType($path) ?: 'image/jpeg';
            $headers['Content-Type'] = $mimeType;
            $fileResponse = response()->file($fullPath, $headers);
            $fileResponse->headers->set('Cache-Control', 'private, no-store, max-age=0, must-revalidate');
            $fileResponse->headers->set('Pragma', 'no-cache');
            return $fileResponse;
        } elseif (filter_var($image->image_url, FILTER_VALIDATE_URL)) {
            return redirect()->away($image->image_url);
        } else {
            abort(404, 'Image asset not found.');
        }
    }
}
