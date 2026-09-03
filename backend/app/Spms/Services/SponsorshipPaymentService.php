<?php

namespace App\Spms\Services;

use App\Ems\Services\Square\SquareClient;
use App\Models\User;
use App\Services\Security\AuditLogger;
use App\Spms\Models\Payment;
use App\Spms\Models\Sponsorship;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SponsorshipPaymentService
{
    public function __construct(
        private readonly SquareClient $squareClient,
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * Create a Square online payment link for a sponsorship commitment.
     */
    public function createSquareCheckout(Sponsorship $sponsorship, int $amountCents, ?string $redirectUrl = null): array
    {
        return DB::transaction(function () use ($sponsorship, $amountCents, $redirectUrl) {
            $payment = $sponsorship->payments()->create([
                'payment_method' => 'square',
                'amount_cents' => $amountCents,
                'currency' => 'CAD',
                'status' => 'pending',
                'notes' => 'Square online sponsorship checkout initiated',
            ]);

            $idempotencyKey = 'spms-pay-' . $payment->uuid;

            try {
                $locationId = config('ems.square.location_id');

                $payload = [
                    'idempotency_key' => $idempotencyKey,
                    'order' => [
                        'location_id' => $locationId,
                        'line_items' => [
                            [
                                'name' => "SFU MSA Sponsorship: {$sponsorship->title}",
                                'quantity' => '1',
                                'base_price_money' => [
                                    'amount' => $amountCents,
                                    'currency' => 'CAD',
                                ],
                            ],
                        ],
                    ],
                    'checkout_options' => [
                        'redirect_url' => $redirectUrl ?? config('app.url') . "/sponsorship/admin/sponsorships/{$sponsorship->uuid}",
                    ],
                ];

                $response = $this->squareClient->post("/v2/locations/{$locationId}/checkouts", $payload);

                if ($response->successful()) {
                    $checkoutData = $response->json('checkout');
                    $payment->update([
                        'square_checkout_id' => $checkoutData['id'] ?? null,
                        'square_order_id' => $checkoutData['order']['id'] ?? null,
                    ]);

                    return [
                        'payment_uuid' => $payment->uuid,
                        'checkout_url' => $checkoutData['checkout_page_url'] ?? null,
                        'square_checkout_id' => $checkoutData['id'] ?? null,
                    ];
                }

                Log::error('Square checkout creation failed for SPMS', ['response' => $response->body()]);
                throw new \RuntimeException('Unable to initialize Square payment checkout session.');
            } catch (\Throwable $e) {
                $payment->update(['status' => 'failed']);
                throw $e;
            }
        });
    }

    /**
     * Record a manual payment (cheque, e-transfer, cash, bank transfer, invoice).
     */
    public function recordManualPayment(Sponsorship $sponsorship, array $data, ?User $actor = null): Payment
    {
        return DB::transaction(function () use ($sponsorship, $data, $actor) {
            $payment = $sponsorship->payments()->create([
                'commitment_id' => $data['commitment_id'] ?? null,
                'payment_method' => $data['payment_method'],
                'amount_cents' => $data['amount_cents'],
                'currency' => 'CAD',
                'status' => 'completed',
                'reference_number' => $data['reference_number'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
                'recorded_by' => $actor?->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->recalculateFinancials($sponsorship);

            if ($actor !== null) {
                $this->auditLogger->log(
                    'spms.payment.recorded',
                    'spms_payment',
                    (string) $payment->id,
                    [
                        'payment_number' => $payment->payment_number,
                        'amount_cents' => $payment->amount_cents,
                        'payment_method' => $payment->payment_method->value,
                        'reference_number' => $payment->reference_number,
                    ],
                    $actor
                );
            }

            return $payment;
        });
    }

    /**
     * Handle incoming Square payment webhook update for SPMS.
     */
    public function processSquareWebhook(array $payload): void
    {
        $type = $payload['type'] ?? null;
        if (!in_array($type, ['payment.updated', 'payment.created'], true)) {
            return;
        }

        $paymentData = $payload['data']['object']['payment'] ?? null;
        if (!$paymentData) return;

        $squarePaymentId = $paymentData['id'] ?? null;
        $orderId = $paymentData['order_id'] ?? null;
        $status = $paymentData['status'] ?? null;

        $payment = Payment::where('square_order_id', $orderId)
            ->orWhere('square_payment_id', $squarePaymentId)
            ->first();

        if (!$payment) return;

        if ($status === 'COMPLETED' && $payment->status !== 'completed') {
            DB::transaction(function () use ($payment, $squarePaymentId) {
                $payment->update([
                    'status' => 'completed',
                    'square_payment_id' => $squarePaymentId,
                    'paid_at' => now(),
                ]);

                $this->recalculateFinancials($payment->sponsorship);

                $this->auditLogger->log(
                    'spms.square.payment_completed',
                    'spms_payment',
                    (string) $payment->id,
                    [
                        'payment_number' => $payment->payment_number,
                        'square_payment_id' => $squarePaymentId,
                        'amount_cents' => $payment->amount_cents,
                    ]
                );
            });
        }
    }

    /**
     * Recalculate sponsorship total paid cents and update financial status.
     */
    public function recalculateFinancials(Sponsorship $sponsorship): void
    {
        $totalPaid = (int) $sponsorship->payments()->where('status', 'completed')->sum('amount_cents');

        $sponsorship->total_paid_cents = $totalPaid;

        if ($totalPaid >= $sponsorship->total_committed_cents && $sponsorship->total_committed_cents > 0) {
            $sponsorship->financial_status = 'paid';
        } elseif ($totalPaid > 0) {
            $sponsorship->financial_status = 'partially_paid';
        } elseif ($sponsorship->total_committed_cents > 0) {
            $sponsorship->financial_status = 'committed';
        } else {
            $sponsorship->financial_status = 'uncommitted';
        }

        $sponsorship->save();
    }
}
