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

        $prepared = DB::transaction(function () use ($event, $ticketType, $quantity, $name, $email, $phone, $total, $deviceId, $staff): array {
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

            return [
                'order' => $order,
                'registration' => $registration,
                'payment' => $payment,
                'ticket_type_id' => $lockedType->id,
                'currency' => $lockedType->currency,
                'event_uuid' => $locked->uuid,
                'ticket_type_uuid' => $lockedType->uuid,
                'quantity' => $quantity,
            ];
        });

        /** @var Order $order */
        $order = $prepared['order'];
        /** @var Registration $registration */
        $registration = $prepared['registration'];
        /** @var Payment $payment */
        $payment = $prepared['payment'];
        $ticketTypeId = $prepared['ticket_type_id'];
        $currency = $prepared['currency'];
        $eventUuid = $prepared['event_uuid'];
        $ticketTypeUuid = $prepared['ticket_type_uuid'];
        $quantity = $prepared['quantity'];

        $idempotency = 'ems-term-' . $payment->uuid;

        try {
            $response = $this->square->post('/v2/terminals/checkouts', [
                'idempotency_key' => $idempotency,
                'checkout' => [
                    'amount_money' => [
                        'amount' => (int) round($total * 100),
                        'currency' => strtoupper($currency),
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
                    'event_uuid' => $eventUuid,
                    'ticket_type_uuid' => $ticketTypeUuid,
                ]);

            return [
                'order' => $order->fresh(),
                'registration' => $registration->fresh(['ticketType']),
                'payment' => $payment->fresh(),
                'terminal_checkout_id' => $checkoutId,
            ];
        } catch (\Throwable $e) {
            $isDefinitive = $this->isDefinitiveProviderRejection($e);

            if ($isDefinitive) {
                DB::transaction(function () use ($ticketTypeId, $quantity, $payment, $order, $registration, $e): void {
                    /** @var Payment|null $lockedPayment */
                    $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();
                    /** @var Registration|null $lockedReg */
                    $lockedReg = Registration::query()->whereKey($registration->id)->lockForUpdate()->first();
                    /** @var Order|null $lockedOrder */
                    $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->first();

                    if (! $lockedPayment || ! $lockedReg || ! $lockedOrder) {
                        return;
                    }

                    if (! empty($lockedPayment->metadata['inventory_released'])) {
                        return;
                    }

                    if (in_array($lockedReg->status, [RegistrationStatus::AwaitingPayment, RegistrationStatus::Pending], true)) {
                        $lockedReg->status = RegistrationStatus::Cancelled;
                        $lockedReg->cancelled_at = now();
                        $lockedReg->save();

                        if ($lockedReg->ticket_type_id) {
                            $ticketType = TicketType::query()->whereKey($lockedReg->ticket_type_id)->lockForUpdate()->first();
                            if ($ticketType) {
                                $ticketType->quantity_sold = max(0, (int) $ticketType->quantity_sold - (int) $lockedReg->quantity);
                                $ticketType->save();
                            }
                        }
                    }

                    $lockedPayment->status = PaymentStatus::Cancelled;
                    $lockedPayment->failure_reason = 'Square Terminal checkout creation rejected: ' . $e->getMessage();
                    $meta = $lockedPayment->metadata ?? [];
                    $meta['inventory_released'] = true;
                    $meta['released_at'] = now()->toIso8601String();
                    $lockedPayment->metadata = $meta;
                    $lockedPayment->save();

                    $lockedOrder->status = OrderStatus::Cancelled;
                    $lockedOrder->cancelled_at = now();
                    $lockedOrder->save();
                });

                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->error('ems.square.terminal.creation_rejected', [
                        'ems_order_reference' => $order->reference,
                        'payment_uuid' => $payment->uuid,
                        'error' => $e->getMessage(),
                    ]);

                throw $e;
            }

            // Ambiguous terminal checkout outcome (timeout, connection drop, 5xx):
            // Do NOT cancel order or release inventory! Square Terminal may already have received the checkout.
            $metadata = $payment->metadata ?? [];
            $metadata['terminal_creation_ambiguous'] = true;
            $metadata['ambiguous_reason'] = $e->getMessage();
            $metadata['ambiguous_at'] = now()->toIso8601String();
            $payment->metadata = $metadata;
            $payment->failure_reason = 'Ambiguous Terminal checkout outcome: ' . substr($e->getMessage(), 0, 150);
            $payment->save();

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->warning('ems.square.terminal.creation_ambiguous', [
                    'ems_order_reference' => $order->reference,
                    'payment_uuid' => $payment->uuid,
                    'error' => $e->getMessage(),
                ]);

            throw new EmsException(
                'Square Terminal request timed out or returned an ambiguous outcome. The reservation is held; please check the physical terminal screen or retry.',
                ['terminal' => ['Terminal request timed out.']],
                Response::HTTP_GATEWAY_TIMEOUT
            );
        }
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
            // Check if checkout carries reference_id matching an EMS Order reference
            $referenceId = (string) ($checkout['reference_id'] ?? '');
            if ($referenceId !== '') {
                $order = Order::query()->where('reference', $referenceId)->first();
                if ($order !== null) {
                    $payment = $order->payments()
                        ->where('source_channel', PaymentSourceChannel::Terminal->value)
                        ->latest()
                        ->first()
                        ?? $order->payments()->latest()->first();

                    if ($payment !== null) {
                        $payment->terminal_checkout_id = $checkoutId;
                        $payment->save();

                        Log::channel((string) config('ems.logging.channel', 'ems'))
                            ->info('ems.square.terminal.correlated_by_reference', [
                                'terminal_checkout_id' => $checkoutId,
                                'order_reference' => $referenceId,
                                'payment_uuid' => $payment->uuid,
                            ]);
                    }
                }
            }
        }

        if ($payment === null && $status === 'COMPLETED') {
            $paymentIds = $checkout['payment_ids'] ?? [];
            $squarePaymentId = is_array($paymentIds) ? (string) ($paymentIds[0] ?? '') : '';
            if ($squarePaymentId !== '') {
                try {
                    $retrieved = $this->square->get('/v2/payments/' . urlencode($squarePaymentId));
                    $object = is_array($retrieved['payment'] ?? null) ? $retrieved['payment'] : [];

                    $paymentRef = (string) ($object['reference_id'] ?? '');
                    if ($paymentRef !== '') {
                        $order = Order::query()->where('reference', $paymentRef)->first();
                        if ($order !== null) {
                            $payment = $order->payments()->latest()->first();
                            if ($payment !== null) {
                                $payment->terminal_checkout_id = $checkoutId;
                                $payment->save();

                                Log::channel((string) config('ems.logging.channel', 'ems'))
                                    ->info('ems.square.terminal.correlated_by_payment_reference', [
                                        'terminal_checkout_id' => $checkoutId,
                                        'order_reference' => $paymentRef,
                                        'payment_uuid' => $payment->uuid,
                                    ]);
                            }
                        }
                    }

                    if ($payment === null) {
                        $object['terminal_checkout_id'] = $checkoutId;
                        return $this->pos->ingestPayment($object);
                    }
                } catch (\Throwable $e) {
                    Log::channel((string) config('ems.logging.channel', 'ems'))
                        ->warning('ems.square.terminal.unmatched', [
                            'terminal_checkout_id' => $checkoutId,
                            'error' => $e->getMessage(),
                        ]);
                }
            }
        }

        if ($payment === null) {
            return null;
        }

        // Verify amount and currency if provided in Square Terminal checkout
        if (isset($checkout['amount_money']) && is_array($checkout['amount_money'])) {
            $checkoutAmount = (int) ($checkout['amount_money']['amount'] ?? 0);
            $checkoutCurrency = strtoupper((string) ($checkout['amount_money']['currency'] ?? ''));
            $expectedAmount = (int) round(((float) $payment->amount) * 100);
            $expectedCurrency = strtoupper((string) $payment->currency);

            if ($checkoutAmount !== $expectedAmount || $checkoutCurrency !== $expectedCurrency) {
                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->warning('ems.square.terminal.mismatched_amount_or_currency', [
                        'terminal_checkout_id' => $checkoutId,
                        'payment_uuid' => $payment->uuid,
                        'expected_amount' => $expectedAmount,
                        'checkout_amount' => $checkoutAmount,
                        'expected_currency' => $expectedCurrency,
                        'checkout_currency' => $checkoutCurrency,
                    ]);

                return null;
            }
        }

        // Delayed / duplicate webhook protection: if already settled as Paid or Cancelled, never downgrade or alter
        if ($payment->status === PaymentStatus::Paid) {
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.square.terminal.already_paid_ignored', [
                    'terminal_checkout_id' => $checkoutId,
                    'payment_uuid' => $payment->uuid,
                ]);

            return $payment;
        }

        if (in_array($payment->status, [PaymentStatus::Cancelled, PaymentStatus::Failed], true)) {
            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->warning('ems.square.terminal.delayed_webhook_for_cancelled_payment', [
                    'terminal_checkout_id' => $checkoutId,
                    'payment_uuid' => $payment->uuid,
                ]);

            if ($status === 'COMPLETED') {
                $paymentIds = $checkout['payment_ids'] ?? [];
                $squarePaymentId = is_array($paymentIds) ? (string) ($paymentIds[0] ?? '') : null;
                $payment = $this->fulfillment->recordStaleCaptureAfterBuyerCancel(
                    $payment,
                    $squarePaymentId,
                    isset($checkout['order_id']) ? (string) $checkout['order_id'] : null,
                    source: 'square_terminal',
                );
            }

            return $payment;
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

            if ($squarePaymentId === '') {
                $meta = $payment->metadata ?? [];
                $meta['reconciliation_review_required'] = true;
                $meta['review_reason'] = 'Terminal checkout completed without payment ID.';
                $payment->metadata = $meta;
                $payment->save();

                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->warning('ems.square.terminal.completed_without_payment_id', [
                        'terminal_checkout_id' => $checkoutId,
                        'payment_uuid' => $payment->uuid,
                    ]);

                return null;
            }

            // If Square integration is disabled or unconfigured in this environment:
            // Production MUST NEVER mark an order paid without authoritative Square confirmation.
            // Local/test simulation is ONLY permitted if explicitly configured AND NOT in production.
            if (! $this->square->enabled()) {
                if (! app()->isProduction() && (bool) config('ems.payments.allow_test_simulation', false)) {
                    Log::channel((string) config('ems.logging.channel', 'ems'))
                        ->warning('ems.square.terminal.simulated_fulfillment_in_test_environment', [
                            'terminal_checkout_id' => $checkoutId,
                            'payment_uuid' => $payment->uuid,
                        ]);

                    $providerData = [
                        'provider_payment_id' => $squarePaymentId ?: $payment->provider_payment_id,
                        'provider_order_id' => $checkout['order_id'] ?? $payment->provider_order_id,
                        'metadata' => [
                            'terminal_status' => $status,
                            'square_payment_status' => 'COMPLETED',
                            'test_simulation' => true,
                        ],
                    ];

                    return $this->fulfillment->markPaid($payment, $providerData);
                }

                // In production, or unconfigured test/local environments: FAIL CLOSED.
                // Do NOT mark paid, do NOT issue tickets, do NOT complete registration.
                $meta = $payment->metadata ?? [];
                $meta['reconciliation_review_required'] = true;
                $meta['review_reason'] = app()->isProduction()
                    ? 'Square payment verification unavailable in production: Square is disabled or unconfigured.'
                    : 'Square payment verification unavailable: Square is disabled or unconfigured and test simulation is not permitted.';
                $payment->metadata = $meta;
                $payment->save();

                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->error('ems.square.terminal.verification_disabled_fail_closed', [
                        'terminal_checkout_id' => $checkoutId,
                        'payment_uuid' => $payment->uuid,
                        'is_production' => app()->isProduction(),
                        'square_enabled' => $this->square->enabled(),
                    ]);

                return null;
            }

            // Confirm the associated Square payment is actually successful and matches expectations
            try {
                $retrieved = $this->square->get('/v2/payments/' . urlencode($squarePaymentId));
                $squarePayment = is_array($retrieved['payment'] ?? null) ? $retrieved['payment'] : null;
            } catch (\Throwable $e) {
                $meta = $payment->metadata ?? [];
                $meta['reconciliation_review_required'] = true;
                $meta['review_reason'] = 'Square payment lookup failed: ' . $e->getMessage();
                $payment->metadata = $meta;
                $payment->save();

                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->warning('ems.square.terminal.payment_verification_lookup_failed', [
                        'terminal_checkout_id' => $checkoutId,
                        'square_payment_id' => $squarePaymentId,
                        'error' => $e->getMessage(),
                    ]);

                return null;
            }

            if (! is_array($squarePayment)) {
                $meta = $payment->metadata ?? [];
                $meta['reconciliation_review_required'] = true;
                $meta['review_reason'] = 'Square payment not found for terminal checkout.';
                $payment->metadata = $meta;
                $payment->save();

                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->warning('ems.square.terminal.payment_not_found', [
                        'terminal_checkout_id' => $checkoutId,
                        'square_payment_id' => $squarePaymentId,
                    ]);

                return null;
            }

            $sqStatus = strtoupper((string) ($squarePayment['status'] ?? ''));

            // CRITICAL: Only Square Payment status COMPLETED may trigger successful payment fulfillment.
            if ($sqStatus === 'COMPLETED') {
                // Verify expected amount
                $sqAmount = (int) ($squarePayment['amount_money']['amount'] ?? 0);
                $expectedAmount = (int) round(((float) $payment->amount) * 100);
                if ($sqAmount !== $expectedAmount) {
                    $meta = $payment->metadata ?? [];
                    $meta['reconciliation_review_required'] = true;
                    $meta['review_reason'] = "Payment amount mismatch: expected {$expectedAmount}, got {$sqAmount}";
                    $payment->metadata = $meta;
                    $payment->save();

                    Log::channel((string) config('ems.logging.channel', 'ems'))
                        ->warning('ems.square.terminal.payment_amount_mismatch', [
                            'terminal_checkout_id' => $checkoutId,
                            'square_payment_id' => $squarePaymentId,
                            'expected_amount' => $expectedAmount,
                            'square_amount' => $sqAmount,
                        ]);

                    return null;
                }

                // Verify expected currency
                $sqCurrency = strtoupper((string) ($squarePayment['amount_money']['currency'] ?? ''));
                $expectedCurrency = strtoupper((string) $payment->currency);
                if ($sqCurrency !== $expectedCurrency) {
                    $meta = $payment->metadata ?? [];
                    $meta['reconciliation_review_required'] = true;
                    $meta['review_reason'] = "Payment currency mismatch: expected {$expectedCurrency}, got {$sqCurrency}";
                    $payment->metadata = $meta;
                    $payment->save();

                    Log::channel((string) config('ems.logging.channel', 'ems'))
                        ->warning('ems.square.terminal.payment_currency_mismatch', [
                            'terminal_checkout_id' => $checkoutId,
                            'square_payment_id' => $squarePaymentId,
                            'expected_currency' => $expectedCurrency,
                            'square_currency' => $sqCurrency,
                        ]);

                    return null;
                }

                // Verify expected merchant / location
                $configuredLocation = (string) config('ems.payments.square.location_id', '');
                if ($configuredLocation !== '') {
                    $sqLocation = (string) ($squarePayment['location_id'] ?? '');
                    if ($sqLocation !== '' && $sqLocation !== $configuredLocation) {
                        $meta = $payment->metadata ?? [];
                        $meta['reconciliation_review_required'] = true;
                        $meta['review_reason'] = "Payment location mismatch: expected {$configuredLocation}, got {$sqLocation}";
                        $payment->metadata = $meta;
                        $payment->save();

                        Log::channel((string) config('ems.logging.channel', 'ems'))
                            ->warning('ems.square.terminal.payment_location_mismatch', [
                                'terminal_checkout_id' => $checkoutId,
                                'square_payment_id' => $squarePaymentId,
                                'expected_location' => $configuredLocation,
                                'square_location' => $sqLocation,
                            ]);

                        return null;
                    }
                }

                // Verify expected order reference if present
                $order = $payment->order;
                if ($order !== null) {
                    $sqRef = (string) ($squarePayment['reference_id'] ?? '');
                    if ($sqRef !== '' && $sqRef !== $order->reference) {
                        $meta = $payment->metadata ?? [];
                        $meta['reconciliation_review_required'] = true;
                        $meta['review_reason'] = "Payment reference mismatch: expected {$order->reference}, got {$sqRef}";
                        $payment->metadata = $meta;
                        $payment->save();

                        Log::channel((string) config('ems.logging.channel', 'ems'))
                            ->warning('ems.square.terminal.payment_reference_mismatch', [
                                'terminal_checkout_id' => $checkoutId,
                                'square_payment_id' => $squarePaymentId,
                                'expected_reference' => $order->reference,
                                'square_reference' => $sqRef,
                            ]);

                        return null;
                    }
                }

                // Verify association with terminal checkout if present in Square payment
                $sqCheckoutId = (string) ($squarePayment['terminal_checkout_id'] ?? '');
                if ($sqCheckoutId !== '' && $sqCheckoutId !== $checkoutId) {
                    $meta = $payment->metadata ?? [];
                    $meta['reconciliation_review_required'] = true;
                    $meta['review_reason'] = "Terminal checkout association mismatch: expected {$checkoutId}, got {$sqCheckoutId}";
                    $payment->metadata = $meta;
                    $payment->save();

                    Log::channel((string) config('ems.logging.channel', 'ems'))
                        ->warning('ems.square.terminal.checkout_association_mismatch', [
                            'terminal_checkout_id' => $checkoutId,
                            'square_payment_id' => $squarePaymentId,
                            'square_terminal_checkout_id' => $sqCheckoutId,
                        ]);

                    return null;
                }

                $providerData = [
                    'provider_payment_id' => $squarePaymentId,
                    'provider_order_id' => $squarePayment['order_id'] ?? $checkout['order_id'] ?? $payment->provider_order_id,
                    'metadata' => [
                        'terminal_status' => $status,
                        'square_payment_status' => 'COMPLETED',
                    ],
                ];

                return $this->fulfillment->markPaid($payment, $providerData);
            }

            // APPROVED: Keep EMS payment pending/authorized; do NOT fulfill
            if ($sqStatus === 'APPROVED') {
                $payment->status = PaymentStatus::Authorized;
                $payment->provider_payment_id = $squarePaymentId;
                $payment->metadata = array_merge($payment->metadata ?? [], [
                    'terminal_status' => $status,
                    'square_payment_status' => 'APPROVED',
                    'authorized_at' => now()->toIso8601String(),
                ]);
                $payment->save();

                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->info('ems.square.terminal.payment_approved_held_authorized', [
                        'terminal_checkout_id' => $checkoutId,
                        'square_payment_id' => $squarePaymentId,
                        'payment_uuid' => $payment->uuid,
                    ]);

                return $payment;
            }

            // PENDING: Keep payment pending
            if ($sqStatus === 'PENDING') {
                $payment->metadata = array_merge($payment->metadata ?? [], [
                    'terminal_status' => $status,
                    'square_payment_status' => 'PENDING',
                ]);
                $payment->save();

                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->info('ems.square.terminal.payment_pending_held', [
                        'terminal_checkout_id' => $checkoutId,
                        'square_payment_id' => $squarePaymentId,
                        'payment_uuid' => $payment->uuid,
                    ]);

                return $payment;
            }

            // FAILED: Apply safe cancellation and inventory-release logic
            if ($sqStatus === 'FAILED') {
                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->warning('ems.square.terminal.payment_failed', [
                        'terminal_checkout_id' => $checkoutId,
                        'square_payment_id' => $squarePaymentId,
                    ]);

                return $this->fulfillment->markCancelled($payment, 'Square payment status was FAILED.');
            }

            // CANCELED: Apply safe cancellation and inventory-release logic
            if (in_array($sqStatus, ['CANCELED', 'CANCELLED'], true)) {
                Log::channel((string) config('ems.logging.channel', 'ems'))
                    ->warning('ems.square.terminal.payment_canceled', [
                        'terminal_checkout_id' => $checkoutId,
                        'square_payment_id' => $squarePaymentId,
                    ]);

                return $this->fulfillment->markCancelled($payment, 'Square payment status was CANCELED.');
            }

            // Unknown status: Retain pending state and flag for review
            $meta = $payment->metadata ?? [];
            $meta['reconciliation_review_required'] = true;
            $meta['unknown_payment_status'] = $sqStatus;
            $meta['review_reason'] = "Unknown Square payment status: {$sqStatus}";
            $payment->metadata = $meta;
            $payment->save();

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->warning('ems.square.terminal.unknown_payment_status', [
                    'terminal_checkout_id' => $checkoutId,
                    'square_payment_id' => $squarePaymentId,
                    'square_payment_status' => $sqStatus,
                ]);

            return $payment;
        }

        if (in_array($status, ['CANCELED', 'CANCEL_REQUESTED'], true)) {
            return $this->fulfillment->markCancelled($payment, 'Terminal checkout canceled.');
        }

        if (in_array($status, ['PENDING', 'IN_PROGRESS'], true)) {
            return $payment;
        }

        // Unknown Terminal checkout status
        $meta = $payment->metadata ?? [];
        $meta['reconciliation_review_required'] = true;
        $meta['unknown_terminal_status'] = $status;
        $meta['review_reason'] = "Unknown Terminal checkout status: {$status}";
        $payment->metadata = $meta;
        $payment->save();

        return $payment;
    }

    private function isDefinitiveProviderRejection(\Throwable $e): bool
    {
        if (! ($e instanceof EmsException)) {
            return false;
        }

        $status = $e->status();
        $code = strtoupper((string) ($e->providerCode() ?? ''));

        if (in_array($status, [
            Response::HTTP_UNAUTHORIZED,
            Response::HTTP_FORBIDDEN,
            Response::HTTP_NOT_FOUND,
            Response::HTTP_REQUEST_TIMEOUT,
            Response::HTTP_CONFLICT,
            Response::HTTP_TOO_MANY_REQUESTS,
            Response::HTTP_INTERNAL_SERVER_ERROR,
            Response::HTTP_BAD_GATEWAY,
            Response::HTTP_SERVICE_UNAVAILABLE,
            Response::HTTP_GATEWAY_TIMEOUT,
        ], true) || $status >= 500) {
            return false;
        }

        $ambiguousCodes = [
            'IDEMPOTENCY_KEY_REUSED',
            'CONFLICT',
            'RATE_LIMITED',
            'GATEWAY_TIMEOUT',
            'REQUEST_TIMEOUT',
            'INTERNAL_SERVER_ERROR',
            'SERVICE_UNAVAILABLE',
            'TEMPORARY_ERROR',
        ];

        if ($code !== '' && in_array($code, $ambiguousCodes, true)) {
            return false;
        }

        if ($status === Response::HTTP_BAD_REQUEST || $status === Response::HTTP_UNPROCESSABLE_ENTITY) {
            if ($code !== '') {
                return ! in_array($code, $ambiguousCodes, true);
            }

            return true;
        }

        return false;
    }
}
