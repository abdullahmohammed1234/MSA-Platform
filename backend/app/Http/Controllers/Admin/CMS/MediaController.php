<?php

namespace App\Http\Controllers\Admin\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\CMS\UploadAssetRequest;
use App\Http\Requests\CMS\UploadMediaRequest;
use App\Services\CMS\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MediaController extends Controller
{
    protected $service;

    public function __construct(MediaService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'category_id', 'media_type']);
        $media = $this->service->list($filters, $request->input('per_page', 18));

        return response()->json($media);
    }

    public function store(UploadMediaRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $media = $this->service->upload($file, Auth::id(), [
            'display_name' => $request->validated('display_name'),
            'category_id' => $request->validated('category_id'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File uploaded successfully.',
            'media' => $media,
        ], 201);
    }

    /**
     * Upload a CMS-owned contextual image (homepage, announcements, team forms).
     * Returns a public URL only — does not create a media-library entry.
     *
     * Course/Academy assets must use POST /admin/academy/assets/upload.
     */
    public function uploadAsset(UploadAssetRequest $request): JsonResponse
    {
        $url = $this->service->storeAsset($request->file('file'));

        return response()->json([
            'success' => true,
            'message' => 'Image uploaded successfully.',
            'url' => $url,
            'owner' => 'cms',
        ], 201);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $media = $this->service->findByUuid($uuid);
        if (!$media) {
            return response()->json(['message' => 'Media file not found.'], 404);
        }

        $this->service->delete($media, Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Media file deleted successfully.',
        ]);
    }
}
