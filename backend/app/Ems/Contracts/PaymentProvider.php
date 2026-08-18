<?php

namespace App\Ems\Contracts;

use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Services\Payments\CheckoutSession;

/**
 * Provider-agnostic payment seam.
 *
 * Implementations must never log or persist card data, access tokens or
 * webhook signing keys.
 */
interface PaymentProvider
{
    public function name(): string;

    /**
     * Create a hosted checkout session for a pending order/payment.
     */
    public function createCheckout(Order $order, Payment $payment): CheckoutSession;

    /**
     * Verify a webhook signature against the raw request body.
     */
    public function verifyWebhookSignature(string $body, string $signature, string $notificationUrl): bool;

    /**
     * Extract a stable event id from a verified webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function webhookEventId(array $payload): ?string;

    /**
     * Extract the webhook event type.
     *
     * @param  array<string, mixed>  $payload
     */
    public function webhookEventType(array $payload): ?string;

    /**
     * Resolve EMS payment / order references from a verified payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *     payment_status?: string|null,
     *     provider_payment_id?: string|null,
     *     provider_order_id?: string|null,
     *     provider_checkout_id?: string|null,
     *     provider_transaction_id?: string|null,
     *     amount?: string|null,
     *     currency?: string|null,
     *     reference?: string|null,
     *     metadata?: array<string, mixed>
     * }
     */
    public function parseWebhook(array $payload): array;

    /**
     * Fetch authoritative payment details from the provider for reconciliation.
     *
     * @return array{
     *     status: string,
     *     amount: string,
     *     currency: string,
     *     provider_payment_id?: string|null,
     *     provider_transaction_id?: string|null,
     *     metadata?: array<string, mixed>
     * }
     */
    public function retrievePayment(Payment $payment): array;

    /**
     * Refund a settled payment, in full or in part.
     */
    public function refund(Payment $payment, ?float $amount = null): Payment;

    /**
     * @return array<string, mixed>|null
     */
    public function retrievePaymentLink(string $checkoutId): ?array;

    public function deletePaymentLink(?string $checkoutId): void;
}
