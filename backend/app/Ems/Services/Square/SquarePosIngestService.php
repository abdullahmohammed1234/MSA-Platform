<?php

namespace App\Ems\Services\Square;

use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\PaymentSourceChannel;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\RegistrationType;
use App\Ems\Models\Order;
use App\Ems\Models\OrderItem;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\TicketType;
use App\Ems\Services\PaymentFulfillmentService;
use App\Ems\Services\Ticketing\TicketCodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns a Square POS/Reader payment into EMS order/registration/ticket
 * when the sold catalog variation is mapped to an EMS ticket type.
 */
class SquarePosIngestService
{
    public function __construct(
        private readonly SquareClient $square,
        private readonly SquareCatalogService $catalog,
        private readonly TicketCodeGenerator $codes,
        private readonly PaymentFulfillmentService $fulfillment,
    ) {
    }

    /**
     * @param  array<string, mixed>  $squarePayment
     */
    public function ingestPayment(array $squarePayment): ?Payment
    {
        $paymentId = (string) ($squarePayment['id'] ?? '');
        if ($paymentId === '') {
            return null;
        }

        $existing = Payment::query()->where('provider_payment_id', $paymentId)->first();
        if ($existing !== null) {
            if ($existing->status !== PaymentStatus::Paid) {
                $status = strtoupper((string) ($squarePayment['status'] ?? ''));
                if (in_array($status, ['COMPLETED', 'APPROVED', 'PAID'], true)) {
                    return $this->fulfillment->markPaid($existing, $this->providerData($squarePayment));
                }
            }

            return $existing;
        }

        $status = strtoupper((string) ($squarePayment['status'] ?? ''));
        if (! in_array($status, ['COMPLETED', 'APPROVED', 'PAID'], true)) {
            return null;
        }

        $orderId = (string) ($squarePayment['order_id'] ?? '');
        $orderPayload = $orderId !== '' ? $this->retrieveOrder($orderId) : [];
        $lineItems = $orderPayload['line_items'] ?? [];

        $matched = [];
        foreach ($lineItems as $line) {
            $variationId = (string) ($line['catalog_object_id'] ?? '');
            if ($variationId === '') {
                continue;
            }
            $mapping = $this->catalog->findByVariationId($variationId);
            if ($mapping?->ticketType) {
                $qty = max(1, (int) ($line['quantity'] ?? 1));
                $matched[] = ['mapping' => $mapping, 'quantity' => $qty, 'line' => $line];
            }
        }

        if ($matched === []) {
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.square.pos.unmapped', [
                    'square_payment_id' => $paymentId,
                    'square_order_id' => $orderId ?: null,
                ]);

