<?php

namespace App\Ems\Services;

use App\Ems\Contracts\TicketIssuer;
use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Events\RegistrationCreated;
use App\Ems\Exceptions\EmsException;
use App\Ems\Exceptions\InvalidPaymentTransitionException;
use App\Ems\Jobs\QueueRegistrationConfirmation;
use App\Ems\Jobs\ReconcilePaymentJob;
use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\TicketType;
use App\Ems\Services\Notifications\EventCommunicationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Completes paid orders after verified payment confirmation.
 *
 * Never issues tickets before payment is marked Paid.
 */
class PaymentFulfillmentService
{
    public function __construct(
        private readonly TicketIssuer $tickets,
        private readonly WaitlistService $waitlists,
        private readonly EventCommunicationService $communications,
    ) {
    }

    /**
     * Mark payment paid and fulfill the related order/registrations/tickets.
     *
     * @param  array<string, mixed>  $providerData
     */
    public function markPaid(Payment $payment, array $providerData = []): Payment
    {
        return DB::transaction(function () use ($payment, $providerData): Payment {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentStatus::Paid) {
                return $locked->fresh(['order.registrations.tickets', 'registration']);
            }

            if (! $locked->status->canTransitionTo(PaymentStatus::Paid)
                && ! in_array($locked->status, [
                    PaymentStatus::Authorized,
                    PaymentStatus::Processing,
                    PaymentStatus::Abandoned,
                    PaymentStatus::Cancelled,
                ], true)
            ) {
                throw InvalidPaymentTransitionException::make(
                    $locked->status->value,
                    PaymentStatus::Paid->value
                );
            }

            if (isset($providerData['provider_payment_id'])) {
                $locked->provider_payment_id = (string) $providerData['provider_payment_id'];
            }
            if (isset($providerData['provider_order_id'])) {
                $locked->provider_order_id = (string) $providerData['provider_order_id'];
            }
            if (isset($providerData['provider_transaction_id'])) {
                $locked->provider_transaction_id = (string) $providerData['provider_transaction_id'];
            }
            if (isset($providerData['provider_checkout_id'])) {
                $locked->provider_checkout_id = (string) $providerData['provider_checkout_id'];
            }

            $locked->status = PaymentStatus::Paid;
            $locked->paid_at = now();
            $locked->failure_reason = null;
            $locked->metadata = array_merge($locked->metadata ?? [], $providerData['metadata'] ?? []);
            $locked->save();

            $order = $locked->order ?? ($locked->registration?->order);

            if ($order === null) {
                throw new EmsException(
                    'Payment is not linked to an order.',
                    [],
                    Response::HTTP_CONFLICT
                );
            }

            /** @var Order $orderLocked */
            $orderLocked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($orderLocked->status !== OrderStatus::Completed) {
                if (! $orderLocked->status->canTransitionTo(OrderStatus::Completed)
                    && $orderLocked->status !== OrderStatus::Pending
                    && $orderLocked->status !== OrderStatus::Cancelled
                ) {
                    throw new EmsException(
                        'Order cannot be completed from its current state.',
                        ['status' => ['Invalid order status: ' . $orderLocked->status->value]],
                        Response::HTTP_CONFLICT
                    );
                }

                $orderLocked->status = OrderStatus::Completed;
                $orderLocked->completed_at = now();
                $orderLocked->cancelled_at = null;
                $orderLocked->save();
            }

            $registrations = Registration::query()
                ->where('order_id', $orderLocked->id)
                ->lockForUpdate()
                ->get();

            $released = (bool) data_get($locked->metadata, 'inventory_released', false);

            foreach ($registrations as $registration) {
                if ($released) {
                    $this->restoreInventoryFor($registration);
                }
                $this->confirmRegistration($registration);
            }

            if ($released) {
                $metadata = $locked->metadata ?? [];
                $metadata['inventory_released'] = false;
                $locked->metadata = $metadata;
                $locked->save();
            }

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.payments.paid', [
                    'payment_uuid' => $locked->uuid,
                    'order_uuid' => $orderLocked->uuid,
                ]);

            ReconcilePaymentJob::dispatch($locked->id);

