<?php

namespace App\Services\Operations\Rules;

use App\Donations\Models\DonationRefund;
use App\Services\Operations\OperationalAlertService;
use Illuminate\Support\Facades\Schema;

class DonationOperationalRules
{
    public function __construct(
        private OperationalAlertService $alertService
    ) {}

    public function detect(): int
    {
        if (! Schema::hasTable('donation_refunds')) {
            return 0;
        }

        $detected = 0;

        // Pending or failed donation refunds
        $unreconciledRefunds = DonationRefund::whereIn('status', ['pending', 'failed'])
            ->orWhereNull('square_refund_id')
            ->with('donation')
            ->get();

        foreach ($unreconciledRefunds as $refund) {
            $donationNumber = $refund->donation?->donation_number ?? 'N/A';
            $amountFormatted = '$' . number_format($refund->amount_cents / 100, 2);

            $this->alertService->upsertAlert([
                'category' => 'donations',
                'severity' => 'high',
                'title' => "Unreconciled Donation Refund: {$donationNumber}",
                'description' => "Donation refund of {$amountFormatted} for donation #{$donationNumber} has status '{$refund->status}' and lacks completed gateway reconciliation.",
                'source_type' => 'DonationRefund',
                'source_id' => (string) $refund->id,
                'rule_key' => 'donation_refund_unreconciled',
                'action_url' => '/admin/donations',
                'metadata' => [
                    'refund_id' => $refund->id,
                    'donation_id' => $refund->donation_id,
                    'donation_number' => $donationNumber,
                    'amount_cents' => $refund->amount_cents,
                    'refund_status' => $refund->status,
                    'square_refund_id' => $refund->square_refund_id,
                ],
            ]);
            $detected++;
        }

        return $detected;
    }
}