            return null;
        }

        $channel = PaymentSourceChannel::fromSquarePayment($squarePayment);
        $buyer = $this->buyerFrom($squarePayment, $orderPayload);

        return DB::transaction(function () use ($matched, $squarePayment, $paymentId, $orderId, $channel, $buyer): Payment {
            $firstType = $matched[0]['mapping']->ticketType;
            $event = $firstType->event()->lockForUpdate()->firstOrFail();

            $amountMoney = $squarePayment['amount_money'] ?? [];
            $amount = isset($amountMoney['amount'])
                ? number_format(((int) $amountMoney['amount']) / 100, 2, '.', '')
                : '0.00';
            $currency = (string) ($amountMoney['currency'] ?? $firstType->currency ?? 'CAD');

            $order = new Order();
            $order->reference = $this->codes->orderReference();
            $order->event_id = $event->id;
            $order->user_id = null;
            $order->buyer_name = $buyer['name'];
            $order->buyer_email = $buyer['email'];
            $order->buyer_phone = $buyer['phone'];
            $order->total_amount = $amount;
            $order->currency = $currency;
            $order->status = OrderStatus::Pending;
            $order->source_channel = $channel->value;
            $order->metadata = [
                'source' => $channel->value,
                'square_order_id' => $orderId,
            ];
            $order->save();

            $quantity = 0;
            $ticketType = $firstType;
            foreach ($matched as $row) {
                /** @var TicketType $type */
                $type = $row['mapping']->ticketType;
                $qty = (int) $row['quantity'];
                $quantity += $qty;
                $ticketType = $type;

                $item = new OrderItem();
                $item->order_id = $order->id;
                $item->ticket_type_id = $type->id;
                $item->name = $type->name;
                $item->quantity = $qty;
                $item->unit_price = $type->price;
                $item->line_total = ((float) $type->price) * $qty;
                $item->currency = $type->currency;
                $item->save();

                $type->quantity_sold = (int) $type->quantity_sold + $qty;
                $type->save();
            }

            $registration = new Registration();
            $registration->reference = $this->codes->registrationReference();
            $registration->event_id = $event->id;
            $registration->user_id = null;
            $registration->ticket_type_id = $ticketType->id;
            $registration->order_id = $order->id;
            $registration->attendee_name = $buyer['name'];
            $registration->attendee_email = $buyer['email'];
            $registration->attendee_phone = $buyer['phone'];
            $registration->status = RegistrationStatus::AwaitingPayment;
            $registration->type = RegistrationType::Paid;
            $registration->quantity = max(1, $quantity);
            $registration->amount_due = $amount;
            $registration->currency = $currency;
            $registration->registered_at = now();
            $registration->metadata = [
                'source' => $channel === PaymentSourceChannel::Terminal ? 'terminal' : 'square_pos',
                'walk_in' => true,
            ];
            $registration->save();

            $payment = new Payment();
            $payment->order_id = $order->id;
            $payment->registration_id = $registration->id;
            $payment->amount = $amount;
            $payment->currency = $currency;
            $payment->provider = 'square';
            $payment->status = PaymentStatus::Processing;
            $payment->provider_payment_id = $paymentId;
            $payment->provider_order_id = $orderId ?: null;
            $payment->provider_transaction_id = isset($squarePayment['receipt_number'])
                ? (string) $squarePayment['receipt_number']
                : $paymentId;
            $payment->source_channel = $channel->value;
            $payment->terminal_checkout_id = $squarePayment['terminal_checkout_id'] ?? null;
            $payment->metadata = [
                'square_status' => strtoupper((string) ($squarePayment['status'] ?? '')),
                'square_source_type' => $squarePayment['source_type'] ?? null,
            ];
            $payment->save();

            $fulfilled = $this->fulfillment->markPaid($payment, $this->providerData($squarePayment));

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.square.pos.ingested', [
                    'square_payment_id' => $paymentId,
                    'square_order_id' => $orderId ?: null,
                    'ems_order_reference' => $order->reference,
                    'registration_uuid' => $registration->uuid,
                    'channel' => $channel->value,
                ]);

            return $fulfilled;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function retrieveOrder(string $orderId): array
    {
        try {
            $response = $this->square->get('/v2/orders/' . urlencode($orderId));

            return is_array($response['order'] ?? null) ? $response['order'] : [];
        } catch (\Throwable $e) {
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->warning('ems.square.pos.order_lookup_failed', [
                    'square_order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $payment
     * @param  array<string, mixed>  $order
     * @return array{name: string, email: string, phone: string|null}
     */
    private function buyerFrom(array $payment, array $order): array
    {
        $email = (string) ($payment['buyer_email_address'] ?? $order['buyer_email'] ?? '');
        $phone = $payment['buyer_phone_number'] ?? null;
        $name = trim((string) data_get($order, 'fulfillments.0.pickup_details.recipient.display_name', ''));
        if ($name === '') {
            $given = (string) data_get($payment, 'billing_address.first_name', '');
            $family = (string) data_get($payment, 'billing_address.last_name', '');
            $name = trim($given . ' ' . $family);
        }
        if ($name === '') {
            $name = 'Walk-in';
        }

        return [
            'name' => $name,
            'email' => $email,
            'phone' => is_string($phone) && $phone !== '' ? $phone : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $squarePayment
     * @return array<string, mixed>
     */
    private function providerData(array $squarePayment): array
    {
        return [
            'provider_payment_id' => $squarePayment['id'] ?? null,
            'provider_order_id' => $squarePayment['order_id'] ?? null,
            'provider_transaction_id' => $squarePayment['receipt_number'] ?? ($squarePayment['id'] ?? null),
            'metadata' => [
                'square_status' => strtoupper((string) ($squarePayment['status'] ?? '')),
            ],
        ];
    }
}
