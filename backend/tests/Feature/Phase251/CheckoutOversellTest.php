<?php

namespace Tests\Feature\Phase251;

use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\WebhookEventStatus;
use App\Ems\Models\Payment;
use App\Ems\Models\Ticket;
use App\Ems\Models\WebhookEvent;
use App\Ems\Services\PaymentFulfillmentService;
use App\Platform\Models\PlatformAlert;

class CheckoutOversellTest extends SquareFlowTestCase
{
    public function test_late_payment_for_resold_last_unit_is_not_fulfilled_and_is_routed_to_staff(): void
    {
        [$event, $ticketType] = $this->paidEvent([], ticketLimit: 1);

        $this->startCheckout($event, $ticketType, 'late@example.com')->assertCreated();
        $late = $this->paymentFor('late@example.com');
        $this->assertSame(1, $ticketType->fresh()->quantity_sold);

        $this->expireCheckout($late);
        $this->assertSame(PaymentStatus::Abandoned, $late->fresh()->status);
        $this->assertSame(0, $ticketType->fresh()->quantity_sold);

        $this->startCheckout($event, $ticketType, 'winner@example.com')->assertCreated();
        $winner = $this->paymentFor('winner@example.com');
        $this->completedWebhook($winner, 'evt_winner')->assertOk();
        $this->assertSame(PaymentStatus::Paid, $winner->fresh()->status);

        $this->completedWebhook($late->fresh(), 'evt_late')->assertOk();

        $late = $late->fresh(['order', 'registration']);
        $this->assertSame(PaymentStatus::Cancelled, $late->status);
        $this->assertNotNull(data_get($late->metadata, 'capacity_conflict_at'));
        $this->assertTrue($late->holdsUnfulfilledCaptures());
        $this->assertSame('capacity_unavailable', $late->staleCaptureEntries()[0]['reason'] ?? null);
        $this->assertSame('sq_pay_'.$late->id, $late->staleCaptureEntries()[0]['square_payment_id'] ?? null);
        $this->assertSame(RegistrationStatus::Cancelled, $late->registration->status);
        $this->assertSame(OrderStatus::Cancelled, $late->order->status);
        $this->assertSame(0, Ticket::query()->where('registration_id', $late->registration_id)->count());

        $this->assertSame(1, $ticketType->fresh()->quantity_sold, 'ticket type must not be oversold');
        $this->assertSame(1, $event->fresh()->occupiedSeats());
        $this->assertSame(WebhookEventStatus::Processed->value, WebhookEvent::query()->where('event_id', 'evt_late')->value('status'));
        $this->assertSame(1, PlatformAlert::query()->where('application', 'ems')->count());
    }

    public function test_event_level_capacity_conflict_is_also_blocked(): void
    {
        [$event, $ticketType] = $this->paidEvent(['capacity' => 1]);

        $this->startCheckout($event, $ticketType, 'late@example.com')->assertCreated();
        $late = $this->paymentFor('late@example.com');
        $this->expireCheckout($late);

        $this->startCheckout($event, $ticketType, 'winner@example.com')->assertCreated();
        $this->completedWebhook($this->paymentFor('winner@example.com'), 'evt_winner')->assertOk();

        $this->completedWebhook($late->fresh(), 'evt_late')->assertOk();

        $this->assertSame(PaymentStatus::Cancelled, $late->fresh()->status);
        $this->assertSame(1, $event->fresh()->occupiedSeats());
        $this->assertSame(0, Ticket::query()->where('registration_id', $late->registration_id)->count());
    }

    public function test_late_payment_is_fulfilled_when_released_seat_is_still_free(): void
    {
        [$event, $ticketType] = $this->paidEvent([], ticketLimit: 1);

        $this->startCheckout($event, $ticketType, 'late@example.com')->assertCreated();
        $late = $this->paymentFor('late@example.com');
        $this->expireCheckout($late);

        $this->completedWebhook($late->fresh(), 'evt_late')->assertOk();

        $this->assertSame(PaymentStatus::Paid, $late->fresh()->status);
        $this->assertSame(RegistrationStatus::Confirmed, $late->registration->fresh()->status);
        $this->assertSame(1, $ticketType->fresh()->quantity_sold);
        $this->assertSame(0, PlatformAlert::query()->count());
    }

    public function test_duplicate_late_deliveries_record_one_capture_and_never_issue_tickets(): void
    {
        [$event, $ticketType] = $this->paidEvent([], ticketLimit: 1);

        $this->startCheckout($event, $ticketType, 'late@example.com')->assertCreated();
        $late = $this->paymentFor('late@example.com');
        $this->expireCheckout($late);
        $this->startCheckout($event, $ticketType, 'winner@example.com')->assertCreated();
        $this->completedWebhook($this->paymentFor('winner@example.com'), 'evt_winner')->assertOk();

        $this->completedWebhook($late->fresh(), 'evt_late')->assertOk();
        $this->completedWebhook($late->fresh(), 'evt_late')->assertOk();
        $this->completedWebhook($late->fresh(), 'evt_late_retry_new_id')->assertOk();
        app(PaymentFulfillmentService::class)->markPaid($late->fresh(), ['provider_payment_id' => 'sq_pay_'.$late->id]);

        $late = $late->fresh();
        $this->assertCount(1, $late->staleCaptureEntries());
        $this->assertSame(0, Ticket::query()->where('registration_id', $late->registration_id)->count());
        $this->assertSame(1, $ticketType->fresh()->quantity_sold);
    }

    public function test_repeated_checkout_requests_reserve_inventory_once(): void
    {
        [$event, $ticketType] = $this->paidEvent([], ticketLimit: 2);

        $this->startCheckout($event, $ticketType, 'repeat@example.com')->assertCreated();
        $this->startCheckout($event, $ticketType, 'repeat@example.com')->assertSuccessful();

        $this->assertSame(1, $ticketType->fresh()->quantity_sold);
        $this->assertSame(1, Payment::query()->whereHas('order', fn ($q) => $q->where('buyer_email', 'repeat@example.com'))->count());
    }

    public function test_payment_failure_releases_inventory_and_later_capture_goes_to_staff_queue(): void
    {
        [$event, $ticketType] = $this->paidEvent([], ticketLimit: 1);

        $this->startCheckout($event, $ticketType, 'flaky@example.com')->assertCreated();
        $payment = $this->paymentFor('flaky@example.com');

        $this->completedWebhook($payment, 'evt_failed', 'sq_pay_failed_attempt', 'FAILED')->assertOk();
        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertSame(0, $ticketType->fresh()->quantity_sold);

        $this->completedWebhook($payment->fresh(), 'evt_completed_after_failed', 'sq_pay_retry')->assertOk();

        $payment = $payment->fresh();
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertTrue($payment->holdsUnfulfilledCaptures());
        $this->assertSame(WebhookEventStatus::Processed->value, WebhookEvent::query()->where('event_id', 'evt_completed_after_failed')->value('status'));
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_stale_paid_event_after_refund_is_a_no_op(): void
    {
        [$event, $ticketType] = $this->paidEvent();

        $this->startCheckout($event, $ticketType, 'refunded@example.com')->assertCreated();
        $payment = $this->paymentFor('refunded@example.com');
        $this->completedWebhook($payment, 'evt_paid')->assertOk();
        $payment->fresh()->forceFill(['status' => PaymentStatus::Refunded])->save();

        $this->completedWebhook($payment->fresh(), 'evt_paid_replayed_late')->assertOk();

        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertSame(WebhookEventStatus::Processed->value, WebhookEvent::query()->where('event_id', 'evt_paid_replayed_late')->value('status'));
    }
}
