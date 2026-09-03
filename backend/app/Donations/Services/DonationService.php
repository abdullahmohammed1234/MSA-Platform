<?php

namespace App\Donations\Services;

use App\Donations\Enums\DonationStatus;
use App\Donations\Models\Donation;
use App\Ems\Services\Square\SquareClient;
use App\Services\Security\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DonationService
{
    public function __construct(
        private readonly SquareClient $squareClient,
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * Create a pending donation and generate a Square Checkout Payment Link.
     */
    public function createCheckout(array $data, ?int $userId = null): array
    {
        $amountCents = (int) ($data['amount_cents'] ?? 0);
        if ($amountCents < 100 || $amountCents > 1000000) {
            throw ValidationException::withMessages([
                'amount' => ['Donation amount must be between $1.00 and $10,000.00 CAD.'],
            ]);
        }

        $donorName = trim((string) ($data['donor_name'] ?? ''));
        $donorEmail = strtolower(trim((string) ($data['donor_email'] ?? '')));
        $isAnonymous = (bool) ($data['is_anonymous'] ?? false);
        $dedication = ! empty($data['dedication']) ? trim((string) $data['dedication']) : null;

        if ($donorName === '' || ! filter_var($donorEmail, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'donor_email' => ['A valid name and email address are required.'],
            ]);
        }

        $donation = DB::transaction(function () use ($userId, $donorName, $donorEmail, $amountCents, $isAnonymous, $dedication) {
            return Donation::create([
                'user_id' => $userId,
                'donor_name' => $donorName,
                'donor_email' => $donorEmail,
                'amount_cents' => $amountCents,
                'currency' => 'CAD',
                'status' => DonationStatus::Pending,
                'is_anonymous' => $isAnonymous,
                'dedication' => $dedication,
            ]);
        });

        // Generate Square Checkout Link
        $checkoutUrl = null;
        $squareCheckoutId = null;
        $squareOrderId = null;

        if ($this->squareClient->enabled()) {
            try {
                $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');
                $redirectUrl = $frontendUrl.'/donate/success?donation_uuid='.$donation->uuid;

                $payload = [
                    'idempotency_key' => 'don_chk_'.$donation->uuid,
                    'order' => [
                        'location_id' => $this->squareClient->locationId(),
                        'reference_id' => $donation->donation_number,
                        'line_items' => [
                            [
                                'name' => 'SFU MSA Donation ('.$donation->donation_number.')',
                                'quantity' => '1',
                                'base_price_money' => [
                                    'amount' => $amountCents,
                                    'currency' => 'CAD',
                                ],
                            ],
                        ],
                    ],
                    'checkout_options' => [
                        'redirect_url' => $redirectUrl,
                        'ask_for_shipping_address' => false,
                        'allow_tipping' => false,
                    ],
                    'pre_populate_buyer_email' => $donorEmail,
                ];

                $res = $this->squareClient->post('/v2/online-checkout/payment-links', $payload);

                $checkoutUrl = data_get($res, 'payment_link.url');
                $squareCheckoutId = data_get($res, 'payment_link.id');
                $squareOrderId = data_get($res, 'payment_link.order_id');

                $donation->update([
                    'square_checkout_id' => $squareCheckoutId,
                    'square_order_id' => $squareOrderId,
                ]);
            } catch (\Throwable $e) {
                Log::error('donations.square_checkout_failed', [
                    'donation_uuid' => $donation->uuid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->auditLogger->log(
            'donation_created',
            $donation,
            'Created donation '.$donation->donation_number,
            ['amount_cents' => $amountCents]
        );

        return [
            'donation' => $donation,
            'checkout_url' => $checkoutUrl,
        ];
    }

    public function getStatus(string $uuid): ?Donation
    {
        return Donation::where('uuid', $uuid)->first();
    }
}
