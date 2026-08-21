<?php

namespace App\Ems\Services\Square;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\PaymentSourceChannel;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\RegistrationType;
use App\Ems\Models\Event;
use App\Ems\Models\Order;
use App\Ems\Models\OrderItem;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\TicketType;
use App\Ems\Services\PaymentFulfillmentService;
use App\Ems\Services\Ticketing\TicketCodeGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns an unmatched Square payment (POS/Reader, Online Store, or other
 * catalog sale) into EMS order/registration/ticket rows when the sold
 * catalog variation is mapped to an EMS ticket type.
 *
 * Square Online Store is a first-class channel: it is not labeled as POS
 * walk-in, requires a captured payment, enforces capacity, and creates one
 * registration per ticket type.
 */
class SquarePosIngestService
{
    private ?string $lastUnmatchedReason = null;

    private ?string $lastUnmatchedCode = null;

    public function __construct(
        private readonly SquareClient $square,
        private readonly SquareCatalogService $catalog,
        private readonly TicketCodeGenerator $codes,
        private readonly PaymentFulfillmentService $fulfillment,
    ) {
    }

    public function lastUnmatchedReason(): ?string
    {
        return $this->lastUnmatchedReason;
    }

    public function lastUnmatchedCode(): ?string
    {
        return $this->lastUnmatchedCode;
    }

    /**
     * @param  array<string, mixed>  $squarePayment
     */
    public function ingestPayment(array $squarePayment): ?Payment
    {
        $this->lastUnmatchedReason = null;
        $this->lastUnmatchedCode = null;

        $paymentId = (string) ($squarePayment['id'] ?? '');
        if ($paymentId === '') {
            $this->unmatched('invalid_payment', 'Square payment is missing an id.');

            return null;
        }

        $channel = PaymentSourceChannel::fromSquarePayment($squarePayment);
        $status = strtoupper((string) ($squarePayment['status'] ?? ''));
        $orderId = (string) ($squarePayment['order_id'] ?? '');

        $existing = Payment::query()->where('provider_payment_id', $paymentId)->first();
        if ($existing !== null) {
            return $this->fulfillExistingIfSettled($existing, $squarePayment, $channel, $status);
        }

        if ($orderId !== '') {
            $existingOrderSale = $this->existingSaleForSquareOrder($orderId);
            if ($existingOrderSale !== null) {
                return $this->attachSplitTenderOrReturn(
                    $existingOrderSale,
                    $squarePayment,
                    $channel,
                    $status
                );
            }
        }

        if (! $this->isSettledStatus($status, $channel)) {
            $this->logChannel($channel, 'payment_pending', [
                'square_payment_id' => $paymentId,
                'square_order_id' => $orderId ?: null,
                'square_status' => $status ?: null,
                'source_channel' => $channel->value,
            ]);
            $this->unmatched(
                'pending_payment',
                'Square payment is not captured (status '.$status.').'
            );

            return null;
        }

        if ($orderId === '') {
            $this->unmatched('invalid_order', 'Square payment has no order_id.');

            return null;
        }

        $orderPayload = $this->retrieveOrder($orderId);
        if ($orderPayload === []) {
            $this->unmatched('invalid_order', 'Square order could not be retrieved.');

            return null;
        }

        $matched = $this->matchedLineItems($orderPayload['line_items'] ?? []);
        if ($matched === []) {
            $this->logChannel($channel, 'invalid_mapping', [
                'square_payment_id' => $paymentId,
                'square_order_id' => $orderId,
                'source_channel' => $channel->value,
            ]);
            $this->unmatched('unmapped', 'No EMS catalog mapping for this Square sale.');

            return null;
        }

        $buyer = $this->buyerFrom($squarePayment, $orderPayload, $channel);

        try {
            return DB::transaction(function () use (
                $matched,
                $squarePayment,
                $paymentId,
                $orderId,
                $channel,
                $buyer,
                $status
            ): ?Payment {
                $again = Payment::query()
                    ->where('provider_payment_id', $paymentId)
                    ->lockForUpdate()
                    ->first();
                if ($again !== null) {
                    return $this->fulfillExistingIfSettled($again, $squarePayment, $channel, $status);
                }

                $orderSale = $this->existingSaleForSquareOrder($orderId, true);
                if ($orderSale !== null) {
                    return $this->attachSplitTenderOrReturn($orderSale, $squarePayment, $channel, $status);
                }

                $grouped = $this->groupMatchedByTicketType($matched);
                $this->lockEventsAndTypes($grouped);

                if ($channel === PaymentSourceChannel::SquareOnlineStore) {
                    $capacityError = $this->capacityFailure($grouped);
                    if ($capacityError !== null) {
                        $this->logChannel($channel, 'capacity_failed', [
                            'square_payment_id' => $paymentId,
                            'square_order_id' => $orderId,
                            'source_channel' => $channel->value,
                            'reason' => $capacityError,
                        ]);
                        $this->unmatched('capacity', $capacityError);

                        return null;
                    }

                    $eligibilityError = $this->eligibilityFailure($grouped);
                    if ($eligibilityError !== null) {
                        $this->unmatched('ineligible_event', $eligibilityError);

                        return null;
                    }
                }

                return $this->createSale(
                    $grouped,
                    $squarePayment,
                    $paymentId,
                    $orderId,
                    $channel,
                    $buyer
                );
            });
        } catch (QueryException $e) {
            $existingAfterRace = Payment::query()->where('provider_payment_id', $paymentId)->first()
                ?? ($orderId !== '' ? $this->existingSaleForSquareOrder($orderId) : null);

            if ($existingAfterRace !== null) {
                $this->logChannel($channel, 'duplicate', [
                    'square_payment_id' => $paymentId,
                    'square_order_id' => $orderId ?: null,
                    'source_channel' => $channel->value,
                    'ems_order_reference' => $existingAfterRace->order?->reference,
                ]);

                return $existingAfterRace;
            }

            throw $e;
        }
    }

