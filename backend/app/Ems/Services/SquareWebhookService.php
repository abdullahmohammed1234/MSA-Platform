<?php

namespace App\Ems\Services;

use App\Ems\Enums\PaymentProvider as PaymentProviderEnum;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\WebhookEventStatus;
use App\Ems\Exceptions\EmsException;
use App\Ems\Jobs\ProcessSquareWebhookJob;
use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Models\WebhookEvent;
use App\Ems\Services\Payments\PaymentProviderManager;
use App\Ems\Services\Square\SquareCatalogService;
use App\Ems\Services\Square\SquarePosIngestService;
use App\Ems\Services\Square\SquareRefundService;
use App\Ems\Services\Square\SquareTerminalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verify, persist, and process Square webhooks.
 *
 * Unmatched events are stored as unmatched — never discarded as processed —
 * so they can be reconciled after EMS mappings become available.
 */
class SquareWebhookService
{
    public function __construct(
        private readonly PaymentProviderManager $providers,
        private readonly PaymentFulfillmentService $fulfillment,
        private readonly SquarePosIngestService $pos,
        private readonly SquareRefundService $refunds,
        private readonly SquareCatalogService $catalog,
        private readonly SquareTerminalService $terminal,
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

        $record = DB::transaction(function () use ($payload, $eventId, $eventType): WebhookEvent {
            $existing = WebhookEvent::query()
                ->where('provider', 'square')
                ->where('event_id', $eventId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $record = new WebhookEvent();
            $record->provider = 'square';
            $record->event_id = $eventId;
            $record->event_type = $eventType;
            $record->status = WebhookEventStatus::Received->value;
            $record->payload = $this->redactPayload($payload);
            $record->save();

            return $record;
        });

        $status = $this->normalizeStatus($record->status);

        if ($status === WebhookEventStatus::Processed) {
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.webhooks.square.duplicate_ignored', [
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                ]);

            return $record->payment_id ? Payment::query()->find($record->payment_id) : null;
        }

