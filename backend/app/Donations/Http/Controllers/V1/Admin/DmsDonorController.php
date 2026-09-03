<?php

namespace App\Donations\Http\Controllers\V1\Admin;

use App\Donations\Enums\DonationStatus;
use App\Donations\Models\Donation;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DmsDonorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasRole('super-admin') && ! $user->hasRole('admin') && ! $user->hasRole('dms-administrator') && ! $user->hasRole('dms-staff') && ! $user->hasPermissionTo('donations.view'))) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $query = Donation::query()
            ->where('status', DonationStatus::Paid->value)
            ->select(
                'donor_email',
                DB::raw('MAX(donor_name) as donor_name'),
                DB::raw('COUNT(*) as total_donations'),
                DB::raw('SUM(amount_cents) as total_contributed_cents'),
                DB::raw('MAX(paid_at) as last_donated_at')
            )
            ->groupBy('donor_email');

        if ($search = $request->input('search')) {
            $query->havingRaw('donor_name LIKE ? OR donor_email LIKE ?', ["%{$search}%", "%{$search}%"]);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 5), 100);
        $donors = $query->orderByDesc('total_contributed_cents')->paginate($perPage);

        return response()->json([
            'success' => true,
            'donors' => array_map(function ($d) {
                return [
                    'donor_name' => $d->donor_name,
                    'donor_email' => $d->donor_email,
                    'total_donations' => (int) $d->total_donations,
                    'total_contributed_cents' => (int) $d->total_contributed_cents,
                    'formatted_total' => '$'.number_format($d->total_contributed_cents / 100, 2).' CAD',
                    'last_donated_at' => $d->last_donated_at,
                ];
            }, $donors->items()),
            'meta' => [
                'current_page' => $donors->currentPage(),
                'last_page' => $donors->lastPage(),
                'per_page' => $donors->perPage(),
                'total' => $donors->total(),
            ],
        ]);
    }
}
