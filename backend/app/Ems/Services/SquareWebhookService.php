<?php

namespace App\Ems\Services;

use App\Ems\Enums\PaymentProvider as PaymentProviderEnum;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Exceptions\EmsException;
use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Models\WebhookEvent;
use App\Ems\Services\Payments\PaymentProviderManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Secure, idempotent Square webhook processing.
 */
class SquareWebhookService
{
    public function __construct(
        private readonly PaymentProviderManager $providers,
        private readonly PaymentFulfillmentService $fulfillment,
    ) {
    }

    public function handle(string $rawBody, string $signature, string $notificationUrl): ?Payment
    {
        $provider = $this->providers->driver(PaymentProviderEnum::Square);

        if (! $provider->verifyWebhookSignature($rawBody, $signature, $notificationUrl)) {
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->warning('ems.webhooks.square.invalid_signature');

            throw new EmsException(
                'Invalid webhook signature.',
                [],
                Response::HTTP_UNAUTHORIZED
            );
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($rawBody, true) ?? [];

        if ($payload === []) {
            throw new EmsException('Invalid webhook payload.', [], Response::HTTP_BAD_REQUEST);
        }

        $eventId = $provider->webhookEventId($payload);
        $eventType = $provider->webhookEventType($payload);

        if ($eventId === null) {
            throw new EmsException('Webhook missing event id.', [], Response::HTTP_BAD_REQUEST);
        }

        return DB::transaction(function () use ($provider, $payload, $eventId, $eventType): ?Payment {
            $existing = WebhookEvent::query()
                ->where('provider', 'square')
                ->where('event_id', $eventId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->info('ems.webhooks.square.duplicate_ignored', [
                        'event_id' => $eventId,
                        'event_type' => $eventType,
                    ]);

                return $existing->payment_id
                    ? Payment::query()->find($existing->payment_id)
                    : null;
            }

            $parsed = $provider->parseWebhook($payload);
            $payment = $this->resolvePayment($parsed, $payload);

            $record = new WebhookEvent();
            $record->provider = 'square';
            $record->event_id = $eventId;
            $record->event_type = $eventType;
            $record->status = 'processed';
            $record->payload = $this->redactPayload($payload);
            $record->processed_at = now();
            $record->order_id = $payment?->order_id;
            $record->payment_id = $payment?->id;
            $record->save();

            if ($payment === null) {
                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->warning('ems.webhooks.square.unmatched', [
                        'event_id' => $eventId,
                        'event_type' => $eventType,
                    ]);

                return null;
            }

            $status = $parsed['payment_status'] ?? null;

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.webhooks.square.processed', [
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                    'payment_uuid' => $payment->uuid,
                    'mapped_status' => $status,
                ]);

            return match ($status) {
                PaymentStatus::Paid->value => $this->fulfillment->markPaid($payment, $parsed),
                PaymentStatus::Failed->value => $this->fulfillment->markFailed(
                    $payment,
                    'Provider reported payment failure.',
                    $parsed
                ),
                PaymentStatus::Cancelled->value => $this->fulfillment->markCancelled(
                    $payment,
                    'Provider reported payment cancellation.'
                ),
                PaymentStatus::Refunded->value => $this->recordRefundFoundation($payment, $parsed),
                default => $payment,
            };
        });
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $payload
     */
    private function resolvePayment(array $parsed, array $payload): ?Payment
    {
        if (! empty($parsed['provider_payment_id'])) {
            $byProviderId = Payment::query()
                ->where('provider', PaymentProviderEnum::Square->value)
                ->where('provider_payment_id', $parsed['provider_payment_id'])
                ->first();

            if ($byProviderId !== null) {
                return $byProviderId;
            }
        }

        if (! empty($parsed['provider_order_id'])) {
            $byOrderId = Payment::query()
                ->where('provider', PaymentProviderEnum::Square->value)
                ->where('provider_order_id', $parsed['provider_order_id'])
                ->first();

            if ($byOrderId !== null) {
                return $byOrderId;
            }
        }

        if (! empty($parsed['provider_checkout_id'])) {
            $byCheckout = Payment::query()
                ->where('provider', PaymentProviderEnum::Square->value)
                ->where('provider_checkout_id', $parsed['provider_checkout_id'])
                ->first();

            if ($byCheckout !== null) {
                return $byCheckout;
            }
        }

        $reference = $parsed['reference'] ?? null;

        if (is_string($reference) && str_starts_with($reference, 'ORD-')) {
            $order = Order::query()->where('reference', $reference)->first();

            if ($order !== null) {
                return $order->latestPayment;
            }
        }

        // Square payment.updated often includes order reference in note.
        $note = data_get($payload, 'data.object.payment.note');
        if (is_string($note) && preg_match('/ORD-[A-Z0-9]+/', $note, $matches)) {
            $order = Order::query()->where('reference', $matches[0])->first();

            if ($order !== null) {
                return $order->latestPayment;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function recordRefundFoundation(Payment $payment, array $parsed): Payment
    {
        // Foundation only — do not reverse tickets or registrations in Phase 3.
        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.webhooks.square.refund_noted', [
                'payment_uuid' => $payment->uuid,
                'provider_payment_id' => $parsed['provider_payment_id'] ?? null,
            ]);

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redactPayload(array $payload): array
    {
        $redacted = $payload;
        unset(
            $redacted['merchant_id'],
        );

        return $redacted;
    }
}
