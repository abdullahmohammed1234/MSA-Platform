<?php

namespace Tests\Unit\Ems;

use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Jobs\ReconcilePaymentJob;
use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use App\Ems\Models\TicketType;
use App\Ems\Services\PaymentFulfillmentService;
use App\Ems\Services\PaymentReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Ems\EmsTestCase;

class PaymentReconciliationTest extends EmsTestCase
{
    use RefreshDatabase;

    public function test_reconcile_payment_job_is_safe_for_stale_buyer_cancelled_capture(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Cancelled,
            'total_amount' => 15,
        ]);
        $ticketType = TicketType::factory()->paid(15)->create(['event_id' => $order->event_id]);
        $registration = Registration::factory()->create([
            'event_id' => $order->event_id,
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'status' => RegistrationStatus::Cancelled,
        ]);
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'registration_id' => $registration->id,
            'amount' => 15,
            'currency' => 'CAD',
            'provider' => 'square',
            'status' => PaymentStatus::Cancelled->value,
            'provider_order_id' => 'sq_order_reconcile_job',
            'metadata' => [
                'buyer_cancelled_at' => now()->toIso8601String(),
            ],
        ]);

        app(PaymentFulfillmentService::class)->recordStaleCaptureAfterBuyerCancel(
            $payment,
            'sq_pay_reconcile_job',
            'sq_order_reconcile_job',
            source: 'square_reconciliation',
        );

        $payment = $payment->fresh();
        $job = new ReconcilePaymentJob($payment->id);
        $reconciliation = app(PaymentReconciliationService::class);

        $job->handle($reconciliation);
        (new ReconcilePaymentJob($payment->id))->handle($reconciliation);

        $this->assertSame(PaymentStatus::Cancelled, $payment->fresh()->status);
        $this->assertSame(RegistrationStatus::Cancelled, $registration->fresh()->status);
        $this->assertSame(0, Ticket::query()->count());
        $this->assertCount(1, $payment->fresh()->metadata['stale_captures_after_buyer_cancel'] ?? []);
    }
}
