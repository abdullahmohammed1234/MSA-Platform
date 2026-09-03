<?php

namespace App\Donations\Http\Controllers\V1\Admin;

use App\Donations\Enums\DonationStatus;
use App\Donations\Models\Donation;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DmsDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasRole('super-admin') && ! $user->hasRole('admin') && ! $user->hasRole('dms-administrator') && ! $user->hasRole('dms-staff') && ! $user->hasPermissionTo('donations.view'))) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $totalPaidCents = Donation::paid()->sum('amount_cents');
        $totalPaidCount = Donation::paid()->count();
        $totalPendingCount = Donation::pending()->count();
        $totalRefundedCount = Donation::where('status', DonationStatus::Refunded->value)->count();

        $startOfMonth = Carbon::now()->startOfMonth();
        $thisMonthCents = Donation::paid()->where('paid_at', '>=', $startOfMonth)->sum('amount_cents');
        $thisMonthCount = Donation::paid()->where('paid_at', '>=', $startOfMonth)->count();

        $recentDonations = Donation::query()
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($d) {
                return [
                    'uuid' => $d->uuid,
                    'donation_number' => $d->donation_number,
                    'donor_name' => $d->is_anonymous ? 'Anonymous' : $d->donor_name,
                    'donor_email' => $d->is_anonymous ? '***@***.***' : $d->donor_email,
                    'amount_cents' => $d->amount_cents,
                    'formatted_amount' => $d->formatted_amount,
                    'status' => $d->status->value,
                    'is_anonymous' => $d->is_anonymous,
                    'created_at' => $d->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'metrics' => [
                'total_revenue_cents' => (int) $totalPaidCents,
                'total_revenue_formatted' => '$'.number_format($totalPaidCents / 100, 2).' CAD',
                'total_donations_count' => (int) $totalPaidCount,
                'pending_donations_count' => (int) $totalPendingCount,
                'refunded_donations_count' => (int) $totalRefundedCount,
                'this_month_cents' => (int) $thisMonthCents,
                'this_month_formatted' => '$'.number_format($thisMonthCents / 100, 2).' CAD',
                'this_month_count' => (int) $thisMonthCount,
            ],
            'recent_donations' => $recentDonations,
        ]);
    }
}
