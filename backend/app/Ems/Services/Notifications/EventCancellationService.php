<?php

namespace App\Ems\Services\Notifications;

use App\Ems\Contracts\EventNotificationDispatcher;
use App\Ems\Enums\NotificationAudience;
use App\Ems\Enums\NotificationType;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Models\Event;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use Illuminate\Support\Facades\Log;

/**
 * Event cancellation communications + refund workflow initiation (no Square refunds yet).
 */
class EventCancellationService
{
    public function __construct(
        private readonly EventNotificationDispatcher $dispatcher,
        private readonly EventCommunicationService $communications,
    ) {
    }

    /**
     * @return array{notified: int, refunds_initiated: int}
     */
    public function handleCancelled(Event $event, ?string $reason = null): array
    {
        if ($reason !== null) {
            $event->cancellation_reason = $reason;
            $event->save();
        }

        $notified = $this->dispatcher->broadcastToEvent($event, NotificationType::EventCancelled->value, [
            'audience' => NotificationAudience::Everyone->value,
            'cancellation_reason' => $event->cancellation_reason ?? $reason ?? '',
            'idempotency_suffix' => 'event_cancel',
            'force' => true,
            'skip_preference_check' => true,
        ]);

        $refunds = $this->initiateRefundWorkflow($event);

        // Notify attached VMS volunteer opportunities
        $vmsOpportunities = \App\Volunteering\Models\Opportunity::where('event_id', $event->id)->get();
        foreach ($vmsOpportunities as $opp) {
            if ($opp->status !== 'cancelled') {
                app(\App\Volunteering\Services\VolunteerOpportunityService::class)->updateOpportunity($opp, [
                    'status' => 'cancelled',
                    'cancellation_reason' => $event->cancellation_reason ?? $reason ?? 'Event cancelled',
                ], $event->created_by ?? 1);
            }
        }

        Log::channel((string) config('ems.logging.channel', 'ems'))
            ->info('ems.notifications.event_cancelled', [
                'event_uuid' => $event->uuid,
                'notified' => $notified,
                'refunds_initiated' => $refunds,
            ]);

        return [
            'notified' => $notified,
            'refunds_initiated' => $refunds,
        ];
    }

    /**
     * Marks paid registrations for refund processing and queues refund-initiated emails.
     * Does NOT call Square — Phase 6/7 owns provider refunds.
     */
    public function initiateRefundWorkflow(Event $event): int
    {
        $count = 0;

        Registration::query()
            ->where('event_id', $event->id)
            ->where('status', RegistrationStatus::Confirmed->value)
            ->with(['settledPayment', 'order', 'event', 'tickets', 'ticketType'])
            ->orderBy('id')
            ->chunkById(50, function ($registrations) use (&$count): void {
                foreach ($registrations as $registration) {
                    $payment = $registration->settledPayment;

                    if ($payment === null || $payment->status !== PaymentStatus::Paid) {
                        continue;
                    }

                    if ((float) $payment->amount <= 0) {
                        continue;
                    }

                    $meta = $payment->metadata ?? [];
                    if (! empty($meta['refund_workflow_initiated_at'])) {
                        continue;
                    }

                    $meta['refund_workflow_initiated_at'] = now()->toIso8601String();
                    $meta['refund_workflow_status'] = 'initiated';
                    $payment->metadata = $meta;
                    $payment->save();

                    $this->communications->sendRefund(
                        $registration,
                        NotificationType::RefundInitiated,
                        (float) $payment->amount - (float) $payment->amount_refunded,
                        $payment
                    );

                    $count++;
                }
            });

        return $count;
    }

    public function notifyRefundCompleted(Registration $registration, Payment $payment, float $amount): void
    {
        $this->communications->sendRefund(
            $registration,
            NotificationType::RefundCompleted,
            $amount,
            $payment
        );
    }

    public function notifyRefundFailed(Registration $registration, Payment $payment, float $amount): void
    {
        $this->communications->sendRefund(
            $registration,
            NotificationType::RefundFailed,
            $amount,
            $payment
        );
    }
}
