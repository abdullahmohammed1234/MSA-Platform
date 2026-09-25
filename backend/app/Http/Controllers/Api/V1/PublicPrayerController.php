<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CMS\CmsPrayer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class PublicPrayerController extends Controller
{
    public function index(): JsonResponse
    {
        $prayers = Cache::remember('cms_prayers', 3600, function () {
            return CmsPrayer::where('is_enabled', true)
                ->orderBy('display_order', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();
        });

        $daily = $prayers->where('type', 'daily')->values();
        $jumuah = $prayers->where('type', 'jumuah')->values();

        return response()->json([
            'success' => true,
            'data' => [
                'daily' => $daily,
                'jumuah' => $jumuah,
            ],
        ]);
    }
}
