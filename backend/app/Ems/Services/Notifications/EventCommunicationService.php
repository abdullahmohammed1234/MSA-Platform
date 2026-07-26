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

    /**
     * Registration confirmation + ticket + QR (+ payment confirmation when paid).
     */
    public function sendRegistrationBundle(Registration $registration, bool $includePayment = false): void
    {
        $registration->loadMissing(['event', 'tickets', 'ticketType', 'order', 'settledPayment']);

        $this->dispatcher->notifyRegistration(
            $registration,
            NotificationType::RegistrationConfirmed->value,
            ['idempotency_suffix' => 'confirm']
        );

        if ($registration->tickets->isNotEmpty()) {
            $this->dispatcher->notifyRegistration(
                $registration,
                NotificationType::TicketEmail->value,
                ['idempotency_suffix' => 'ticket']
            );

            $this->dispatcher->notifyRegistration(
                $registration,
                NotificationType::QrCodeEmail->value,
                ['idempotency_suffix' => 'qr']
            );
        }

        if ($includePayment) {
            $payment = $registration->settledPayment;
            if ($payment !== null && $payment->status === PaymentStatus::Paid) {
                $this->sendPaymentConfirmation($registration, $payment);
            }
        }

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.notifications.registration_bundle_queued', [
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
        ?Payment $payment = null
    ): void {
        $this->dispatcher->notifyRegistration(
            $registration,
            $type->value,
            [
                'refund_amount' => $refundAmount,
                'payment_id' => $payment?->id,
                'payment' => $payment,
                'force' => true,
                'idempotency_suffix' => $type->value . ':' . ($payment?->id ?? 'na'),
            ]
        );
    }
}
