<?php

namespace App\Ems\Services;

use App\Ems\Contracts\TicketIssuer;
use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\PaymentProvider as PaymentProviderEnum;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\RegistrationType;
use App\Ems\Events\RegistrationCreated;
use App\Ems\Exceptions\CapacityExceededException;
use App\Ems\Exceptions\DuplicateRegistrationException;
use App\Ems\Exceptions\EmsException;
use App\Ems\Exceptions\RegistrationLimitExceededException;
use App\Ems\Exceptions\RegistrationNotOpenException;
use App\Ems\Exceptions\TicketUnavailableException;
use App\Ems\Jobs\QueueRegistrationConfirmation;
use App\Ems\Models\Event;
use App\Ems\Models\Order;
use App\Ems\Models\OrderItem;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\TicketType;
use App\Ems\Services\Payments\PaymentProviderManager;
use App\Ems\Services\Ticketing\TicketCodeGenerator;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Creates orders and drives free vs paid checkout for public registration.
 */
class CheckoutService
{
    public function __construct(
        private readonly TicketCodeGenerator $codes,
        private readonly TicketIssuer $tickets,
        private readonly PaymentProviderManager $providers,
    ) {
    }

    /**
     * Free registration with an optional ticket type (Phase 2 compatible).
     *
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     phone?: string|null,
     *     student_id?: string|null,
     *     notes?: string|null,
     *     quantity?: int,
     *     ticket_type_id?: string|null
     * }  $data
     */
    public function registerFree(Event $event, array $data, ?User $user = null): Registration
    {
        $this->assertEventAcceptsRegistration($event);

        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $email = strtolower(trim($data['email']));
        $firstName = trim($data['first_name']);
        $lastName = trim($data['last_name']);
        $attendeeName = trim($firstName . ' ' . $lastName);

        return DB::transaction(function () use ($event, $data, $user, $quantity, $email, $attendeeName, $firstName, $lastName): Registration {
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $ticketType = $this->resolveTicketType($locked, $data['ticket_type_id'] ?? null, requireFree: true);

            $this->assertPurchaseAllowed($locked, $ticketType, $email, $user, $quantity);

            if (! $locked->hasAvailableCapacity($quantity)) {
                if ($locked->waitlist_enabled) {
                    throw new EmsException(
                        'This event is sold out. You can join the waitlist.',
                        ['event' => ['Sold out — join waitlist.']],
                        Response::HTTP_CONFLICT
                    );
                }

                throw CapacityExceededException::make($locked->remainingCapacity());
            }

            if ($ticketType !== null) {
                $ticketType = TicketType::query()->whereKey($ticketType->id)->lockForUpdate()->firstOrFail();

                if (! $ticketType->hasAvailableQuantity($quantity)) {
                    throw TicketUnavailableException::insufficient((int) $ticketType->remainingQuantity());
                }
            }

            if ($this->hasActiveRegistration($locked, $email, $user)) {
                throw DuplicateRegistrationException::forEmail($email);
            }

            $unitPrice = $ticketType ? (float) $ticketType->price : 0.0;
            $currency = $ticketType?->currency ?? (string) config('ems.defaults.currency', 'CAD');

            $promoCode = null;
            $discountAmount = 0.0;
            if (!empty($data['promo_code'])) {
                $promoCode = \App\Ems\Models\PromoCode::where('code', strtoupper($data['promo_code']))
                    ->whereNull('archived_at')
                    ->first();
                if (!$promoCode) {
                    throw new EmsException(
                        'Invalid promo code.',
                        ['promo_code' => ['Invalid promo code.']],
                        Response::HTTP_UNPROCESSABLE_ENTITY
                    );
                }
                $originalTotal = $unitPrice * $quantity;
                $check = $promoCode->isValidFor($locked, $ticketType, $user, $originalTotal, $email);
                if (!$check['valid']) {
                    throw new EmsException(
                        $check['reason'],
                        ['promo_code' => [$check['reason']]],
                        Response::HTTP_UNPROCESSABLE_ENTITY
                    );
                }
                $discountAmount = $promoCode->calculateDiscount($originalTotal);
            }

            $order = $this->createOrder(
                $locked,
                $user,
                $attendeeName,
                $email,
                isset($data['phone']) ? trim((string) $data['phone']) : null,
                $unitPrice * $quantity,
                $currency,
                OrderStatus::Completed,
                $promoCode?->id,
                $discountAmount
            );
            $order->completed_at = now();
            $order->save();

            if ($ticketType !== null) {
                $this->createOrderItem($order, $ticketType, $quantity, $unitPrice, $currency);
            }

            $metadata = array_filter([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'student_id' => isset($data['student_id']) ? trim((string) $data['student_id']) : null,
            ], fn ($value) => $value !== null && $value !== '');

            $registration = new Registration();
            $registration->reference = $this->codes->registrationReference();
            $registration->event_id = $locked->id;
            $registration->user_id = $user?->id;
            $registration->ticket_type_id = $ticketType?->id;
            $registration->order_id = $order->id;
            $registration->attendee_name = $attendeeName;
            $registration->attendee_email = $email;
            $registration->attendee_phone = isset($data['phone']) ? trim((string) $data['phone']) : null;
            $registration->notes = isset($data['notes']) ? trim((string) $data['notes']) : null;
            $registration->status = RegistrationStatus::Confirmed;
            $registration->type = RegistrationType::Free;
            $registration->quantity = $quantity;
            $registration->amount_due = 0;
            $registration->currency = $currency;
            $registration->registered_at = now();
            $registration->confirmed_at = now();
            $registration->promo_code_id = $promoCode?->id;
            $registration->discount_amount = $discountAmount;
            $registration->metadata = $metadata === [] ? null : $metadata;
            $registration->save();

            if ($ticketType !== null) {
                $ticketType->quantity_sold += $quantity;
                $ticketType->save();
            }

            $this->tickets->issueFor($registration);

            $registration->load(['tickets.event.category', 'event.category', 'event.organizer', 'ticketType', 'order']);

            RegistrationCreated::dispatch($registration, $user);
            QueueRegistrationConfirmation::dispatch($registration->id);

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.checkout.free_completed', [
                    'order_uuid' => $order->uuid,
                    'registration_uuid' => $registration->uuid,
                ]);

            return $registration;
        });
    }

    /**
     * Paid (or mixed) checkout: create pending order/registration/payment and
     * return a Square hosted checkout URL when payment is required.
     *
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     phone?: string|null,
     *     student_id?: string|null,
     *     notes?: string|null,
     *     quantity?: int,
     *     ticket_type_id: string
     * }  $data
     * @return array{order: Order, registration: Registration, checkout_url: string|null, payment: Payment|null}
     */
    public function checkout(Event $event, array $data, ?User $user = null): array
    {
        $this->assertEventAcceptsRegistration($event);

        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $email = strtolower(trim($data['email']));
        $firstName = trim($data['first_name']);
        $lastName = trim($data['last_name']);
        $attendeeName = trim($firstName . ' ' . $lastName);

        return DB::transaction(function () use ($event, $data, $user, $quantity, $email, $attendeeName, $firstName, $lastName): array {
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $ticketType = $this->resolveTicketType($locked, $data['ticket_type_id'] ?? null, requireFree: false);

            if ($ticketType === null) {
                throw new EmsException(
                    'A ticket type is required.',
                    ['ticket_type_id' => ['Please select a ticket type.']],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $ticketType = TicketType::query()->whereKey($ticketType->id)->lockForUpdate()->firstOrFail();

            $this->assertPurchaseAllowed($locked, $ticketType, $email, $user, $quantity);

            if (! $locked->hasAvailableCapacity($quantity) || ! $ticketType->hasAvailableQuantity($quantity)) {
                if ($locked->waitlist_enabled) {
                    throw new EmsException(
                        'Sold out. You can join the waitlist.',
                        ['event' => ['Sold out — join waitlist.']],
                        Response::HTTP_CONFLICT
                    );
                }

                if (! $ticketType->hasAvailableQuantity($quantity)) {
                    throw TicketUnavailableException::insufficient((int) $ticketType->remainingQuantity());
                }

                throw CapacityExceededException::make($locked->remainingCapacity());
            }

            if ($this->hasActiveRegistration($locked, $email, $user)) {
                throw DuplicateRegistrationException::forEmail($email);
            }

            // Free ticket selected via checkout endpoint — complete immediately.
            if ($ticketType->isFree()) {
                $registration = $this->registerFree($locked, array_merge($data, [
                    'ticket_type_id' => $ticketType->uuid,
                ]), $user);

                return [
                    'order' => $registration->order,
                    'registration' => $registration,
                    'checkout_url' => null,
                    'payment' => null,
                ];
            }

            if (! $this->providers->enabled()) {
                throw new EmsException(
                    'Paid tickets are not available right now.',
                    [],
                    Response::HTTP_SERVICE_UNAVAILABLE
                );
            }

            $unitPrice = (float) $ticketType->price;
            $currency = $ticketType->currency;
            $originalTotal = $unitPrice * $quantity;

            $promoCode = null;
            $discountAmount = 0.0;
            if (!empty($data['promo_code'])) {
                $promoCode = \App\Ems\Models\PromoCode::where('code', strtoupper($data['promo_code']))
                    ->whereNull('archived_at')
                    ->first();
                if (!$promoCode) {
                    throw new EmsException(
                        'Invalid promo code.',
                        ['promo_code' => ['Invalid promo code.']],
                        Response::HTTP_UNPROCESSABLE_ENTITY
                    );
                }
                $check = $promoCode->isValidFor($locked, $ticketType, $user, $originalTotal, $email);
                if (!$check['valid']) {
                    throw new EmsException(
                        $check['reason'],
                        ['promo_code' => [$check['reason']]],
                        Response::HTTP_UNPROCESSABLE_ENTITY
                    );
                }
                $discountAmount = $promoCode->calculateDiscount($originalTotal);
            }

            $total = max(0.0, $originalTotal - $discountAmount);

            // If the promo code makes the checkout free, complete it immediately.
            if ($total === 0.0) {
                $order = $this->createOrder(
                    $locked,
                    $user,
                    $attendeeName,
                    $email,
                    isset($data['phone']) ? trim((string) $data['phone']) : null,
                    $originalTotal,
                    $currency,
                    OrderStatus::Completed,
                    $promoCode?->id,
                    $discountAmount
                );
                $order->completed_at = now();
                $order->save();

                $this->createOrderItem($order, $ticketType, $quantity, $unitPrice, $currency);

                $metadata = array_filter([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'student_id' => isset($data['student_id']) ? trim((string) $data['student_id']) : null,
                ], fn ($value) => $value !== null && $value !== '');

                $registration = new Registration();
                $registration->reference = $this->codes->registrationReference();
                $registration->event_id = $locked->id;
                $registration->user_id = $user?->id;
                $registration->ticket_type_id = $ticketType->id;
                $registration->order_id = $order->id;
                $registration->attendee_name = $attendeeName;
                $registration->attendee_email = $email;
                $registration->attendee_phone = isset($data['phone']) ? trim((string) $data['phone']) : null;
                $registration->notes = isset($data['notes']) ? trim((string) $data['notes']) : null;
                $registration->status = RegistrationStatus::Confirmed;
                $registration->type = RegistrationType::Paid;
                $registration->quantity = $quantity;
                $registration->amount_due = 0;
                $registration->currency = $currency;
                $registration->registered_at = now();
                $registration->confirmed_at = now();
                $registration->promo_code_id = $promoCode?->id;
                $registration->discount_amount = $discountAmount;
                $registration->metadata = $metadata === [] ? null : $metadata;
                $registration->save();

                $ticketType->quantity_sold += $quantity;
                $ticketType->save();

                $this->tickets->issueFor($registration);

                $registration->load(['tickets.event.category', 'event.category', 'event.organizer', 'ticketType', 'order']);

                RegistrationCreated::dispatch($registration, $user);
                QueueRegistrationConfirmation::dispatch($registration->id);

                return [
                    'order' => $order,
                    'registration' => $registration,
                    'checkout_url' => null,
                    'payment' => null,
                ];
            }

            if (! $this->providers->enabled()) {
                throw new EmsException(
                    'Paid tickets are not available right now.',
                    [],
                    Response::HTTP_SERVICE_UNAVAILABLE
                );
            }

            $order = $this->createOrder(
                $locked,
                $user,
                $attendeeName,
                $email,
                isset($data['phone']) ? trim((string) $data['phone']) : null,
                $originalTotal,
                $currency,
                OrderStatus::Pending,
                $promoCode?->id,
                $discountAmount
            );

            $this->createOrderItem($order, $ticketType, $quantity, $unitPrice, $currency);

            $metadata = array_filter([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'student_id' => isset($data['student_id']) ? trim((string) $data['student_id']) : null,
            ], fn ($value) => $value !== null && $value !== '');

            $registration = new Registration();
            $registration->reference = $this->codes->registrationReference();
            $registration->event_id = $locked->id;
            $registration->user_id = $user?->id;
            $registration->ticket_type_id = $ticketType->id;
            $registration->order_id = $order->id;
            $registration->attendee_name = $attendeeName;
            $registration->attendee_email = $email;
            $registration->attendee_phone = isset($data['phone']) ? trim((string) $data['phone']) : null;
            $registration->notes = isset($data['notes']) ? trim((string) $data['notes']) : null;
            $registration->status = RegistrationStatus::AwaitingPayment;
            $registration->type = RegistrationType::Paid;
            $registration->quantity = $quantity;
            $registration->amount_due = $total;
            $registration->currency = $currency;
            $registration->registered_at = now();
            $registration->promo_code_id = $promoCode?->id;
            $registration->discount_amount = $discountAmount;
            $registration->metadata = $metadata === [] ? null : $metadata;
            $registration->save();

            // Reserve inventory while payment is pending.
            $ticketType->quantity_sold += $quantity;
            $ticketType->save();

            $payment = new Payment();
            $payment->order_id = $order->id;
            $payment->registration_id = $registration->id;
            $payment->amount = $total;
            $payment->currency = $currency;
            $payment->provider = PaymentProviderEnum::Square;
            $payment->status = PaymentStatus::Pending;
            $payment->save();

            $order->load(['items', 'event']);
            $session = $this->providers->default()->createCheckout($order, $payment);

            $payment->provider_checkout_id = $session->checkoutId;
            $payment->provider_order_id = $session->providerOrderId;
            $payment->status = PaymentStatus::Processing;
            $payment->metadata = array_merge($payment->metadata ?? [], $session->metadata);
            $payment->save();

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.checkout.created', [
                    'order_uuid' => $order->uuid,
                    'payment_uuid' => $payment->uuid,
                    'provider_checkout_id' => $session->checkoutId,
                ]);

            $registration->load(['event.category', 'ticketType', 'order']);

            return [
                'order' => $order->fresh(['items', 'event']),
                'registration' => $registration,
                'checkout_url' => $session->checkoutUrl,
                'payment' => $payment->fresh(),
            ];
        });
    }

    private function assertEventAcceptsRegistration(Event $event): void
    {
        if (! $event->isPubliclyDiscoverable()) {
            throw new EmsException(
                'This event is not available for registration.',
                [],
                Response::HTTP_NOT_FOUND
            );
        }

        if ($event->registration_deadline_at !== null
            && now()->greaterThan($event->registration_deadline_at)
        ) {
            throw RegistrationLimitExceededException::deadlinePassed();
        }

        if (! $event->isAcceptingRegistrations()) {
            throw RegistrationNotOpenException::forEvent($event->status->label());
        }
    }

    private function resolveTicketType(Event $event, mixed $ticketTypeId, bool $requireFree): ?TicketType
    {
        if ($ticketTypeId === null || $ticketTypeId === '') {
            return null;
        }

        $ticketType = $event->ticketTypes()
            ->where(function ($q) use ($ticketTypeId): void {
                $q->where('uuid', $ticketTypeId);

                if (is_numeric($ticketTypeId)) {
                    $q->orWhere('id', (int) $ticketTypeId);
                }
            })
            ->first();

        if ($ticketType === null) {
            throw new EmsException(
                'The selected ticket type was not found.',
                ['ticket_type_id' => ['Invalid ticket type.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (! $ticketType->isOnSale()) {
            throw TicketUnavailableException::notOnSale();
        }

        if ($requireFree && ! $ticketType->isFree()) {
            throw new EmsException(
                'This ticket requires payment. Use checkout instead.',
                ['ticket_type_id' => ['Paid ticket — use checkout.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return $ticketType;
    }

    private function assertPurchaseAllowed(
        Event $event,
        ?TicketType $ticketType,
        string $email,
        ?User $user,
        int $quantity
    ): void {
        $maxPerOrder = $ticketType?->max_per_order ?? $event->max_tickets_per_order;

        if ($maxPerOrder !== null && $quantity > $maxPerOrder) {
            throw RegistrationLimitExceededException::perOrder((int) $maxPerOrder);
        }

        $maxPerAttendee = $event->max_registrations_per_attendee;

        if ($maxPerAttendee !== null) {
            $existing = (int) $event->registrations()
                ->occupyingCapacity()
                ->where(function ($query) use ($email, $user): void {
                    $query->where('attendee_email', $email);

                    if ($user !== null) {
                        $query->orWhere('user_id', $user->id);
                    }
                })
                ->sum('quantity');

            if (($existing + $quantity) > $maxPerAttendee) {
                throw RegistrationLimitExceededException::perAttendee((int) $maxPerAttendee);
            }
        }
    }

    private function createOrder(
        Event $event,
        ?User $user,
        string $buyerName,
        string $buyerEmail,
        ?string $buyerPhone,
        float $total,
        string $currency,
        OrderStatus $status,
        ?int $promoCodeId = null,
        float $discountAmount = 0.0
    ): Order {
        $order = new Order();
        $order->reference = $this->codes->orderReference();
        $order->event_id = $event->id;
        $order->user_id = $user?->id;
        $order->buyer_name = $buyerName;
        $order->buyer_email = $buyerEmail;
        $order->buyer_phone = $buyerPhone;
        $order->total_amount = max(0.0, $total - $discountAmount);
        $order->currency = $currency;
        $order->status = $status;
        $order->promo_code_id = $promoCodeId;
        $order->discount_amount = $discountAmount;
        $order->save();

        return $order;
    }

    private function createOrderItem(
        Order $order,
        TicketType $ticketType,
        int $quantity,
        float $unitPrice,
        string $currency
    ): OrderItem {
        $item = new OrderItem();
        $item->order_id = $order->id;
        $item->ticket_type_id = $ticketType->id;
        $item->name = $ticketType->name;
        $item->quantity = $quantity;
        $item->unit_price = $unitPrice;
        $item->line_total = $unitPrice * $quantity;
        $item->currency = $currency;
        $item->save();

        return $item;
    }

    private function hasActiveRegistration(Event $event, string $email, ?User $user): bool
    {
        return $event->registrations()
            ->occupyingCapacity()
            ->where(function ($query) use ($email, $user): void {
                $query->where('attendee_email', $email);

                if ($user !== null) {
                    $query->orWhere('user_id', $user->id);
                }
            })
            ->exists();
    }
}
