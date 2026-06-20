<?php

namespace App\Http\Controllers\Admin\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\CMS\SaveHomepageRequest;
use App\Services\CMS\HomepageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class HomepageController extends Controller
{
    protected $service;

    public function __construct(HomepageService $service)
    {
        $this->service = $service;
    }

    public function index(): JsonResponse
    {
        // Returns the list of sections with all blocks
        return response()->json([
            'sections' => $this->service->getSections()
        ]);
    }

    public function update(string $key, SaveHomepageRequest $request): JsonResponse
    {
        $updated = $this->service->updateSection($key, $request->input('blocks'), Auth::id());

        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'Homepage section not found.'
            ], 404);
        }

        Cache::forget('website_homepage');

        return response()->json([
            'success' => true,
            'message' => 'Homepage section updated successfully.'
        ]);
    }
}
