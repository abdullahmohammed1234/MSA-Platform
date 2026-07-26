<?php

namespace App\Ems\Services\Payments;

/**
 * Result of creating a hosted checkout with an external provider.
 */
final class CheckoutSession
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $checkoutUrl,
        public readonly ?string $checkoutId = null,
        public readonly ?string $providerOrderId = null,
        public readonly array $metadata = [],
    ) {
    }
}
