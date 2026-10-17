<?php

namespace Tests\Feature\Phase251;

use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use App\Ems\Models\TicketType;
use App\Ems\Services\PaymentFulfillmentService;
use App\Ems\Services\Square\SquareReconciliationService;
use App\Platform\Enums\AlertSeverity;
use App\Platform\Models\PlatformAlert;

/**
 * Hourly reconciliation of a missed webhook, and captures on replaced checkout links.
 */
class ReconciliationAndSupersededCaptureTest extends SquareFlowTestCase
{
    private function squareCapture(Payment $payment, string $squarePaymentId): array
    {
        $payment->loadMissing('order');

        return [
            'id' => $squarePaymentId,
            'status' => 'COMPLETED',
            'order_id' => $payment->provider_order_id,
            'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
            'created_at' => now()->toRfc3339String(),
            'reference_id' => $payment->order->reference,
        ];
    }

    public function test_reconciling_a_missed_webhook_fulfils_the_pending_checkout_once(): void
    {
        [$event, $ticketType] = $this->paidEvent();
        $this->startCheckout($event, $ticketType, 'buyer@example.com')->assertCreated();
        $payment = $this->paymentFor('buyer@example.com');
        $this->assertNull($payment->provider_payment_id);

        $this->squarePayments = [$this->squareCapture($payment, 'sq_missed_1')];
        app(SquareReconciliationService::class)->reconcile();
        app(SquareReconciliationService::class)->reconcile();

        $payment->refresh();
        $this->assertSame(1, Payment::query()->count(), 'no split-tender row for the primary capture');
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame('sq_missed_1', $payment->provider_payment_id);
        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(1, (int) $ticketType->fresh()->quantity_sold);
    }

    public function test_reconciled_late_capture_does_not_oversell_a_resold_seat(): void
    {
        [$event, $ticketType] = $this->paidEvent([], 1);
        $this->startCheckout($event, $ticketType, 'first@example.com')->assertCreated();
        $first = $this->paymentFor('first@example.com');
        $this->expireCheckout($first);
        $this->startCheckout($event, $ticketType, 'second@example.com')->assertCreated();

        $this->squarePayments = [$this->squareCapture($first->fresh(), 'sq_late_rec')];
        app(SquareReconciliationService::class)->reconcile();

        $first->refresh();
        $this->assertSame(2, Payment::query()->count());
        $this->assertSame(PaymentStatus::Cancelled, $first->status);
        $this->assertTrue($first->holdsUnfulfilledCaptures());
        $this->assertSame(1, (int) $ticketType->fresh()->quantity_sold);
        $this->assertSame(1, Registration::query()->occupyingCapacity()->count());
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_reconciled_capture_after_failure_is_held_for_refund(): void
    {
        [$event, $ticketType] = $this->paidEvent();
        $this->startCheckout($event, $ticketType, 'buyer@example.com')->assertCreated();
        $payment = $this->paymentFor('buyer@example.com');
        app(PaymentFulfillmentService::class)->markFailed($payment, 'Card declined');

        $this->squarePayments = [$this->squareCapture($payment->fresh(), 'sq_second_card')];
        app(SquareReconciliationService::class)->reconcile();

        $payment->refresh();
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertTrue($payment->holdsUnfulfilledCaptures());
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_capture_on_a_replaced_checkout_link_raises_a_critical_alert(): void
    {
        [$event, $ga] = $this->paidEvent();
        $vip = TicketType::factory()->paid(20)->create(['event_id' => $event->id, 'name' => 'VIP']);

        $this->startCheckout($event, $ga, 'buyer@example.com')->assertCreated();
        $this->startCheckout($event, $vip, 'buyer@example.com')->assertCreated();
        $payment = $this->paymentFor('buyer@example.com');
        $this->assertNotSame('sq_order_1', $payment->provider_order_id);

        $old = [
            'event_id' => 'evt_old_link',
            'type' => 'payment.updated',
            'data' => ['object' => ['payment' => [
                'id' => 'sq_pay_old_link',
                'status' => 'COMPLETED',
                'order_id' => 'sq_order_1',
                'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
            ]]],
        ];
        $this->postWebhook($old)->assertOk();

        $this->assertSame(0, Ticket::query()->count());
        $this->assertNotSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(RegistrationStatus::AwaitingPayment, Registration::query()->first()->status);

        $alerts = PlatformAlert::query()->where('application', 'ems')->get();
        $this->assertCount(1, $alerts);
        $this->assertSame(AlertSeverity::CRITICAL->value, $alerts->first()->severity instanceof AlertSeverity
            ? $alerts->first()->severity->value
            : $alerts->first()->severity);
    }
}