    /**
     * Order webhooks do not embed a payment object. Look up tenders and ingest
     * only once a captured payment exists.
     *
     * @param  array<string, mixed>  $payload
     */
    public function ingestFromOrderWebhook(array $payload): ?Payment
    {
        $this->lastUnmatchedReason = null;
        $this->lastUnmatchedCode = null;

        $orderId = $this->extractOrderIdFromWebhook($payload);
        if ($orderId === '') {
            $this->unmatched('invalid_order', 'Order webhook is missing an order id.');

            return null;
        }

        $existing = $this->existingSaleForSquareOrder($orderId);
        if ($existing !== null) {
            $this->logChannel(
                PaymentSourceChannel::tryFrom((string) $existing->source_channel) ?? PaymentSourceChannel::Other,
                'duplicate',
                [
                    'square_order_id' => $orderId,
                    'square_payment_id' => $existing->provider_payment_id,
                    'source_channel' => $existing->source_channel,
                    'ingestion_result' => 'already_imported',
                ]
            );

            return $existing;
        }

        $order = $this->retrieveOrder($orderId);
        if ($order === []) {
            $this->unmatched('invalid_order', 'Square order could not be retrieved.');

            return null;
        }

        foreach ($this->paymentIdsFromOrder($order) as $paymentId) {
            $squarePayment = $this->retrievePayment($paymentId);
            if ($squarePayment === []) {
                continue;
            }

            $ingested = $this->ingestPayment($squarePayment);
            if ($ingested !== null) {
                return $ingested;
            }
        }

        if ($this->lastUnmatchedReason === null) {
            $this->unmatched(
                'pending_payment',
                'Order webhook has no captured Square payment to ingest.'
            );
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $squarePayment
     */
    private function fulfillExistingIfSettled(
        Payment $existing,
        array $squarePayment,
        PaymentSourceChannel $channel,
        string $status
    ): Payment {
        if ($existing->status === PaymentStatus::Paid) {
            $this->logChannel($channel, 'duplicate', [
                'square_payment_id' => $existing->provider_payment_id,
                'square_order_id' => $existing->provider_order_id,
                'source_channel' => $channel->value,
                'ingestion_result' => 'already_paid',
            ]);

            return $existing;
        }

        if ($this->isSettledStatus($status, $channel)) {
            return $this->fulfillment->markPaid($existing, $this->providerData($squarePayment));
        }

        return $existing;
    }

    /**
     * Same Square order, different Square payment (split tender): record the
     * extra payment on the existing EMS order. Do not issue a second ticket batch.
     *
     * @param  array<string, mixed>  $squarePayment
     */
    private function attachSplitTenderOrReturn(
        Payment $existingSale,
        array $squarePayment,
        PaymentSourceChannel $channel,
        string $status
    ): Payment {
        $paymentId = (string) ($squarePayment['id'] ?? '');
        if ($paymentId !== '' && (string) $existingSale->provider_payment_id === $paymentId) {
            return $this->fulfillExistingIfSettled($existingSale, $squarePayment, $channel, $status);
        }

        $already = Payment::query()->where('provider_payment_id', $paymentId)->first();
        if ($already !== null) {
            return $this->fulfillExistingIfSettled($already, $squarePayment, $channel, $status);
        }

        $this->logChannel($channel, 'duplicate', [
            'square_payment_id' => $paymentId,
            'square_order_id' => $existingSale->provider_order_id,
            'source_channel' => $channel->value,
            'ingestion_result' => 'split_tender_attached',
            'ems_order_reference' => $existingSale->order?->reference,
        ]);

        if (! $this->isSettledStatus($status, $channel) || $existingSale->order_id === null) {
            return $existingSale;
        }

        $amountMoney = $squarePayment['amount_money'] ?? [];
        $amount = isset($amountMoney['amount'])
            ? number_format(((int) $amountMoney['amount']) / 100, 2, '.', '')
            : (string) $existingSale->amount;

        $extra = new Payment();
        $extra->order_id = $existingSale->order_id;
        $extra->registration_id = $existingSale->registration_id;
        $extra->amount = $amount;
        $extra->currency = (string) ($amountMoney['currency'] ?? $existingSale->currency);
        $extra->provider = 'square';
        $extra->status = PaymentStatus::Processing;
        $extra->provider_payment_id = $paymentId;
        $extra->provider_order_id = $existingSale->provider_order_id;
        $extra->provider_transaction_id = isset($squarePayment['receipt_number'])
            ? (string) $squarePayment['receipt_number']
            : $paymentId;
        $extra->source_channel = $channel->value;
        $extra->metadata = [
            'square_status' => $status,
            'split_tender' => true,
            'primary_payment_uuid' => $existingSale->uuid,
        ];
        $extra->save();

        if ($existingSale->status === PaymentStatus::Paid) {
            return $this->fulfillment->markPaid($extra, $this->providerData($squarePayment));
        }

        return $this->fulfillExistingIfSettled($existingSale, $squarePayment, $channel, $status);
    }

    /**
     * @param  list<array{mapping: \App\Ems\Models\SquareCatalogMapping, quantity: int, variation_id: string}>  $matched
     * @param  array<string, mixed>  $squarePayment
     * @param  array{name: string, email: string, phone: string|null}  $buyer
     */
    private function createSale(
        array $grouped,
        array $squarePayment,
        string $paymentId,
        string $orderId,
        PaymentSourceChannel $channel,
        array $buyer
    ): Payment {
        $first = $grouped[array_key_first($grouped)];
        /** @var TicketType $firstType */
        $firstType = $first['ticket_type'];
        $primaryEvent = Event::query()->whereKey($firstType->event_id)->firstOrFail();

        $amountMoney = $squarePayment['amount_money'] ?? [];
        $amount = isset($amountMoney['amount'])
            ? number_format(((int) $amountMoney['amount']) / 100, 2, '.', '')
            : '0.00';
        $currency = (string) ($amountMoney['currency'] ?? $firstType->currency ?? 'CAD');

        $order = new Order();
        $order->reference = $this->codes->orderReference();
        $order->event_id = $primaryEvent->id;
        $order->user_id = null;
        $order->buyer_name = $buyer['name'];
        $order->buyer_email = $buyer['email'];
        $order->buyer_phone = $buyer['phone'];
        $order->total_amount = $amount;
        $order->currency = $currency;
        $order->status = OrderStatus::Pending;
        $order->source_channel = $channel->value;
        $order->provider_order_id = $orderId;
        $order->metadata = [
            'source' => $channel->value,
            'square_order_id' => $orderId,
        ];
        $order->save();

        $firstRegistration = null;
        foreach ($grouped as $row) {
            /** @var TicketType $type */
            $type = TicketType::query()->whereKey($row['ticket_type']->id)->lockForUpdate()->firstOrFail();
            $qty = (int) $row['quantity'];
            $lineTotal = ((float) $type->price) * $qty;

            $item = new OrderItem();
            $item->order_id = $order->id;
            $item->ticket_type_id = $type->id;
            $item->name = $type->name;
            $item->quantity = $qty;
            $item->unit_price = $type->price;
            $item->line_total = $lineTotal;
            $item->currency = $type->currency;
            $item->metadata = [
                'square_catalog_variation_id' => $row['variation_id'],
            ];
            $item->save();

            $type->quantity_sold = (int) $type->quantity_sold + $qty;
            $type->save();

            $registration = new Registration();
            $registration->reference = $this->codes->registrationReference();
            $registration->event_id = $type->event_id;
            $registration->user_id = null;
            $registration->ticket_type_id = $type->id;
            $registration->order_id = $order->id;
            $registration->attendee_name = $buyer['name'];
            $registration->attendee_email = $buyer['email'];
            $registration->attendee_phone = $buyer['phone'];
            $registration->status = RegistrationStatus::AwaitingPayment;
            $registration->type = RegistrationType::Paid;
            $registration->quantity = $qty;
            $registration->amount_due = number_format($lineTotal, 2, '.', '');
            $registration->currency = $currency;
            $registration->registered_at = now();
            $registration->metadata = [
                'source' => $channel->registrationSource(),
                'walk_in' => $channel->isWalkIn(),
                'square_catalog_variation_id' => $row['variation_id'],
            ];
            $registration->save();

            $firstRegistration ??= $registration;
        }

        /** @var Registration $firstRegistration */
        $payment = new Payment();
        $payment->order_id = $order->id;
        $payment->registration_id = $firstRegistration->id;
        $payment->amount = $amount;
        $payment->currency = $currency;
        $payment->provider = 'square';
        $payment->status = PaymentStatus::Processing;
        $payment->provider_payment_id = $paymentId;
        $payment->provider_order_id = $orderId;
        $payment->provider_transaction_id = isset($squarePayment['receipt_number'])
            ? (string) $squarePayment['receipt_number']
            : $paymentId;
        $payment->source_channel = $channel->value;
        $payment->terminal_checkout_id = $squarePayment['terminal_checkout_id'] ?? null;
        $payment->metadata = [
            'square_status' => strtoupper((string) ($squarePayment['status'] ?? '')),
            'square_source_type' => $squarePayment['source_type'] ?? null,
            'square_product' => data_get($squarePayment, 'application_details.square_product'),
        ];
        $payment->save();

        $fulfilled = $this->fulfillment->markPaid($payment, $this->providerData($squarePayment));

        $this->logChannel($channel, 'ingested', [
            'square_payment_id' => $paymentId,
            'square_order_id' => $orderId,
            'source_channel' => $channel->value,
            'ems_order_reference' => $order->reference,
            'registration_uuids' => $order->registrations()->pluck('uuid')->all(),
            'ingestion_result' => 'created',
        ]);

        return $fulfilled;
    }

    /**
     * @param  array<int|string, mixed>  $lineItems
     * @return list<array{mapping: \App\Ems\Models\SquareCatalogMapping, quantity: int, variation_id: string}>
     */
    private function matchedLineItems(array $lineItems): array
    {
        $matched = [];
        foreach ($lineItems as $line) {
            if (! is_array($line)) {
                continue;
            }
            $variationId = (string) ($line['catalog_object_id'] ?? '');
            if ($variationId === '') {
                continue;
            }
            $qty = (int) ($line['quantity'] ?? 0);
            if ($qty < 1) {
                continue;
            }
            $mapping = $this->catalog->findByVariationId($variationId);
            if ($mapping?->ticketType) {
                $matched[] = [
                    'mapping' => $mapping,
                    'quantity' => $qty,
                    'variation_id' => $variationId,
                ];
            }
        }

        return $matched;
    }

    /**
     * @param  list<array{mapping: \App\Ems\Models\SquareCatalogMapping, quantity: int, variation_id: string}>  $matched
     * @return array<int, array{ticket_type: TicketType, quantity: int, variation_id: string}>
     */
    private function groupMatchedByTicketType(array $matched): array
    {
        $grouped = [];
        foreach ($matched as $row) {
            $type = $row['mapping']->ticketType;
            if ($type === null) {
                continue;
            }
            $id = (int) $type->id;
            if (! isset($grouped[$id])) {
                $grouped[$id] = [
                    'ticket_type' => $type,
                    'quantity' => 0,
                    'variation_id' => $row['variation_id'],
                ];
            }
            $grouped[$id]['quantity'] += (int) $row['quantity'];
        }

        return $grouped;
    }

    /**
     * @param  array<int, array{ticket_type: TicketType, quantity: int, variation_id: string}>  $grouped
     */
    private function lockEventsAndTypes(array $grouped): void
    {
        $eventIds = collect($grouped)
            ->map(fn (array $row): int => (int) $row['ticket_type']->event_id)
            ->unique()
            ->sort()
            ->values();

        foreach ($eventIds as $eventId) {
            Event::query()->whereKey($eventId)->lockForUpdate()->first();
        }

        $typeIds = collect($grouped)->keys()->map(fn ($id): int => (int) $id)->sort()->values();
        foreach ($typeIds as $typeId) {
            $locked = TicketType::query()->whereKey($typeId)->lockForUpdate()->first();
            if ($locked !== null) {
                $grouped[$typeId]['ticket_type'] = $locked;
            }
        }

        foreach ($grouped as $typeId => $row) {
            $locked = TicketType::query()->whereKey($typeId)->first();
            if ($locked !== null) {
                $grouped[$typeId]['ticket_type'] = $locked;
            }
        }
    }

    /**
     * @param  array<int, array{ticket_type: TicketType, quantity: int, variation_id: string}>  $grouped
     */
    private function capacityFailure(array $grouped): ?string
    {
        $qtyByEvent = [];
        foreach ($grouped as $row) {
            $type = TicketType::query()->whereKey($row['ticket_type']->id)->first();
            if ($type === null) {
                return 'Mapped ticket type is missing.';
            }
            if (! $type->hasAvailableQuantity((int) $row['quantity'])) {
                return 'Ticket type capacity is insufficient.';
            }
            $eventId = (int) $type->event_id;
            $qtyByEvent[$eventId] = ($qtyByEvent[$eventId] ?? 0) + (int) $row['quantity'];
        }

        foreach ($qtyByEvent as $eventId => $qty) {
            $event = Event::query()->whereKey($eventId)->first();
            if ($event === null) {
                return 'Mapped event is missing.';
            }
            if (! $event->hasAvailableCapacity($qty)) {
                return 'Event capacity is insufficient.';
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{ticket_type: TicketType, quantity: int, variation_id: string}>  $grouped
     */
    private function eligibilityFailure(array $grouped): ?string
    {
        foreach ($grouped as $row) {
            $event = $row['ticket_type']->event ?? Event::query()->whereKey($row['ticket_type']->event_id)->first();
            if ($event === null) {
                return 'Mapped event is missing.';
            }
            if (in_array($event->status, [EventStatus::Cancelled, EventStatus::Archived], true)) {
                return 'Event is not eligible for ticket sales.';
            }
        }

        return null;
    }

    private function existingSaleForSquareOrder(string $orderId, bool $lock = false): ?Payment
    {
        $query = Payment::query()->where('provider_order_id', $orderId)->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $payment = $query->first();
        if ($payment !== null) {
            return $payment;
        }

        $orderQuery = Order::query()->where('provider_order_id', $orderId);
        if ($lock) {
            $orderQuery->lockForUpdate();
        }
        $order = $orderQuery->first();

        return $order?->latestPayment;
    }

    private function isSettledStatus(string $status, PaymentSourceChannel $channel): bool
    {
        if (in_array($status, ['COMPLETED', 'PAID'], true)) {
            return true;
        }

        return $status === 'APPROVED' && $channel->treatsApprovedAsSettled();
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
     * @return array<string, mixed>
     */
    private function retrievePayment(string $paymentId): array
    {
        try {
            $response = $this->square->get('/v2/payments/' . urlencode($paymentId));

            return is_array($response['payment'] ?? null) ? $response['payment'] : [];
        } catch (\Throwable $e) {
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->warning('ems.square.pos.payment_lookup_failed', [
                    'square_payment_id' => $paymentId,
                    'error' => $e->getMessage(),
                ]);

            return [];
        }
    }

    /**
     * Best-effort Square Customer profile. Failures are ignored.
     *
     * @return array{name: string, email: string, phone: string|null}
     */
    private function retrieveCustomer(string $customerId): array
    {
        try {
            $response = $this->square->get('/v2/customers/' . urlencode($customerId));
            $customer = is_array($response['customer'] ?? null) ? $response['customer'] : [];
            $name = trim(
                (string) ($customer['given_name'] ?? '').' '.(string) ($customer['family_name'] ?? '')
            );
            if ($name === '') {
                $name = (string) ($customer['nickname'] ?? $customer['company_name'] ?? '');
            }

            $phone = $customer['phone_number'] ?? null;

            return [
                'name' => $name,
                'email' => (string) ($customer['email_address'] ?? ''),
                'phone' => is_string($phone) && $phone !== '' ? $phone : null,
            ];
        } catch (\Throwable) {
            return ['name' => '', 'email' => '', 'phone' => null];
        }
    }

    /**
     * @param  array<string, mixed>  $order
     * @return list<string>
     */
    private function paymentIdsFromOrder(array $order): array
    {
        $ids = [];
        foreach ($order['tenders'] ?? [] as $tender) {
            if (! is_array($tender)) {
                continue;
            }
            foreach (['payment_id', 'id'] as $key) {
                $value = (string) ($tender[$key] ?? '');
                if ($value !== '') {
                    $ids[] = $value;
                    break;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractOrderIdFromWebhook(array $payload): string
    {
        $candidates = [
            data_get($payload, 'data.object.payment.order_id'),
            data_get($payload, 'data.object.order.id'),
            data_get($payload, 'data.object.order_updated.order_id'),
            data_get($payload, 'data.object.order_created.order_id'),
            data_get($payload, 'data.id'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payment
     * @param  array<string, mixed>  $order
     * @return array{name: string, email: string, phone: string|null}
     */
    private function buyerFrom(array $payment, array $order, PaymentSourceChannel $channel): array
    {
        $email = trim((string) ($payment['buyer_email_address'] ?? ''));
        if ($email === '') {
            $email = trim((string) ($order['buyer_email'] ?? ''));
        }
        if ($email === '') {
            $email = $this->recipientEmailFromOrder($order);
        }

        $phone = $payment['buyer_phone_number'] ?? null;
        $name = $this->recipientNameFromOrder($order);

        if ($name === '') {
            $given = (string) data_get($payment, 'billing_address.first_name', '');
            $family = (string) data_get($payment, 'billing_address.last_name', '');
            $name = trim($given.' '.$family);
        }

        $customerId = (string) ($payment['customer_id'] ?? $order['customer_id'] ?? '');
        if ($customerId !== '' && ($email === '' || $name === '')) {
            $customer = $this->retrieveCustomer($customerId);
            if ($email === '') {
                $email = $customer['email'];
            }
            if ($name === '') {
                $name = $customer['name'];
            }
            if ((! is_string($phone) || $phone === '') && $customer['phone']) {
                $phone = $customer['phone'];
            }
        }

        if ($name === '') {
            $name = $channel === PaymentSourceChannel::SquareOnlineStore
                ? 'Square Online Guest'
                : 'Walk-in';
        }

        return [
            'name' => $name,
            'email' => $email,
            'phone' => is_string($phone) && $phone !== '' ? $phone : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function recipientNameFromOrder(array $order): string
    {
        $paths = [
            'fulfillments.0.pickup_details.recipient.display_name',
            'fulfillments.0.shipment_details.recipient.display_name',
            'fulfillments.0.delivery_details.recipient.display_name',
            'fulfillments.0.pickup_details.recipient.displayName',
        ];

        foreach ($paths as $path) {
            $name = trim((string) data_get($order, $path, ''));
            if ($name !== '') {
                return $name;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function recipientEmailFromOrder(array $order): string
    {
        $paths = [
            'fulfillments.0.pickup_details.recipient.email_address',
            'fulfillments.0.shipment_details.recipient.email_address',
            'fulfillments.0.delivery_details.recipient.email_address',
        ];

        foreach ($paths as $path) {
            $email = trim((string) data_get($order, $path, ''));
            if ($email !== '') {
                return $email;
            }
        }

        return '';
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

    private function unmatched(string $code, string $reason): void
    {
        $this->lastUnmatchedCode = $code;
        $this->lastUnmatchedReason = $reason;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logChannel(PaymentSourceChannel $channel, string $outcome, array $context): void
    {
        $prefix = $channel === PaymentSourceChannel::SquareOnlineStore
            ? 'ems.square.online_store.'
            : 'ems.square.pos.';

        $event = match ($outcome) {
            'ingested' => $prefix.'ingested',
            'unmatched' => $prefix.'unmatched',
            'capacity_failed' => $prefix.'capacity_failed',
            'duplicate' => $prefix.'duplicate',
            'payment_pending' => $prefix.'payment_pending',
            'invalid_mapping' => $prefix.'invalid_mapping',
            default => $prefix.$outcome,
        };

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info($event, $context);
    }
}