        if ($status === WebhookEventStatus::Processing) {
            return $record->payment_id ? Payment::query()->find($record->payment_id) : null;
        }

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.webhooks.square.received', [
                'event_id' => $eventId,
                'event_type' => $eventType,
            ]);

        if ((string) config('queue.default') === 'sync') {
            return $this->processRecord($record);
        }

        ProcessSquareWebhookJob::dispatch($record->id);

        return $record->payment_id ? Payment::query()->find($record->payment_id) : null;
    }

    public function processRecord(WebhookEvent $record): ?Payment
    {
        $status = $this->normalizeStatus($record->status);
        if ($status === WebhookEventStatus::Processed) {
            return $record->payment_id ? Payment::query()->find($record->payment_id) : null;
        }

        $record->status = WebhookEventStatus::Processing->value;
        $record->last_attempt_at = now();
        $record->retry_count = (int) $record->retry_count + 1;
        $record->save();

        $payload = is_array($record->payload) ? $record->payload : [];
        $eventType = (string) ($record->event_type ?? '');

        try {
            $payment = $this->dispatchEvent($eventType, $payload, $record);
            $record->refresh();

            if ($this->normalizeStatus($record->status) === WebhookEventStatus::Processed) {
                return $payment ?? ($record->payment_id ? Payment::query()->find($record->payment_id) : null);
            }

            if ($payment !== null) {
                $record->status = WebhookEventStatus::Processed->value;
                $record->payment_id = $payment->id;
                $record->order_id = $payment->order_id;
                $record->processed_at = now();
                $record->failure_reason = null;
                $record->save();

                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->info('ems.webhooks.square.processed', [
                        'event_id' => $record->event_id,
                        'event_type' => $eventType,
                        'payment_uuid' => $payment->uuid,
                    ]);

                return $payment;
            }

            $record->status = WebhookEventStatus::Unmatched->value;
            $record->failure_reason = $record->failure_reason
                ?: 'No EMS mapping for this Square event.';
            $record->save();

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->warning('ems.webhooks.square.unmatched', [
                    'event_id' => $record->event_id,
                    'event_type' => $eventType,
                ]);

            return null;
        } catch (\Throwable $e) {
            $record->status = WebhookEventStatus::Failed->value;
            $record->failure_reason = mb_substr($e->getMessage(), 0, 500);
            $record->save();

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->error('ems.webhooks.square.failed', [
                    'event_id' => $record->event_id,
                    'event_type' => $eventType,
                    'error' => $e->getMessage(),
                ]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchEvent(string $eventType, array $payload, WebhookEvent $record): ?Payment
    {
        if ($eventType === 'catalog.version.updated') {
            $this->catalog->pullRemoteChanges();

            $record->status = WebhookEventStatus::Processed->value;
            $record->processed_at = now();
            $record->failure_reason = null;
            $record->save();

            return null;
        }

        if (str_starts_with($eventType, 'refund.')) {
            $refund = data_get($payload, 'data.object.refund', data_get($payload, 'data.object', []));
            if (! is_array($refund)) {
                return null;
            }

            $applied = $this->refunds->applyFromWebhook($refund);
            if ($applied?->payment) {
                $record->order_id = $applied->payment->order_id;

                return $applied->payment;
            }

            return null;
        }

        if (str_starts_with($eventType, 'terminal.checkout.')) {
            $checkout = data_get($payload, 'data.object.checkout', data_get($payload, 'data.object', []));
            if (! is_array($checkout)) {
                return null;
            }

            return $this->terminal->handleCheckoutUpdated($checkout);
        }

        if (str_starts_with($eventType, 'payment.') || str_starts_with($eventType, 'order.')) {
            $provider = $this->providers->driver(PaymentProviderEnum::Square);
            $parsed = $provider->parseWebhook($payload);
            $payment = $this->resolvePayment($parsed, $payload);

            if ($payment !== null) {
                return $this->fulfillExisting($payment, $parsed, $eventType, $record);
            }

            $squarePayment = data_get($payload, 'data.object.payment');
            if (is_array($squarePayment)) {
                $ingested = $this->pos->ingestPayment($squarePayment);
                $this->rememberIngestFailure($record);

                return $ingested;
            }

            if (str_starts_with($eventType, 'order.')) {
                $ingested = $this->pos->ingestFromOrderWebhook($payload);
                $this->rememberIngestFailure($record);

                return $ingested;
            }
        }

        return null;
    }

    private function rememberIngestFailure(WebhookEvent $record): void
    {
        $reason = $this->pos->lastUnmatchedReason();
        if ($reason) {
            $record->failure_reason = $reason;
            $record->save();
        }
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function fulfillExisting(Payment $payment, array $parsed, string $eventType, WebhookEvent $record): Payment
    {
        $status = $parsed['payment_status'] ?? null;

        if (str_contains($eventType, 'refund')) {
            return $payment;
        }

        if ($this->isSupersededSquareCapture($payment, $parsed)) {
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.checkout.superseded_payment_ignored', [
                    'payment_uuid' => $payment->uuid,
                    'order_uuid' => $payment->order?->uuid,
                    'checkout_version' => $payment->checkout_version,
                    'square_order_id' => $parsed['provider_order_id'] ?? null,
                    'square_payment_id' => $parsed['provider_payment_id'] ?? null,
                    'reason' => 'superseded_checkout',
                ]);

            return $payment;
        }

        return match ($status) {
            PaymentStatus::Paid->value => $this->applyPaidFromWebhook($payment, $parsed, $record),
            PaymentStatus::Failed->value => $this->fulfillment->markFailed(
                $payment,
                'Provider reported payment failure.',
                $parsed
            ),
            PaymentStatus::Cancelled->value => $this->fulfillment->markCancelled(
                $payment,
                'Provider reported payment cancellation.'
            ),
            default => $payment,
        };
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function applyPaidFromWebhook(Payment $payment, array $parsed, WebhookEvent $record): Payment
    {
        $payment->refresh();

        if ($payment->status === PaymentStatus::Cancelled && $payment->wasBuyerCancelled()) {
            return $this->fulfillment->recordStaleCaptureAfterBuyerCancel(
                $payment,
                isset($parsed['provider_payment_id']) ? (string) $parsed['provider_payment_id'] : null,
                isset($parsed['provider_order_id']) ? (string) $parsed['provider_order_id'] : null,
                webhookEventId: $record->event_id,
                source: 'square_webhook',
            );
        }

        return $this->fulfillment->markPaid($payment, $parsed);
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

        $superseded = $this->findPaymentBySupersededCheckout($parsed);
        if ($superseded !== null) {
            return $superseded;
        }

        $reference = $parsed['reference'] ?? null;

        if (is_string($reference) && str_starts_with($reference, 'ORD-')) {
            $order = Order::query()->where('reference', $reference)->first();

            if ($order !== null) {
                $payment = $order->latestPayment;
                if ($payment !== null && $this->squareIdsBelongToCurrentCheckout($payment, $parsed)) {
                    return $payment;
                }
            }
        }

        $note = data_get($payload, 'data.object.payment.note');
        if (is_string($note) && preg_match('/ORD-[A-Z0-9]+/', $note, $matches)) {
            $order = Order::query()->where('reference', $matches[0])->first();

            if ($order !== null) {
                $payment = $order->latestPayment;
                if ($payment !== null && $this->squareIdsBelongToCurrentCheckout($payment, $parsed)) {
                    return $payment;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function isSupersededSquareCapture(Payment $payment, array $parsed): bool
    {
        $orderId = isset($parsed['provider_order_id']) ? (string) $parsed['provider_order_id'] : '';
        $checkoutId = isset($parsed['provider_checkout_id']) ? (string) $parsed['provider_checkout_id'] : '';

        if ($orderId !== '' && $payment->provider_order_id && $orderId === (string) $payment->provider_order_id) {
            return false;
        }
        if ($checkoutId !== '' && $payment->provider_checkout_id && $checkoutId === (string) $payment->provider_checkout_id) {
            return false;
        }

        return $payment->recordsSupersededSquareId(
            $orderId !== '' ? $orderId : null,
            $checkoutId !== '' ? $checkoutId : null
        );
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function squareIdsBelongToCurrentCheckout(Payment $payment, array $parsed): bool
    {
        $orderId = isset($parsed['provider_order_id']) ? (string) $parsed['provider_order_id'] : '';
        if ($orderId === '' || ! $payment->provider_order_id) {
            return true;
        }

        return $orderId === (string) $payment->provider_order_id;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function findPaymentBySupersededCheckout(array $parsed): ?Payment
    {
        $orderId = isset($parsed['provider_order_id']) ? (string) $parsed['provider_order_id'] : '';
        $checkoutId = isset($parsed['provider_checkout_id']) ? (string) $parsed['provider_checkout_id'] : '';
        if ($orderId === '' && $checkoutId === '') {
            return null;
        }

        $needle = $orderId !== '' ? $orderId : $checkoutId;

        $candidates = Payment::query()
            ->where('provider', PaymentProviderEnum::Square->value)
            ->whereNotNull('metadata')
            ->where('metadata', 'like', '%'.$needle.'%')
            ->limit(20)
            ->get();

        foreach ($candidates as $candidate) {
            if ($candidate->recordsSupersededSquareId(
                $orderId !== '' ? $orderId : null,
                $checkoutId !== '' ? $checkoutId : null
            )) {
                return $candidate;
            }
        }

        return null;
    }

    private function normalizeStatus(mixed $status): WebhookEventStatus
    {
        if ($status instanceof WebhookEventStatus) {
            return $status;
        }

        return WebhookEventStatus::tryFrom((string) $status) ?? WebhookEventStatus::Received;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redactPayload(array $payload): array
    {
        $redacted = $payload;
        unset($redacted['merchant_id']);

        return $redacted;
    }
}
