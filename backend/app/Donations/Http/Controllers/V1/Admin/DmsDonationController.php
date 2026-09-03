<?php

namespace App\Donations\Http\Controllers\V1\Admin;

use App\Donations\Models\Donation;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DmsDonationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasRole('super-admin') && ! $user->hasRole('admin') && ! $user->hasRole('dms-administrator') && ! $user->hasRole('dms-staff') && ! $user->hasPermissionTo('donations.view'))) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $query = Donation::query()->with('refunds');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('donation_number', 'like', "%{$search}%")
                    ->orWhere('donor_name', 'like', "%{$search}%")
                    ->orWhere('donor_email', 'like', "%{$search}%")
                    ->orWhere('square_payment_id', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        $perPage = min(max((int) $request->input('per_page', 15), 5), 100);
        $donations = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'donations' => $donations->items(),
            'meta' => [
                'current_page' => $donations->currentPage(),
                'last_page' => $donations->lastPage(),
                'per_page' => $donations->perPage(),
                'total' => $donations->total(),
            ],
        ]);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasRole('super-admin') && ! $user->hasRole('admin') && ! $user->hasRole('dms-administrator') && ! $user->hasRole('dms-staff') && ! $user->hasPermissionTo('donations.view'))) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $donation = Donation::with('refunds.processor')->where('uuid', $uuid)->first();

        if (! $donation) {
            return response()->json(['message' => 'Donation not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'donation' => $donation,
        ]);
    }
}
