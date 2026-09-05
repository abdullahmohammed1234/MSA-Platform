<?php

namespace App\Ems\Services\Notifications;

use App\Ems\Contracts\EventNotificationDispatcher;
use App\Ems\Enums\NotificationType;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use Illuminate\Support\Facades\Log;

/**
 * High-level EMS communication workflows built on the dispatcher.
 */
class EventCommunicationService
{
    public function __construct(
        private readonly EventNotificationDispatcher $dispatcher,
    ) {
    }

    public function sendRegistrationBundle(Registration $registration, bool $includePayment = false): void
    {
        $registration->loadMissing(['event', 'tickets', 'ticketType', 'order', 'settledPayment']);

        $this->dispatcher->notifyRegistrationImmediately(
            $registration,
            NotificationType::RegistrationConfirmed->value,
            ['idempotency_suffix' => 'confirm']
        );

        if ($includePayment) {
            $payment = $registration->settledPayment;
            if ($payment !== null && $payment->status === PaymentStatus::Paid) {
                $this->sendPaymentConfirmation($registration, $payment);
            }
        }

        foreach ($registration->tickets as $index => $ticket) {
            $holderEmail = strtolower(trim((string) ($ticket->holder_email ?? '')));
            $primaryEmail = strtolower(trim((string) ($registration->attendee_email ?? '')));

            if ($index === 0 && ($holderEmail === '' || $holderEmail === $primaryEmail)) {
                continue;
            }

            if ($holderEmail !== '' && $holderEmail !== $primaryEmail) {
                $this->dispatcher->notifyRegistration(
                    $registration,
                    NotificationType::RegistrationConfirmed->value,
                    [
                        'recipient_email' => $ticket->holder_email,
                        'attendee_name' => $ticket->holder_name ?? $registration->attendee_name,
                        'attendee_email' => $ticket->holder_email,
                        'ticket_id' => $ticket->id,
                        'idempotency_suffix' => 'ticket:' . $ticket->id,
                    ]
                );
            }
        }

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.notifications.registration_bundle_dispatched', [
                'registration_uuid' => $registration->uuid,
            ]);
    }

    public function sendPaymentConfirmation(Registration $registration, Payment $payment): void
    {
        $this->dispatcher->notifyRegistration(
            $registration,
            NotificationType::PaymentConfirmation->value,
            [
                'payment_id' => $payment->id,
                'payment' => $payment,
                'idempotency_suffix' => 'payment:' . $payment->id,
            ]
        );
    }

    public function sendPaymentFailure(Registration $registration, Payment $payment): void
    {
        $this->dispatcher->notifyRegistration(
            $registration,
            NotificationType::PaymentFailure->value,
            [
                'payment_id' => $payment->id,
                'payment' => $payment,
                'idempotency_suffix' => 'payment_fail:' . $payment->id,
            ]
        );
    }

    public function sendWaitlistJoined(Registration $registration): void
    {
        $this->dispatcher->notifyRegistration(
            $registration,
            NotificationType::WaitlistConfirmed->value,
            ['idempotency_suffix' => 'waitlist_join']
        );
    }

    public function sendWaitlistRemoved(Registration $registration): void
    {
        $this->dispatcher->notifyRegistration(
            $registration,
            NotificationType::WaitlistRemoved->value,
            ['idempotency_suffix' => 'waitlist_leave', 'force' => true]
        );
    }

    public function sendWaitlistPromoted(Registration $registration): void
    {
        $this->dispatcher->notifyRegistration(
            $registration,
            NotificationType::WaitlistPromoted->value,
            ['idempotency_suffix' => 'waitlist_promote', 'force' => true]
        );
    }

    public function resend(Registration $registration, NotificationType $type): void
    {
        $this->dispatcher->notifyRegistration(
            $registration,
            $type->value,
            ['force' => true, 'idempotency_suffix' => 'manual_resend']
        );
    }

    public function sendRefund(
        Registration $registration,
        NotificationType $type,
        float $refundAmount,
        ?Payment $payment = null,
        ?string $operationKey = null
    ): void {
        $this->dispatcher->notifyRegistration(
            $registration,
            $type->value,
            [
                'refund_amount' => $refundAmount,
                'payment_id' => $payment?->id,
                'payment' => $payment,
                'force' => false,
                'idempotency_suffix' => $type->value . ':' . ($operationKey ?? (string) ($payment?->id ?? 'na')),
            ]
        );
    }
}
