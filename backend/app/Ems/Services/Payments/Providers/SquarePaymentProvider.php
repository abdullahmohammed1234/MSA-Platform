<?php

namespace App\Ems\Services\Payments\Providers;

use App\Ems\Contracts\PaymentProvider;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Exceptions\EmsException;
use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Services\Payments\CheckoutSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Square Hosted Checkout (Payment Links) provider.
 *
 * Talks to Square over HTTPS using Laravel's HTTP client. Access tokens and
 * webhook secrets never leave the server.
 */
class SquarePaymentProvider implements PaymentProvider
{
    public function name(): string
    {
        return 'square';
    }

    public function createCheckout(Order $order, Payment $payment): CheckoutSession
    {
        $this->assertConfigured();

        $amountCents = (int) round(((float) $payment->amount) * 100);

        if ($amountCents <= 0) {
            throw new EmsException(
                'Cannot create a Square checkout for a zero-amount order.',
                ['amount' => ['Paid checkout requires a positive amount.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $successUrl = $this->frontendUrl("/events/{$order->event->slug}/checkout/success?order={$order->uuid}");
        $cancelUrl = $this->frontendUrl("/events/{$order->event->slug}/checkout/cancel?order={$order->uuid}");

        $orderPayload = [
            'location_id' => $this->locationId(),
            'reference_id' => $order->reference,
            'line_items' => $order->items->map(function ($item) {
                return [
                    'name' => $item->name,
                    'quantity' => (string) $item->quantity,
                    'base_price_money' => [
                        'amount' => (int) round(((float) $item->unit_price) * 100),
                        'currency' => strtoupper($item->currency),
                    ],
                ];
            })->values()->all(),
        ];

        if ((float) $order->discount_amount > 0.0) {
            $promoCode = $order->promoCode;
            $codeName = $promoCode ? $promoCode->code : 'Discount';

            $orderPayload['discounts'] = [
                [
                    'name' => $codeName,
                    'type' => 'FIXED_AMOUNT',
                    'amount_money' => [
                        'amount' => (int) round(((float) $order->discount_amount) * 100),
                        'currency' => strtoupper($order->currency),
                    ],
                    'scope' => 'ORDER',
                ]
            ];
        }

        $payload = [
            'idempotency_key' => (string) Str::uuid(),
            'checkout_options' => [
                'redirect_url' => $successUrl,
                'ask_for_shipping_address' => false,
            ],
            'pre_populated_data' => [
                'buyer_email' => $order->buyer_email,
            ],
            'order' => $orderPayload,
            'payment_note' => 'MSA EMS order ' . $order->reference,
        ];

        // Square Payment Links API does not accept cancel_url on all versions;
        // store it in metadata for the EMS cancel return page.
        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->baseUrl($this->baseUrl())
            ->post('/v2/online-checkout/payment-links', $payload);

        if (! $response->successful()) {
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->error('ems.payments.square.checkout_failed', [
                    'order_uuid' => $order->uuid,
                    'status' => $response->status(),
                    'body' => $this->safeBody($response->json()),
                ]);

            throw new EmsException(
                'Unable to start Square checkout. Please try again shortly.',
                [],
                Response::HTTP_BAD_GATEWAY
            );
        }

        $data = $response->json('payment_link') ?? [];
        $checkoutUrl = (string) ($data['url'] ?? '');
        $checkoutId = isset($data['id']) ? (string) $data['id'] : null;
        $providerOrderId = isset($data['order_id']) ? (string) $data['order_id'] : null;

        if ($checkoutUrl === '') {
            throw new EmsException(
                'Square did not return a checkout URL.',
                [],
                Response::HTTP_BAD_GATEWAY
            );
        }

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.payments.square.checkout_created', [
                'order_uuid' => $order->uuid,
                'payment_uuid' => $payment->uuid,
                'provider_checkout_id' => $checkoutId,
                'cancel_url' => $cancelUrl,
            ]);

        return new CheckoutSession(
            checkoutUrl: $checkoutUrl,
            checkoutId: $checkoutId,
            providerOrderId: $providerOrderId,
            metadata: [
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'square_payment_link' => [
                    'id' => $checkoutId,
                    'version' => $data['version'] ?? null,
                ],
            ],
        );
    }

    public function verifyWebhookSignature(string $body, string $signature, string $notificationUrl): bool
    {
        $key = (string) config('ems.payments.square.webhook_signature_key');

        if ($key === '' || $signature === '') {
            return false;
        }

        // Square webhook signature: HMAC-SHA256 of notificationUrl + body,
        // base64 encoded. See Square Docs — Webhook signature validation.
        $payload = $notificationUrl . $body;
        $expected = base64_encode(hash_hmac('sha256', $payload, $key, true));

        return hash_equals($expected, $signature);
    }

    public function webhookEventId(array $payload): ?string
    {
        $id = $payload['event_id'] ?? $payload['eventId'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function webhookEventType(array $payload): ?string
    {
        $type = $payload['type'] ?? null;

        return is_string($type) && $type !== '' ? $type : null;
    }

    public function parseWebhook(array $payload): array
    {
        $type = $this->webhookEventType($payload);
        $data = $payload['data']['object'] ?? $payload['data'] ?? [];

        $paymentObject = $data['payment'] ?? $data['refund'] ?? $data;

        $status = null;
        $rawStatus = strtoupper((string) ($paymentObject['status'] ?? ''));

        $status = match (true) {
            in_array($rawStatus, ['COMPLETED', 'APPROVED', 'PAID'], true) => PaymentStatus::Paid->value,
            $rawStatus === 'FAILED' => PaymentStatus::Failed->value,
            in_array($rawStatus, ['CANCELED', 'CANCELLED'], true) => PaymentStatus::Cancelled->value,
            str_contains((string) $type, 'refund') => PaymentStatus::Refunded->value,
            default => null,
        };

        $amountMoney = $paymentObject['amount_money']
            ?? $paymentObject['total_money']
            ?? [];

        $amount = null;
        if (isset($amountMoney['amount'])) {
            $amount = number_format(((int) $amountMoney['amount']) / 100, 2, '.', '');
        }

        $reference = $paymentObject['reference_id']
            ?? $paymentObject['order_id']
            ?? ($paymentObject['note'] ?? null);

        return [
            'payment_status' => $status,
            'provider_payment_id' => isset($paymentObject['id']) ? (string) $paymentObject['id'] : null,
            'provider_order_id' => isset($paymentObject['order_id']) ? (string) $paymentObject['order_id'] : null,
            'provider_checkout_id' => null,
            'provider_transaction_id' => isset($paymentObject['receipt_number'])
                ? (string) $paymentObject['receipt_number']
                : (isset($paymentObject['id']) ? (string) $paymentObject['id'] : null),
            'amount' => $amount,
            'currency' => isset($amountMoney['currency']) ? (string) $amountMoney['currency'] : null,
            'reference' => is_string($reference) ? $reference : null,
            'metadata' => [
                'square_type' => $type,
                'square_status' => $rawStatus,
            ],
        ];
    }

    public function retrievePayment(Payment $payment): array
    {
        $this->assertConfigured();

        $providerPaymentId = $payment->provider_payment_id;

        if (! $providerPaymentId) {
            throw new EmsException(
                'Payment has no Square payment id to reconcile.',
                [],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->baseUrl($this->baseUrl())
            ->get('/v2/payments/' . urlencode($providerPaymentId));

        if (! $response->successful()) {
            throw new EmsException(
                'Unable to retrieve payment from Square.',
                [],
                Response::HTTP_BAD_GATEWAY
            );
        }

        $object = $response->json('payment') ?? [];
        $amountMoney = $object['amount_money'] ?? [];
        $rawStatus = strtoupper((string) ($object['status'] ?? ''));

        $status = match (true) {
            in_array($rawStatus, ['COMPLETED', 'APPROVED', 'PAID'], true) => PaymentStatus::Paid->value,
            $rawStatus === 'FAILED' => PaymentStatus::Failed->value,
            in_array($rawStatus, ['CANCELED', 'CANCELLED'], true) => PaymentStatus::Cancelled->value,
            default => PaymentStatus::Pending->value,
        };

        return [
            'status' => $status,
            'amount' => isset($amountMoney['amount'])
                ? number_format(((int) $amountMoney['amount']) / 100, 2, '.', '')
                : (string) $payment->amount,
            'currency' => (string) ($amountMoney['currency'] ?? $payment->currency),
            'provider_payment_id' => isset($object['id']) ? (string) $object['id'] : $providerPaymentId,
            'provider_transaction_id' => isset($object['receipt_number'])
                ? (string) $object['receipt_number']
                : null,
            'metadata' => ['square_status' => $rawStatus],
        ];
    }

    public function refund(Payment $payment, ?float $amount = null): Payment
    {
        // Foundation only — full refund workflow belongs with Phase 6 finance.
        throw new EmsException(
            'Refunds are not enabled in this phase.',
            [],
            Response::HTTP_NOT_IMPLEMENTED
        );
    }

    private function assertConfigured(): void
    {
        if (! config('ems.payments.enabled', false)) {
            throw new EmsException(
                'Payments are currently disabled.',
                [],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        if ($this->accessToken() === '' || $this->locationId() === '') {
            throw new EmsException(
                'Square is not configured.',
                [],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }
    }

    private function accessToken(): string
    {
        return (string) config('ems.payments.square.access_token', '');
    }

    private function locationId(): string
    {
        return (string) config('ems.payments.square.location_id', '');
    }

    private function baseUrl(): string
    {
        $env = strtolower((string) config('ems.payments.square.environment', 'sandbox'));

        return $env === 'production'
            ? 'https://connect.squareup.com'
            : 'https://connect.squareupsandbox.com';
    }

    private function frontendUrl(string $path): string
    {
        $base = rtrim((string) config('ems.public.frontend_url'), '/');

        return $base . '/' . ltrim($path, '/');
    }

    /**
     * @param  mixed  $body
     * @return array<string, mixed>
     */
    private function safeBody(mixed $body): array
    {
        if (! is_array($body)) {
            return [];
        }

        unset($body['access_token'], $body['authorization']);

        return $body;
    }
}
