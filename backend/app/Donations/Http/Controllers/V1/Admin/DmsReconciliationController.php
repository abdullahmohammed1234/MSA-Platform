<?php

namespace App\Donations\Http\Controllers\V1\Admin;

use App\Donations\Services\DonationReconciliationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DmsReconciliationController extends Controller
{
    public function __construct(
        private readonly DonationReconciliationService $reconciliationService
    ) {}

    public function reconcile(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasRole('super-admin') && ! $user->hasRole('admin') && ! $user->hasRole('dms-administrator') && ! $user->hasPermissionTo('donations.reconcile'))) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $reconciledCount = $this->reconciliationService->reconcilePendingDonations();

        return response()->json([
            'success' => true,
            'message' => "Reconciliation run completed. {$reconciledCount} pending donation(s) updated.",
            'reconciled_count' => $reconciledCount,
        ]);
    }
}
