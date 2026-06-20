<?php

namespace App\Http\Controllers\Admin\CMS;

use App\Http\Controllers\Controller;
use App\Models\CMS\Media;
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
        $filters = $request->only(['search']);
        $media = $this->service->list($filters, $request->input('per_page', 18));

        return response()->json($media);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:jpeg,png,jpg,gif,svg,webp,pdf,doc,docx,zip|max:10240', // max 10MB
        ]);

        $file = $request->file('file');
        $media = $this->service->upload($file, Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'File uploaded successfully.',
            'media' => $media
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
            'message' => 'Media file deleted successfully.'
        ]);
    }
}
