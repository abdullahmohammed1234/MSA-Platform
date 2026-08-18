<?php

namespace App\Ems\Services;

use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Exceptions\EmsException;
use App\Ems\Models\Event;
use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Services\Payments\PaymentProviderManager;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resume, cancel, and expire hosted Square Payment Links.
 */
class CheckoutLifecycleService
{
    public function __construct(
        private readonly PaymentProviderManager $providers,
        private readonly PaymentFulfillmentService $fulfillment,
        private readonly CheckoutService $checkout,
    ) {
    }

    /**
     * @param  array<string, mixed>  $current
     * @return array{order: Order, registration: mixed, checkout_url: string|null, payment: Payment|null}
     */
    public function resume(Event $event, string $email, ?string $orderUuid = null, array $current = []): array
    {
        if (! empty($current['ticket_type_id'])) {
            $found = $this->checkout->findResumableCheckout($event, $email);
            $registration = $found['registration'] ?? null;
            $meta = is_array($registration?->metadata) ? $registration->metadata : [];
            $nameParts = preg_split('/\s+/', trim((string) ($registration?->attendee_name ?? '')), 2) ?: [];

            return $this->checkout->checkout($event, [
                'first_name' => $current['first_name'] ?? $meta['first_name'] ?? ($nameParts[0] ?? 'Guest'),
                'last_name' => $current['last_name'] ?? $meta['last_name'] ?? ($nameParts[1] ?? 'Attendee'),
                'email' => $email,
                'phone' => $current['phone'] ?? $registration?->attendee_phone,
                'ticket_type_id' => $current['ticket_type_id'],
                'quantity' => $current['quantity'] ?? $registration?->quantity ?? 1,
                'promo_code' => $current['promo_code'] ?? null,
                'notes' => $current['notes'] ?? $registration?->notes,
            ]);
        }

        if ($orderUuid) {
            $order = Order::query()->where('uuid', $orderUuid)->where('event_id', $event->id)->first();
            if ($order && strcasecmp((string) $order->buyer_email, $email) === 0) {
                $found = $this->checkout->findResumableCheckout($event, $email);
                if ($found) {
                    return $found;
                }
            }
        }

        $found = $this->checkout->findResumableCheckout($event, $email);
        if ($found === null) {
            throw new EmsException(
                'No pending Square checkout to resume. Start a new checkout.',
                ['checkout' => ['No resumable payment session was found.']],
                Response::HTTP_NOT_FOUND
            );
        }

        return $found;
    }

    public function cancel(Payment $payment, string $reason = 'Checkout cancelled by buyer.'): Payment
    {
        $payment->loadMissing('order');

        if (in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded], true)) {
            return $payment;
        }

        $this->providers->default()->deletePaymentLink($payment->provider_checkout_id);

        return $this->fulfillment->markCancelled($payment, $reason);
    }

    public function expireStale(): int
    {
        $expired = Payment::query()
            ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Processing->value])
            ->whereNotNull('checkout_expires_at')
            ->where('checkout_expires_at', '<=', now())
            ->limit(100)
            ->get();

        $count = 0;
        foreach ($expired as $payment) {
            try {
                if ($payment->provider_payment_id) {
                    $remote = $this->providers->default()->retrievePayment($payment);
                    if (($remote['status'] ?? null) === PaymentStatus::Paid->value) {
                        $this->fulfillment->markPaid($payment, $remote);
                        $count++;
                        continue;
                    }
                }
            } catch (\Throwable) {
                // Fall through to abandon.
            }

            $this->providers->default()->deletePaymentLink($payment->provider_checkout_id);

            $locked = Payment::query()->whereKey($payment->id)->first();
            if ($locked && in_array($locked->status, [PaymentStatus::Pending, PaymentStatus::Processing], true)) {
                $this->fulfillment->markAbandoned($locked, 'Checkout expired before payment was completed.');
                $count++;
            }
        }

        if ($count > 0) {
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.payments.expired', ['count' => $count]);
        }

        return $count;
    }
}
