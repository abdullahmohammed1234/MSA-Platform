<?php

namespace App\Mlibms\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Mlibms\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = Setting::all()->pluck('value', 'key');

        return response()->json([
            'data' => [
                'default_loan_days' => (int) ($settings['default_loan_days'] ?? 14),
                'max_active_loans' => (int) ($settings['max_active_loans'] ?? 3),
                'max_renewals' => (int) ($settings['max_renewals'] ?? 2),
                'hold_pickup_days' => (int) ($settings['hold_pickup_days'] ?? 3),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'default_loan_days' => 'nullable|integer|min:1|max:365',
            'max_active_loans' => 'nullable|integer|min:1|max:20',
            'max_renewals' => 'nullable|integer|min:0|max:10',
            'hold_pickup_days' => 'nullable|integer|min:1|max:30',
        ]);

        foreach ($data as $key => $val) {
            if (!is_null($val)) {
                Setting::set($key, $val);
            }
        }

        return response()->json([
            'message' => 'Library settings updated successfully.',
        ]);
    }
}
