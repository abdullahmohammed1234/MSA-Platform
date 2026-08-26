<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academy\UploadAcademyAssetRequest;
use App\Services\Academy\AcademyAssetService;
use Illuminate\Http\JsonResponse;

/**
 * DAMS/Academy course asset uploads.
 *
 * Intentionally separate from CMS MediaController::uploadAsset so course
 * thumbnails never require CMS permissions or enter the CMS media library.
 */
class AcademyAssetController extends Controller
{
    public function __construct(
        private readonly AcademyAssetService $assets,
    ) {
    }

    public function upload(UploadAcademyAssetRequest $request): JsonResponse
    {
        $url = $this->assets->storeImage($request->file('file'));

        return response()->json([
            'success' => true,
            'message' => 'Academy image uploaded successfully.',
            'url' => $url,
            'owner' => 'academy',
        ], 201);
    }
}
