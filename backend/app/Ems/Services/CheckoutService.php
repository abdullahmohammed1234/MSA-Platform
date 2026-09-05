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
use App\Ems\Models\SquareCatalogMapping;
use App\Ems\Models\TicketType;
use App\Ems\Services\Payments\CheckoutFingerprint;
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

            $metadata = $this->buildMetadata($data, $firstName, $lastName);

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
            app(\App\Ems\Services\Notifications\EventCommunicationService::class)->sendRegistrationBundle($registration);
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

            $pendingByOrder = $this->findPendingCheckoutByOrderUuid($locked, $data['order_uuid'] ?? null);
            if ($pendingByOrder !== null) {
                return $this->synchronizePendingCheckout(
                    $locked,
                    $pendingByOrder,
                    $data,
                    $user,
                    $ticketType,
                    $quantity,
                    $email,
                    $attendeeName,
                    $firstName,
                    $lastName
                );
            }

            if ($this->hasActiveRegistration($locked, $email, $user)) {
                $resumable = $this->findResumableCheckout($locked, $email, $user);
                if ($resumable !== null) {
                    return $this->synchronizePendingCheckout(
                        $locked,
                        $resumable,
                        $data,
                        $user,
                        $ticketType,
                        $quantity,
                        $email,
                        $attendeeName,
                        $firstName,
                        $lastName
                    );
                }

                throw DuplicateRegistrationException::forEmail($email !== '' ? $email : 'this attendee');
            }

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

            $quoted = $this->quotePaidCheckout($locked, $ticketType, $quantity, $data, $user, $email);
            $unitPrice = $quoted['unit_price'];
            $currency = $quoted['currency'];
            $originalTotal = $quoted['subtotal'];
            $promoCode = $quoted['promo'];
            $discountAmount = $quoted['discount_amount'];
            $total = $quoted['total'];

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

                $metadata = $this->buildMetadata($data, $firstName, $lastName);

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
                app(\App\Ems\Services\Notifications\EventCommunicationService::class)->sendRegistrationBundle($registration);
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

            $metadata = $this->buildMetadata($data, $firstName, $lastName);

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
            $payment->checkout_version = 1;
            $payment->checkout_details_hash = $this->fingerprintFor(
                $locked,
                $ticketType,
                $quantity,
                $quoted,
                $email
            );
            $payment->save();

            $this->attachSquareCheckout($order, $payment);

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.checkout.created', [
                    'order_uuid' => $order->uuid,
                    'payment_uuid' => $payment->uuid,
                    'provider_checkout_id' => $payment->provider_checkout_id,
                    'checkout_version' => $payment->checkout_version,
                    'fingerprint_prefix' => CheckoutFingerprint::prefix($payment->checkout_details_hash),
                ]);

            $registration->load(['event.category', 'ticketType', 'order']);

            return [
                'order' => $order->fresh(['items', 'event']),
                'registration' => $registration,
                'checkout_url' => $payment->checkout_url,
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
        int $quantity,
        ?int $excludeRegistrationId = null
    ): void {
        $maxPerOrder = $ticketType?->max_per_order ?? $event->max_tickets_per_order;

        if ($maxPerOrder !== null && $quantity > $maxPerOrder) {
            throw RegistrationLimitExceededException::perOrder((int) $maxPerOrder);
        }

        $maxPerAttendee = $event->max_registrations_per_attendee;

        if ($maxPerAttendee !== null && ($email !== '' || $user !== null)) {
            $existing = (int) $event->registrations()
                ->occupyingCapacity()
                ->when($excludeRegistrationId !== null, fn ($query) => $query->where('id', '!=', $excludeRegistrationId))
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
        $order->source_channel = \App\Ems\Enums\PaymentSourceChannel::Online->value;
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

    /**
     * Reuse or replace a pending Square Payment Link so "Complete Payment Later"
     * always reflects the current validated EMS order.
     *
     * @param  array<string, mixed>  $data
     * @param  array{order: Order, registration: Registration, checkout_url: string|null, payment: Payment|null}  $resumable
     * @return array{order: Order, registration: Registration, checkout_url: string|null, payment: Payment|null}
     */
    private function synchronizePendingCheckout(
        Event $event,
        array $resumable,
        array $data,
        ?User $user,
        TicketType $ticketType,
        int $quantity,
        string $email,
        string $attendeeName,
        string $firstName,
        string $lastName
    ): array {
        $payment = $resumable['payment'];
        $registration = $resumable['registration'];

        if ($payment === null || $registration === null) {
            throw DuplicateRegistrationException::forEmail($email !== '' ? $email : 'this attendee');
        }

        $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

        if (in_array($payment->status, [
            PaymentStatus::Paid,
            PaymentStatus::Refunded,
            PaymentStatus::PartiallyRefunded,
        ], true)) {
            throw new EmsException(
                'This order has already been paid. A new checkout cannot be created.',
                ['checkout' => ['Payment is already complete.']],
                Response::HTTP_CONFLICT
            );
        }

        $quoted = $this->quotePaidCheckout(
            $event,
            $ticketType,
            $quantity,
            $data,
            $user,
            $email,
            $registration
        );
        $requestedHash = $this->fingerprintFor($event, $ticketType, $quantity, $quoted, $email);
        $storedHash = $payment->checkout_details_hash;

        if ($storedHash === null || $storedHash === '') {
            $storedHash = $this->fingerprintFromPending($event, $registration, $payment);
            $payment->checkout_details_hash = $storedHash;
            $payment->checkout_version = max(1, (int) $payment->checkout_version);
            $payment->save();
        }

        $linkLive = $payment->checkout_url
            && ($payment->checkout_expires_at === null || $payment->checkout_expires_at->isFuture());

        if (hash_equals($storedHash, $requestedHash) && $linkLive) {
            $this->updateNonMonetaryAttendeeFields($registration, $payment->order, $data, $attendeeName, $email);

            return [
                'order' => $registration->order?->fresh(['items', 'event']) ?? $payment->order,
                'registration' => $registration->fresh(['event.category', 'ticketType', 'order']),
                'checkout_url' => $payment->checkout_url,
                'payment' => $payment->fresh(),
            ];
        }

        if ($quoted['total'] <= 0) {
            throw new EmsException(
                'This change would make the order free. Cancel the pending checkout and register again.',
                ['checkout' => ['Pending paid checkout cannot become free in place.']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return $this->replacePendingCheckout(
            $event,
            $payment,
            $registration,
            $ticketType,
            $quantity,
            $quoted,
            $data,
            $email,
            $attendeeName,
            $firstName,
            $lastName,
            $requestedHash
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{
     *     unit_price: float,
     *     currency: string,
     *     subtotal: float,
     *     discount_amount: float,
     *     total: float,
     *     promo: \App\Ems\Models\PromoCode|null
     * }  $quoted
     * @return array{order: Order, registration: Registration, checkout_url: string|null, payment: Payment|null}
     */
    private function replacePendingCheckout(
        Event $event,
        Payment $payment,
        Registration $registration,
        TicketType $ticketType,
        int $quantity,
        array $quoted,
        array $data,
        string $email,
        string $attendeeName,
        string $firstName,
        string $lastName,
        string $requestedHash
    ): array {
        $this->assertPurchaseAllowed($event, $ticketType, $email, $registration->user, $quantity, $registration->id);

        if (! $this->hasCapacityFor($event, $ticketType, $quantity, $registration)) {
            if ($event->waitlist_enabled) {
                throw new EmsException(
                    'Sold out. You can join the waitlist.',
                    ['event' => ['Sold out — join waitlist.']],
                    Response::HTTP_CONFLICT
                );
            }

            throw TicketUnavailableException::insufficient(
                (int) ($ticketType->remainingQuantity() ?? 0)
            );
        }

        $order = Order::query()->whereKey($registration->order_id)->lockForUpdate()->firstOrFail();
        $oldTicketType = $registration->ticket_type_id
            ? TicketType::query()->whereKey($registration->ticket_type_id)->lockForUpdate()->first()
            : null;
        $oldQuantity = (int) $registration->quantity;
        $oldCheckoutId = $payment->provider_checkout_id;
        $oldOrderId = $payment->provider_order_id;
        $oldHash = $payment->checkout_details_hash;
        $oldVersion = max(1, (int) $payment->checkout_version);

        $this->transferInventory($oldTicketType, $oldQuantity, $ticketType, $quantity);

        $order->buyer_name = $attendeeName;
        $order->buyer_email = $email;
        $order->buyer_phone = isset($data['phone']) ? trim((string) $data['phone']) : $order->buyer_phone;
        $order->total_amount = $quoted['total'];
        $order->currency = $quoted['currency'];
        $order->promo_code_id = $quoted['promo']?->id;
        $order->discount_amount = $quoted['discount_amount'];
        $order->save();

        OrderItem::query()->where('order_id', $order->id)->delete();
        $this->createOrderItem($order, $ticketType, $quantity, $quoted['unit_price'], $quoted['currency']);

        $metadata = $this->buildMetadata($data, $firstName, $lastName);

        $registration->ticket_type_id = $ticketType->id;
        $registration->attendee_name = $attendeeName;
        $registration->attendee_email = $email;
        $registration->attendee_phone = isset($data['phone']) ? trim((string) $data['phone']) : $registration->attendee_phone;
        $registration->notes = array_key_exists('notes', $data)
            ? (isset($data['notes']) ? trim((string) $data['notes']) : null)
            : $registration->notes;
        $registration->quantity = $quantity;
        $registration->amount_due = $quoted['total'];
        $registration->currency = $quoted['currency'];
        $registration->promo_code_id = $quoted['promo']?->id;
        $registration->discount_amount = $quoted['discount_amount'];
        $registration->metadata = $metadata === [] ? $registration->metadata : $metadata;
        $registration->save();

        $payment->amount = $quoted['total'];
        $payment->currency = $quoted['currency'];
        $payment->checkout_version = $oldVersion + 1;
        $payment->checkout_details_hash = $requestedHash;

        $superseded = $payment->metadata['superseded_checkouts'] ?? [];
        $superseded[] = [
            'provider_checkout_id' => $oldCheckoutId,
            'provider_order_id' => $oldOrderId,
            'checkout_version' => $oldVersion,
            'fingerprint_prefix' => CheckoutFingerprint::prefix($oldHash),
            'superseded_at' => now()->toIso8601String(),
        ];
        $payment->metadata = array_merge($payment->metadata ?? [], [
            'superseded_checkouts' => $superseded,
        ]);
        $payment->save();

        $order->load(['items', 'event', 'promoCode']);
        $this->attachSquareCheckout($order, $payment);

        $this->providers->default()->deletePaymentLink($oldCheckoutId);

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.square.checkout.replaced', [
                'payment_uuid' => $payment->uuid,
                'order_uuid' => $order->uuid,
                'checkout_version' => $payment->checkout_version,
                'old_checkout' => $oldCheckoutId,
                'new_checkout' => $payment->provider_checkout_id,
                'old_fingerprint' => CheckoutFingerprint::prefix($oldHash),
                'new_fingerprint' => CheckoutFingerprint::prefix($requestedHash),
                'reason' => 'order_details_changed',
            ]);

        $registration->load(['event.category', 'ticketType', 'order']);

        return [
            'order' => $order->fresh(['items', 'event']),
            'registration' => $registration,
            'checkout_url' => $payment->checkout_url,
            'payment' => $payment->fresh(),
        ];
    }

    private function attachSquareCheckout(Order $order, Payment $payment): void
    {
        $order->loadMissing(['items', 'event', 'promoCode']);
        $session = $this->providers->default()->createCheckout($order, $payment);
        $ttlMinutes = (int) config('ems.payments.checkout_ttl_minutes', 1440);

        $payment->provider_checkout_id = $session->checkoutId;
        $payment->provider_order_id = $session->providerOrderId;
        if ($session->providerOrderId) {
            $order->provider_order_id = $session->providerOrderId;
            $order->save();
        }
        $payment->checkout_url = $session->checkoutUrl;
        $payment->checkout_expires_at = now()->addMinutes(max(15, $ttlMinutes));
        $payment->source_channel = \App\Ems\Enums\PaymentSourceChannel::Online->value;
        $payment->status = PaymentStatus::Processing;
        $payment->metadata = array_merge($payment->metadata ?? [], $session->metadata);
        $payment->save();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     unit_price: float,
     *     currency: string,
     *     subtotal: float,
     *     discount_amount: float,
     *     total: float,
     *     promo: \App\Ems\Models\PromoCode|null
     * }
     */
    private function quotePaidCheckout(
        Event $event,
        TicketType $ticketType,
        int $quantity,
        array $data,
        ?User $user,
        string $email,
        ?Registration $existing = null
    ): array {
        $unitPrice = (float) $ticketType->price;
        $currency = $ticketType->currency ?: (string) config('ems.defaults.currency', 'CAD');
        $subtotal = $unitPrice * $quantity;
        $promoCode = null;
        $discountAmount = 0.0;

        if (! empty($data['promo_code'])) {
            $promoCode = \App\Ems\Models\PromoCode::query()
                ->where('code', strtoupper((string) $data['promo_code']))
                ->whereNull('archived_at')
                ->first();
            if ($promoCode === null) {
                throw new EmsException(
                    'Invalid promo code.',
                    ['promo_code' => ['Invalid promo code.']],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $alreadyApplied = $existing !== null && (int) $existing->promo_code_id === (int) $promoCode->id;
            if (! $alreadyApplied) {
                $check = $promoCode->isValidFor($event, $ticketType, $user, $subtotal, $email);
                if (! $check['valid']) {
                    throw new EmsException(
                        $check['reason'],
                        ['promo_code' => [$check['reason']]],
                        Response::HTTP_UNPROCESSABLE_ENTITY
                    );
                }
            }

            $discountAmount = $promoCode->calculateDiscount($subtotal);
        }

        return [
            'unit_price' => $unitPrice,
            'currency' => $currency,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'total' => max(0.0, $subtotal - $discountAmount),
            'promo' => $promoCode,
        ];
    }

    /**
     * @param  array{unit_price: float, currency: string, subtotal: float, discount_amount: float, total: float, promo: \App\Ems\Models\PromoCode|null}  $quoted
     */
    private function fingerprintFor(
        Event $event,
        TicketType $ticketType,
        int $quantity,
        array $quoted,
        string $email
    ): string {
        $variationId = SquareCatalogMapping::query()
            ->where('ticket_type_id', $ticketType->id)
            ->value('square_catalog_variation_id');

        return CheckoutFingerprint::hash([
            'event_uuid' => $event->uuid,
            'ticket_type_uuid' => $ticketType->uuid,
            'quantity' => $quantity,
            'unit_price' => $quoted['unit_price'],
            'subtotal' => $quoted['subtotal'],
            'discount_amount' => $quoted['discount_amount'],
            'fees' => 0,
            'taxes' => 0,
            'total' => $quoted['total'],
            'currency' => $quoted['currency'],
            'email' => $email,
            'promo_code' => $quoted['promo']?->code,
            'catalog_variation_id' => is_string($variationId) ? $variationId : '',
        ]);
    }

    private function fingerprintFromPending(Event $event, Registration $registration, Payment $payment): string
    {
        $ticketType = $registration->ticketType
            ?? TicketType::query()->find($registration->ticket_type_id);
        if ($ticketType === null) {
            return (string) $payment->checkout_details_hash;
        }

        $promo = $registration->promo_code_id
            ? \App\Ems\Models\PromoCode::query()->find($registration->promo_code_id)
            : null;

        return $this->fingerprintFor($event, $ticketType, (int) $registration->quantity, [
            'unit_price' => (float) $ticketType->price,
            'currency' => $payment->currency ?: $ticketType->currency,
            'subtotal' => (float) $ticketType->price * (int) $registration->quantity,
            'discount_amount' => (float) $registration->discount_amount,
            'total' => (float) $payment->amount,
            'promo' => $promo,
        ], (string) $registration->attendee_email);
    }

    private function hasCapacityFor(Event $event, TicketType $ticketType, int $quantity, ?Registration $existing): bool
    {
        $eventCredit = $existing ? (int) $existing->quantity : 0;
        $ticketCredit = ($existing && (int) $existing->ticket_type_id === (int) $ticketType->id)
            ? (int) $existing->quantity
            : 0;

        if ($event->capacity !== null) {
            $remaining = (int) $event->remainingCapacity() + $eventCredit;
            if ($remaining < $quantity) {
                return false;
            }
        }

        $ticketRemaining = $ticketType->remainingQuantity();
        if ($ticketRemaining !== null && ($ticketRemaining + $ticketCredit) < $quantity) {
            return false;
        }

        return true;
    }

    private function transferInventory(
        ?TicketType $oldTicketType,
        int $oldQuantity,
        TicketType $newTicketType,
        int $newQuantity
    ): void {
        $newTicketType = TicketType::query()->whereKey($newTicketType->id)->lockForUpdate()->firstOrFail();

        if ($oldTicketType !== null && (int) $oldTicketType->id === (int) $newTicketType->id) {
            $delta = $newQuantity - $oldQuantity;
            if ($delta !== 0) {
                $newTicketType->quantity_sold = max(0, (int) $newTicketType->quantity_sold + $delta);
                $newTicketType->save();
            }

            return;
        }

        $newTicketType->quantity_sold = (int) $newTicketType->quantity_sold + $newQuantity;
        $newTicketType->save();

        if ($oldTicketType !== null && $oldQuantity > 0) {
            $oldLocked = TicketType::query()->whereKey($oldTicketType->id)->lockForUpdate()->first();
            if ($oldLocked !== null) {
                $oldLocked->quantity_sold = max(0, (int) $oldLocked->quantity_sold - $oldQuantity);
                $oldLocked->save();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateNonMonetaryAttendeeFields(
        Registration $registration,
        ?Order $order,
        array $data,
        string $attendeeName,
        string $email
    ): void {
        $registration->attendee_name = $attendeeName !== '' ? $attendeeName : $registration->attendee_name;
        if (array_key_exists('phone', $data)) {
            $registration->attendee_phone = isset($data['phone']) ? trim((string) $data['phone']) : null;
        }
        if (array_key_exists('notes', $data)) {
            $registration->notes = isset($data['notes']) ? trim((string) $data['notes']) : null;
        }
        $registration->save();

        if ($order !== null) {
            $order->buyer_name = $registration->attendee_name;
            $order->buyer_email = $email !== '' ? $email : $order->buyer_email;
            if (array_key_exists('phone', $data)) {
                $order->buyer_phone = $registration->attendee_phone;
            }
            $order->save();
        }
    }

    /**
     * @return array{order: Order, registration: Registration, checkout_url: string|null, payment: Payment|null}|null
     */
    public function findPendingCheckoutByOrderUuid(Event $event, mixed $orderUuid): ?array
    {
        if (! is_string($orderUuid) || $orderUuid === '') {
            return null;
        }

        $order = Order::query()
            ->where('uuid', $orderUuid)
            ->where('event_id', $event->id)
            ->first();
        if ($order === null) {
            return null;
        }

        $payment = Payment::query()
            ->where('order_id', $order->id)
            ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Processing->value])
            ->latest('id')
            ->first();
        if ($payment === null) {
            return null;
        }

        $registration = Registration::query()
            ->where('order_id', $order->id)
            ->where('status', RegistrationStatus::AwaitingPayment->value)
            ->with(['order.items', 'order.event', 'ticketType'])
            ->first();
        if ($registration === null) {
            return null;
        }

        return [
            'order' => $order,
            'registration' => $registration,
            'checkout_url' => $payment->checkout_url,
            'payment' => $payment,
        ];
    }

    /**
     * @return array{order: Order, registration: Registration, checkout_url: string|null, payment: Payment|null}|null
     */
    public function findResumableCheckout(Event $event, string $email, ?User $user = null): ?array
    {
        $query = Registration::query()
            ->where('event_id', $event->id)
            ->where('status', RegistrationStatus::AwaitingPayment->value)
            ->with(['order.items', 'order.event', 'ticketType']);

        $query->where(function ($inner) use ($email, $user): void {
            if ($email !== '') {
                $inner->where('attendee_email', $email);
            }
            if ($user !== null) {
                $inner->orWhere('user_id', $user->id);
            }
        });

        /** @var Registration|null $registration */
        $registration = $query->latest('id')->first();
        if ($registration === null) {
            return null;
        }

        $payment = Payment::query()
            ->where('registration_id', $registration->id)
            ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Processing->value])
            ->latest('id')
            ->first();

        if ($payment === null) {
            return null;
        }

        if ($payment->checkout_expires_at && $payment->checkout_expires_at->isPast()) {
            return null;
        }

        if (! $payment->checkout_url && $payment->provider_checkout_id) {
            $link = $this->providers->default()->retrievePaymentLink((string) $payment->provider_checkout_id);
            $url = (string) data_get($link, 'payment_link.url', '');
            if ($url !== '') {
                $payment->checkout_url = $url;
                $payment->save();
            }
        }

        if (! $payment->checkout_url) {
            return null;
        }

        $registration->load(['event.category', 'ticketType', 'order']);

        return [
            'order' => $registration->order ?? $payment->order,
            'registration' => $registration,
            'checkout_url' => $payment->checkout_url,
            'payment' => $payment,
        ];
    }

    private function hasActiveRegistration(Event $event, string $email, ?User $user): bool
    {
        if ($email === '' && $user === null) {
            return false;
        }

        return $event->registrations()
            ->occupyingCapacity()
            ->where(function ($query) use ($email, $user): void {
                if ($email !== '') {
                    $query->where('attendee_email', $email);
                }

                if ($user !== null) {
                    $email !== ''
                        ? $query->orWhere('user_id', $user->id)
                        : $query->where('user_id', $user->id);
                }
            })
            ->exists();
    }

    private function buildMetadata(array $data, string $firstName, string $lastName): array
    {
        $metadata = array_filter([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'student_id' => isset($data['student_id']) ? trim((string) $data['student_id']) : null,
        ], fn ($value) => $value !== null && $value !== '');

        if (! empty($data['attendees']) && is_array($data['attendees'])) {
            $formatted = [];
            foreach ($data['attendees'] as $att) {
                if (is_array($att) && ! empty($att['email'])) {
                    $fn = trim((string) ($att['first_name'] ?? ''));
                    $ln = trim((string) ($att['last_name'] ?? ''));
                    $formatted[] = [
                        'first_name' => $fn,
                        'last_name' => $ln,
                        'name' => trim($fn . ' ' . $ln),
                        'email' => strtolower(trim((string) $att['email'])),
                        'phone' => ! empty($att['phone']) ? trim((string) $att['phone']) : null,
                    ];
                }
            }
            if ($formatted !== []) {
                $metadata['attendees'] = $formatted;
            }
        }

        return $metadata;
    }
}
