<?php

namespace App\Http\Controllers\Admin\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\CMS\SaveAnnouncementRequest;
use App\Models\CMS\Announcement;
use App\Services\CMS\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AnnouncementController extends Controller
{
    protected $service;

    public function __construct(AnnouncementService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status', 'author_id']);
        $announcements = $this->service->list($filters, $request->input('per_page', 15));

        return response()->json($announcements);
    }

    public function store(SaveAnnouncementRequest $request): JsonResponse
    {
        $announcement = $this->service->create($request->validated(), Auth::id());
        Cache::forget('website_announcements');

        return response()->json([
            'success' => true,
            'message' => 'Announcement created successfully.',
            'announcement' => $announcement
        ], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $announcement = $this->service->findByUuid($uuid);
        if (!$announcement) {
            return response()->json(['message' => 'Announcement not found.'], 404);
        }

        return response()->json($announcement);
    }

    public function update(SaveAnnouncementRequest $request, string $uuid): JsonResponse
    {
        $announcement = $this->service->findByUuid($uuid);
        if (!$announcement) {
            return response()->json(['message' => 'Announcement not found.'], 404);
        }

        $this->service->update($announcement, $request->validated(), Auth::id());
        Cache::forget('website_announcements');

        return response()->json([
            'success' => true,
            'message' => 'Announcement updated successfully.',
            'announcement' => $announcement
        ]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $announcement = $this->service->findByUuid($uuid);
        if (!$announcement) {
            return response()->json(['message' => 'Announcement not found.'], 404);
        }

        $this->service->delete($announcement, Auth::id());
        Cache::forget('website_announcements');

        return response()->json([
            'success' => true,
            'message' => 'Announcement deleted successfully.'
        ]);
    }

    public function revisions(string $uuid): JsonResponse
    {
        $announcement = $this->service->findByUuid($uuid);
        if (!$announcement) {
            return response()->json(['message' => 'Announcement not found.'], 404);
        }

        return response()->json([
            'revisions' => $this->service->getRevisions($announcement)
        ]);
    }

    public function rollback(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['version' => 'required|integer']);

        $announcement = $this->service->findByUuid($uuid);
        if (!$announcement) {
            return response()->json(['message' => 'Announcement not found.'], 404);
        }

        $rolledBack = $this->service->rollback($announcement, $request->input('version'), Auth::id());

        if (!$rolledBack) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to rollback. Version not found.'
            ], 400);
        }

        Cache::forget('website_announcements');

        return response()->json([
            'success' => true,
            'message' => 'Announcement rolled back successfully.',
            'announcement' => $announcement->fresh()
        ]);
    }
}
