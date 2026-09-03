<?php

namespace App\Donations\Http\Controllers\V1;

use App\Donations\Services\DonationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicDonationController extends Controller
{
    public function __construct(
        private readonly DonationService $donationService
    ) {}

    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'donor_name' => 'required|string|max:255',
            'donor_email' => 'required|email|max:255',
            'amount_cents' => 'required|integer|min:100|max:1000000',
            'is_anonymous' => 'nullable|boolean',
            'dedication' => 'nullable|string|max:500',
        ]);

        $userId = $request->user()?->id;

        $res = $this->donationService->createCheckout($validated, $userId);

        return response()->json([
            'success' => true,
            'donation' => [
                'uuid' => $res['donation']->uuid,
                'donation_number' => $res['donation']->donation_number,
                'amount_cents' => $res['donation']->amount_cents,
                'formatted_amount' => $res['donation']->formatted_amount,
                'currency' => $res['donation']->currency,
                'status' => $res['donation']->status->value,
            ],
            'checkout_url' => $res['checkout_url'],
        ]);
    }

    public function status(string $uuid): JsonResponse
    {
        $donation = $this->donationService->getStatus($uuid);

        if (! $donation) {
            return response()->json(['message' => 'Donation record not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'donation' => [
                'uuid' => $donation->uuid,
                'donation_number' => $donation->donation_number,
                'amount_cents' => $donation->amount_cents,
                'formatted_amount' => $donation->formatted_amount,
                'currency' => $donation->currency,
                'status' => $donation->status->value,
                'donor_name' => $donation->is_anonymous ? 'Anonymous' : $donation->donor_name,
                'donor_email' => $donation->is_anonymous ? '***@***.***' : $donation->donor_email,
                'is_anonymous' => $donation->is_anonymous,
                'dedication' => $donation->dedication,
                'paid_at' => $donation->paid_at?->toIso8601String(),
                'created_at' => $donation->created_at->toIso8601String(),
            ],
        ]);
    }
}
