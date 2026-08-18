<?php

namespace App\Ems\Services\Square;

use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\PaymentSourceChannel;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\RegistrationType;
use App\Ems\Exceptions\EmsException;
use App\Ems\Models\Event;
use App\Ems\Models\Order;
use App\Ems\Models\OrderItem;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\TicketType;
use App\Ems\Services\PaymentFulfillmentService;
use App\Ems\Services\Ticketing\TicketCodeGenerator;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * EMS-initiated Square Terminal checkout.
 *
 * Distinct from Square POS app + Reader sales, which are ingested from
 * Square orders after payment rather than created by EMS.
 */
class SquareTerminalService
{
    public function __construct(
        private readonly SquareClient $square,
        private readonly TicketCodeGenerator $codes,
        private readonly PaymentFulfillmentService $fulfillment,
        private readonly SquarePosIngestService $pos,
    ) {
    }

    /**
     * @param  array{
     *     ticket_type_id: string,
     *     attendee_name: string,
     *     attendee_email?: string|null,
     *     attendee_phone?: string|null,
     *     quantity?: int,
     *     device_id?: string|null
     * }  $data
     * @return array{order: Order, registration: Registration, payment: Payment, terminal_checkout_id: string}
     */
    public function createCheckout(Event $event, array $data, User $staff): array
    {
        if (! $this->square->enabled()) {
            throw new EmsException(
                'Square is not configured.',
                [],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        $deviceId = trim((string) ($data['device_id'] ?? config('ems.payments.square.terminal_device_id', '')));
        if ($deviceId === '') {
            throw new EmsException(
                'Square Terminal is not configured.',
                ['device_id' => ['Set SQUARE_TERMINAL_DEVICE_ID or pass a Terminal device_id.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $ticketType = TicketType::query()
            ->where('event_id', $event->id)
            ->where('uuid', $data['ticket_type_id'])
            ->firstOrFail();

        if ((float) $ticketType->price <= 0) {
            throw new EmsException(
                'Terminal checkout is only for paid tickets.',
                ['ticket_type_id' => ['Use walk-in for free tickets.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $name = trim($data['attendee_name']);
        $email = strtolower(trim((string) ($data['attendee_email'] ?? '')));
        $phone = isset($data['attendee_phone']) ? trim((string) $data['attendee_phone']) : null;
        $total = round(((float) $ticketType->price) * $quantity, 2);

        return DB::transaction(function () use ($event, $ticketType, $quantity, $name, $email, $phone, $total, $deviceId, $staff): array {
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $lockedType = TicketType::query()->whereKey($ticketType->id)->lockForUpdate()->firstOrFail();

            $order = new Order();
            $order->reference = $this->codes->orderReference();
            $order->event_id = $locked->id;
            $order->user_id = null;
            $order->buyer_name = $name;
            $order->buyer_email = $email;
            $order->buyer_phone = $phone;
            $order->total_amount = $total;
            $order->currency = $lockedType->currency;
            $order->status = OrderStatus::Pending;
            $order->source_channel = PaymentSourceChannel::Terminal->value;
            $order->metadata = ['source' => 'terminal', 'created_by_staff_id' => $staff->id];
            $order->save();

            $item = new OrderItem();
            $item->order_id = $order->id;
            $item->ticket_type_id = $lockedType->id;
            $item->name = $lockedType->name;
            $item->quantity = $quantity;
            $item->unit_price = $lockedType->price;
            $item->line_total = $total;
            $item->currency = $lockedType->currency;
            $item->save();

            $registration = new Registration();
            $registration->reference = $this->codes->registrationReference();
            $registration->event_id = $locked->id;
            $registration->user_id = null;
            $registration->ticket_type_id = $lockedType->id;
            $registration->order_id = $order->id;
            $registration->attendee_name = $name;
            $registration->attendee_email = $email;
            $registration->attendee_phone = $phone;
            $registration->status = RegistrationStatus::AwaitingPayment;
            $registration->type = RegistrationType::Paid;
            $registration->quantity = $quantity;
            $registration->amount_due = $total;
            $registration->currency = $lockedType->currency;
            $registration->registered_at = now();
            $registration->metadata = [
                'source' => 'terminal',
                'walk_in' => true,
                'walk_in_by' => $staff->id,
            ];
            $registration->save();

            $lockedType->quantity_sold = (int) $lockedType->quantity_sold + $quantity;
            $lockedType->save();

            $payment = new Payment();
            $payment->order_id = $order->id;
            $payment->registration_id = $registration->id;
            $payment->amount = $total;
            $payment->currency = $lockedType->currency;
            $payment->provider = 'square';
            $payment->status = PaymentStatus::Processing;
            $payment->source_channel = PaymentSourceChannel::Terminal->value;
            $payment->terminal_device_id = $deviceId;
            $payment->save();

            $idempotency = 'ems-term-' . $payment->uuid;
            $response = $this->square->post('/v2/terminals/checkouts', [
                'idempotency_key' => $idempotency,
                'checkout' => [
                    'amount_money' => [
                        'amount' => (int) round($total * 100),
                        'currency' => strtoupper($lockedType->currency),
                    ],
                    'device_options' => [
                        'device_id' => $deviceId,
                    ],
                    'reference_id' => $order->reference,
                    'note' => 'MSA EMS order ' . $order->reference,
                ],
            ], $idempotency);

            $checkout = is_array($response['checkout'] ?? null) ? $response['checkout'] : [];
            $checkoutId = (string) ($checkout['id'] ?? '');
            if ($checkoutId === '') {
                throw new EmsException(
                    'Square did not return a Terminal checkout id.',
                    [],
                    Response::HTTP_BAD_GATEWAY
                );
            }

            $payment->terminal_checkout_id = $checkoutId;
            $payment->provider_order_id = isset($checkout['order_id']) ? (string) $checkout['order_id'] : $payment->provider_order_id;
            $payment->metadata = array_merge($payment->metadata ?? [], [
                'terminal_status' => $checkout['status'] ?? 'PENDING',
            ]);
            $payment->save();

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.square.terminal.created', [
                    'ems_order_reference' => $order->reference,
                    'payment_uuid' => $payment->uuid,
                    'terminal_checkout_id' => $checkoutId,
                    'event_uuid' => $locked->uuid,
                    'ticket_type_uuid' => $lockedType->uuid,
                ]);

            return [
                'order' => $order->fresh(),
                'registration' => $registration->fresh(['ticketType']),
                'payment' => $payment->fresh(),
                'terminal_checkout_id' => $checkoutId,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $checkout
     */
    public function handleCheckoutUpdated(array $checkout): ?Payment
    {
        $checkoutId = (string) ($checkout['id'] ?? '');
        if ($checkoutId === '') {
            return null;
        }

        $payment = Payment::query()->where('terminal_checkout_id', $checkoutId)->first();
        $status = strtoupper((string) ($checkout['status'] ?? ''));

        if ($payment === null) {
            if ($status === 'COMPLETED') {
                $paymentIds = $checkout['payment_ids'] ?? [];
                $squarePaymentId = is_array($paymentIds) ? (string) ($paymentIds[0] ?? '') : '';
                if ($squarePaymentId !== '') {
                    try {
                        $retrieved = $this->square->get('/v2/payments/' . urlencode($squarePaymentId));
                        $object = is_array($retrieved['payment'] ?? null) ? $retrieved['payment'] : [];
                        $object['terminal_checkout_id'] = $checkoutId;

                        return $this->pos->ingestPayment($object);
                    } catch (\Throwable $e) {
                        Log::channel((string) config('ems.logging.channel', 'ems'))
                            ->warning('ems.square.terminal.unmatched', [
                                'terminal_checkout_id' => $checkoutId,
                                'error' => $e->getMessage(),
                            ]);
                    }
                }
            }

            return null;
        }

        $payment->metadata = array_merge($payment->metadata ?? [], [
            'terminal_status' => $status,
        ]);
        if (! empty($checkout['order_id'])) {
            $payment->provider_order_id = (string) $checkout['order_id'];
        }
        $payment->save();

        if ($status === 'COMPLETED') {
            $paymentIds = $checkout['payment_ids'] ?? [];
            $squarePaymentId = is_array($paymentIds) ? (string) ($paymentIds[0] ?? '') : '';
            $providerData = [
                'provider_payment_id' => $squarePaymentId ?: $payment->provider_payment_id,
                'provider_order_id' => $checkout['order_id'] ?? $payment->provider_order_id,
                'metadata' => ['terminal_status' => $status],
            ];

            return $this->fulfillment->markPaid($payment, $providerData);
        }

        if (in_array($status, ['CANCELED', 'CANCEL_REQUESTED'], true)) {
            return $this->fulfillment->markCancelled($payment, 'Terminal checkout canceled.');
        }

        return $payment;
    }
}
