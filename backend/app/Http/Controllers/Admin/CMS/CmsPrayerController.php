<?php

namespace App\Http\Controllers\Admin\CMS;

use App\Http\Controllers\Controller;
use App\Models\CMS\CmsPrayer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CmsPrayerController extends Controller
{
    public function index(): JsonResponse
    {
        $prayers = CmsPrayer::orderBy('display_order', 'asc')->orderBy('created_at', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $prayers,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:daily,jumuah',
            'title' => 'required|string|max:255',
            'campus' => 'nullable|string|max:64',
            'day' => 'nullable|string|max:32',
            'is_enabled' => 'boolean',
            'fajr_time' => 'nullable|string|max:32',
            'dhuhr_time' => 'nullable|string|max:32',
            'asr_time' => 'nullable|string|max:32',
            'maghrib_time' => 'nullable|string|max:32',
            'isha_time' => 'nullable|string|max:32',
            'khutbah_time' => 'nullable|string|max:32',
            'prayer_time' => 'nullable|string|max:32',
            'location' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'timings_json' => 'nullable|array',
            'display_order' => 'nullable|integer',
        ]);

        $user = $request->user();
        $validated['created_by'] = $user?->id;
        $validated['updated_by'] = $user?->id;

        $prayer = CmsPrayer::create($validated);

        Cache::forget('cms_prayers');

        return response()->json([
            'success' => true,
            'message' => 'Prayer entry created successfully.',
            'data' => $prayer,
        ], 201);
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        $prayer = CmsPrayer::where('id', $id)->orWhere('uuid', $id)->firstOrFail();

        $validated = $request->validate([
            'type' => 'sometimes|string|in:daily,jumuah',
            'title' => 'sometimes|string|max:255',
            'campus' => 'nullable|string|max:64',
            'day' => 'nullable|string|max:32',
            'is_enabled' => 'boolean',
            'fajr_time' => 'nullable|string|max:32',
            'dhuhr_time' => 'nullable|string|max:32',
            'asr_time' => 'nullable|string|max:32',
            'maghrib_time' => 'nullable|string|max:32',
            'isha_time' => 'nullable|string|max:32',
            'khutbah_time' => 'nullable|string|max:32',
            'prayer_time' => 'nullable|string|max:32',
            'location' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'timings_json' => 'nullable|array',
            'display_order' => 'nullable|integer',
        ]);

        $user = $request->user();
        $validated['updated_by'] = $user?->id;

        $prayer->update($validated);

        Cache::forget('cms_prayers');

        return response()->json([
            'success' => true,
            'message' => 'Prayer entry updated successfully.',
            'data' => $prayer->fresh(),
        ]);
    }

    public function destroy(int|string $id): JsonResponse
    {
        $prayer = CmsPrayer::where('id', $id)->orWhere('uuid', $id)->firstOrFail();
        $prayer->delete();

        Cache::forget('cms_prayers');

        return response()->json([
            'success' => true,
            'message' => 'Prayer entry removed successfully.',
        ]);
    }
}
