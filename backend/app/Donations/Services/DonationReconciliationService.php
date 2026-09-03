<?php

namespace App\Donations\Services;

use App\Donations\Enums\DonationStatus;
use App\Donations\Models\Donation;
use App\Ems\Services\Square\SquareClient;
use Illuminate\Support\Facades\Log;

class DonationReconciliationService
{
    public function __construct(
        private readonly SquareClient $squareClient,
        private readonly DonationPaymentService $paymentService
    ) {}

    public function reconcilePendingDonations(): int
    {
        if (! $this->squareClient->enabled()) {
            return 0;
        }

        $pendingDonations = Donation::query()
            ->where('status', DonationStatus::Pending->value)
            ->where('created_at', '<=', now()->subMinutes(10))
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        $reconciledCount = 0;

        foreach ($pendingDonations as $donation) {
            try {
                if ($donation->square_order_id) {
                    $res = $this->squareClient->get('/v2/orders/'.$donation->square_order_id);
                    $order = data_get($res, 'order', []);
                    $state = strtolower((string) data_get($order, 'state', ''));
                    $tenders = data_get($order, 'tenders', []);

                    if ($state === 'completed' && ! empty($tenders)) {
                        $paymentId = data_get($tenders, '0.payment_id');
                        $this->paymentService->markPaid($donation, $paymentId, $donation->square_order_id);
                        $reconciledCount++;
                    } elseif ($state === 'canceled') {
                        $this->paymentService->markFailed($donation, 'Square order canceled');
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('donations.reconcile_single_failed', [
                    'donation_uuid' => $donation->uuid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $reconciledCount;
    }
}
