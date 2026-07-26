<?php

namespace App\Ems\Services\Payments;

use App\Ems\Contracts\PaymentProvider;
use App\Ems\Enums\PaymentProvider as PaymentProviderEnum;
use App\Ems\Exceptions\EmsException;
use App\Ems\Services\Payments\Providers\SquarePaymentProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the configured payment provider implementation.
 */
class PaymentProviderManager
{
    public function __construct(
        private readonly SquarePaymentProvider $square,
    ) {
    }

    public function default(): PaymentProvider
    {
        return $this->driver((string) config('ems.payments.default_provider', 'square'));
    }

    public function driver(string|PaymentProviderEnum $provider): PaymentProvider
    {
        $name = $provider instanceof PaymentProviderEnum ? $provider->value : $provider;

        return match ($name) {
            PaymentProviderEnum::Square->value => $this->square,
            default => throw new EmsException(
                'The configured payment provider is not available.',
                ['provider' => ["Unsupported payment provider [{$name}]."]],
                Response::HTTP_SERVICE_UNAVAILABLE
            ),
        };
    }

    public function enabled(): bool
    {
        return (bool) config('ems.payments.enabled', false);
    }
}