            return $locked->fresh(['order.registrations.tickets', 'registration']);
        });
    }

    /**
     * @param  array<string, mixed>  $providerData
     */
    public function markFailed(Payment $payment, ?string $reason = null, array $providerData = []): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $providerData): Payment {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [PaymentStatus::Paid, PaymentStatus::Failed, PaymentStatus::Cancelled], true)) {
                return $locked;
            }

            $locked->status = PaymentStatus::Failed;
            $locked->failure_reason = $reason;
            $locked->metadata = array_merge($locked->metadata ?? [], $providerData['metadata'] ?? []);
            $locked->save();

            $registration = $locked->registration
                ?? $locked->order?->registrations()->first();

            $this->releasePendingOrder($locked, OrderStatus::Failed);

            if ($registration instanceof Registration) {
                $this->communications->sendPaymentFailure(
                    $registration->loadMissing(['event', 'tickets', 'ticketType', 'order']),
                    $locked
                );
            }

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->warning('ems.payments.failed', [
                    'payment_uuid' => $locked->uuid,
                    'reason' => $reason,
                ]);

            return $locked->fresh();
        });
    }

    public function markCancelled(Payment $payment, ?string $reason = null): Payment
    {
        return DB::transaction(function () use ($payment, $reason): Payment {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [PaymentStatus::Paid, PaymentStatus::Cancelled, PaymentStatus::Failed], true)) {
                return $locked;
            }

            $locked->status = PaymentStatus::Cancelled;
            $locked->failure_reason = $reason;
            $locked->save();

            $this->releasePendingOrder($locked, OrderStatus::Cancelled);

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.payments.cancelled', [
                    'payment_uuid' => $locked->uuid,
                ]);

            return $locked->fresh();
        });
    }

    public function markAbandoned(Payment $payment, ?string $reason = null): Payment
    {
        return DB::transaction(function () use ($payment, $reason): Payment {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [
                PaymentStatus::Paid,
                PaymentStatus::Cancelled,
                PaymentStatus::Failed,
                PaymentStatus::Abandoned,
                PaymentStatus::Refunded,
            ], true)) {
                return $locked;
            }

            $locked->status = PaymentStatus::Abandoned;
            $locked->failure_reason = $reason;
            $locked->save();

            $this->releasePendingOrder($locked, OrderStatus::Cancelled);

            Log::channel((string) config('ems.logging.channel', 'ems'))
                ->info('ems.payments.abandoned', [
                    'payment_uuid' => $locked->uuid,
                ]);

            return $locked->fresh();
        });
    }

    private function restoreInventoryFor(Registration $registration): void
    {
        if (! $registration->ticket_type_id) {
            return;
        }

        $ticketType = TicketType::query()
            ->whereKey($registration->ticket_type_id)
            ->lockForUpdate()
            ->first();
        if ($ticketType !== null) {
            $ticketType->quantity_sold = (int) $ticketType->quantity_sold + (int) $registration->quantity;
            $ticketType->save();
        }
    }

    private function confirmRegistration(Registration $registration): void
    {
        if ($registration->status === RegistrationStatus::Confirmed
            && $registration->tickets()->exists()
        ) {
            return;
        }

        $registration->status = RegistrationStatus::Confirmed;
        $registration->confirmed_at = now();
        $registration->save();

        $this->tickets->issueFor($registration->fresh());

        RegistrationCreated::dispatch($registration->fresh(['tickets', 'event']), $registration->user);
        QueueRegistrationConfirmation::dispatch($registration->id, true);

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.tickets.issued_after_payment', [
                'registration_uuid' => $registration->uuid,
            ]);
    }

    private function releasePendingOrder(Payment $payment, OrderStatus $orderStatus): void
    {
        $order = $payment->order;

        if ($order === null) {
            return;
        }

        /** @var Order $orderLocked */
        $orderLocked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

        if ($orderLocked->status === OrderStatus::Pending) {
            $orderLocked->status = $orderStatus;

            if ($orderStatus === OrderStatus::Cancelled) {
                $orderLocked->cancelled_at = now();
            }

            if ($orderStatus === OrderStatus::Failed) {
                $orderLocked->failed_at = now();
            }

            $orderLocked->save();
        }

        $registrations = Registration::query()
            ->where('order_id', $orderLocked->id)
            ->lockForUpdate()
            ->get();

        foreach ($registrations as $registration) {
            if (in_array($registration->status, [RegistrationStatus::AwaitingPayment, RegistrationStatus::Pending], true)) {
                $registration->status = RegistrationStatus::Cancelled;
                $registration->cancelled_at = now();
                $registration->save();

                if ($registration->ticket_type_id) {
                    $ticketType = TicketType::query()
                        ->whereKey($registration->ticket_type_id)
                        ->lockForUpdate()
                        ->first();

                    if ($ticketType !== null) {
                        $ticketType->quantity_sold = max(0, $ticketType->quantity_sold - $registration->quantity);
                        $ticketType->save();
                    }
                }

                $metadata = $payment->metadata ?? [];
                $metadata['inventory_released'] = true;
                $payment->metadata = $metadata;
                $payment->save();
            }
        }

        $this->waitlists->promoteAvailable($orderLocked->event);
    }
}
