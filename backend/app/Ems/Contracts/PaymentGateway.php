<?php

namespace App\Ems\Contracts;

use App\Ems\Models\Payment;
use App\Ems\Models\Registration;

/**
 * The seam Phase 3 (Square) plugs into.
 *
 * Nothing implements this in Phase 1 and nothing resolves it from the
 * container. It exists so that when Square lands, the checkout and webhook
 * code has a defined boundary to sit behind and the rest of the EMS keeps
 * talking to Payment/Registration rather than to a vendor SDK.
 *
 * Implementations must never log or persist card data, access tokens or
 * webhook signing keys.
 */
/**
 * Legacy seam kept for reference. Phase 3 implements
 * App\Ems\Contracts\PaymentProvider (order-based hosted checkout).
 *
 * @deprecated Use App\Ems\Contracts\PaymentProvider
 */
interface PaymentGateway
{
    /**
     * Begin a hosted checkout for a registration and return the URL the
     * attendee should be redirected to.
     */
    public function createCheckout(Registration $registration): string;

    /**
     * Reconcile a provider webhook against a recorded payment.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload, string $signature): ?Payment;

    /**
     * Refund a settled payment, in full or in part.
     */
    public function refund(Payment $payment, ?float $amount = null): Payment;
}
