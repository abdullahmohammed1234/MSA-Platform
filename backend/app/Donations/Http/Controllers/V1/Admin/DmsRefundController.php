<?php

namespace App\Donations\Http\Controllers\V1\Admin;

use App\Donations\Models\Donation;
use App\Donations\Services\DonationPaymentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DmsRefundController extends Controller
{
    public function __construct(
        private readonly DonationPaymentService $paymentService
    ) {}

    public function refund(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasRole('super-admin') && ! $user->hasRole('admin') && ! $user->hasRole('dms-administrator') && ! $user->hasPermissionTo('donations.refund'))) {
            return response()->json(['message' => 'Unauthorized to process refunds.'], 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $donation = Donation::where('uuid', $uuid)->first();
        if (! $donation) {
            return response()->json(['message' => 'Donation not found.'], 404);
        }

        $refund = $this->paymentService->refundDonation($donation, $validated['reason'], $user);

        return response()->json([
            'success' => true,
            'message' => 'Donation refund processed successfully via Square.',
            'refund' => [
                'uuid' => $refund->uuid,
                'amount_cents' => $refund->amount_cents,
                'reason' => $refund->reason,
                'square_refund_id' => $refund->square_refund_id,
                'status' => $refund->status,
                'processed_at' => $refund->processed_at?->toIso8601String(),
            ],
        ]);
    }
}
