<?php

namespace App\Donations\Services;

use App\Donations\Enums\DonationStatus;
use App\Donations\Models\Donation;
use App\Donations\Models\DonationRefund;
use App\Donations\Notifications\DonationConfirmationNotification;
use App\Ems\Services\Square\SquareClient;
use App\Models\User;
use App\Services\Security\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DonationPaymentService
{
    public function __construct(
        private readonly SquareClient $squareClient,
        private readonly AuditLogger $auditLogger
    ) {}

    public function markPaid(Donation $donation, ?string $squarePaymentId = null, ?string $squareOrderId = null): Donation
    {
        return DB::transaction(function () use ($donation, $squarePaymentId, $squareOrderId) {
            $locked = Donation::where('id', $donation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === DonationStatus::Paid) {
                return $locked;
            }

            $locked->status = DonationStatus::Paid;
            $locked->paid_at = $locked->paid_at ?? now();

            if ($squarePaymentId) {
                $locked->square_payment_id = $squarePaymentId;
            }
            if ($squareOrderId) {
                $locked->square_order_id = $squareOrderId;
            }

            $locked->save();

            // Send notification
            try {
                Notification::route('mail', $locked->donor_email)
                    ->notify(new DonationConfirmationNotification($locked));
            } catch (\Throwable $e) {
                Log::warning('donations.confirmation_email_failed', [
                    'donation_id' => $locked->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $this->auditLogger->log(
                'donation_paid',
                $locked,
                'Payment confirmed for donation '.$locked->donation_number,
                [
                    'donation_uuid' => $locked->uuid,
                    'amount_cents' => $locked->amount_cents,
                    'square_payment_id' => $squarePaymentId,
                ]
            );

            return $locked;
        });
    }

    public function markFailed(Donation $donation, string $reason): Donation
    {
        return DB::transaction(function () use ($donation, $reason) {
            $locked = Donation::where('id', $donation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === DonationStatus::Paid) {
                return $locked;
            }

            $locked->status = DonationStatus::Failed;
            $locked->save();

            $this->auditLogger->log(
                'donation_failed',
                $locked,
                'Payment failed for donation '.$locked->donation_number.': '.$reason,
                ['donation_uuid' => $locked->uuid, 'reason' => $reason]
            );

            return $locked;
        });
    }

    public function refundDonation(Donation $donation, string $reason, ?User $actor = null): DonationRefund
    {
        return DB::transaction(function () use ($donation, $reason, $actor) {
            $locked = Donation::where('id', $donation->id)->lockForUpdate()->firstOrFail();

            $existingRefund = DonationRefund::where('donation_id', $locked->id)
                ->where('status', 'completed')
                ->first();

            if ($existingRefund !== null) {
                return $existingRefund;
            }

            if ($locked->status !== DonationStatus::Paid) {
                throw ValidationException::withMessages([
                    'donation' => ['Only paid donations can be refunded.'],
                ]);
            }

            $squareRefundId = null;

            if ($this->squareClient->enabled() && $locked->square_payment_id) {
                $payload = [
                    'idempotency_key' => 'don_rf_'.Str::uuid(),
                    'amount_money' => [
                        'amount' => $locked->amount_cents,
                        'currency' => $locked->currency,
                    ],
                    'payment_id' => $locked->square_payment_id,
                    'reason' => $reason ?: 'Donation refund requested by administrator',
                ];

                $res = $this->squareClient->post('/v2/refunds', $payload);
                $squareRefundId = data_get($res, 'refund.id');
            }

            $refund = DonationRefund::create([
                'donation_id' => $locked->id,
                'amount_cents' => $locked->amount_cents,
                'currency' => $locked->currency,
                'reason' => $reason,
                'square_refund_id' => $squareRefundId,
                'status' => 'completed',
                'processed_by' => $actor?->id,
                'processed_at' => now(),
            ]);

            $locked->status = DonationStatus::Refunded;
            $locked->refunded_at = now();
            $locked->save();

            $this->auditLogger->log(
                'donation_refunded',
                $locked,
                'Refunded donation '.$locked->donation_number.': '.$reason,
                [
                    'donation_uuid' => $locked->uuid,
                    'amount_cents' => $locked->amount_cents,
                    'reason' => $reason,
                ],
                $actor?->id
            );

            return $refund;
        });
    }
}
