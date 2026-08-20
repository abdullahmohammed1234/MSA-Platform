<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\NotificationType;
use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\SquareRefundStatus;
use App\Ems\Enums\TicketStatus;
use App\Ems\Enums\WebhookEventStatus;
use App\Ems\Models\Event;
use App\Ems\Models\EventNotification;
use App\Ems\Models\EventStaff;
use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\SquareRefund;
use App\Ems\Models\TicketType;
use App\Ems\Models\WebhookEvent;
use App\Ems\Services\Operations\CheckInService;
use App\Ems\Services\Square\SquareReconciliationService;
use App\Ems\Services\Square\SquareRefundService;
use App\Ems\Services\Ticketing\DefaultTicketIssuer;
use App\Ems\Support\EmsRoles;
use Illuminate\Support\Facades\Http;

class EmsSquareRefundReliabilityTest extends EmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ems.payments.enabled' => true,
            'ems.payments.square.access_token' => 'test-token',
            'ems.payments.square.location_id' => 'LOCATION_TEST',
            'ems.payments.square.application_id' => 'app-test',
            'ems.payments.square.webhook_signature_key' => 'webhook-secret',
            'ems.payments.square.webhook_notification_url' => 'https://example.test/api/v1/webhooks/square',
            'ems.payments.square.environment' => 'sandbox',
            'ems.notifications.enabled' => true,
            'queue.default' => 'sync',
            'ems.tickets.enabled' => true,
        ]);
    }

    public function test_refund_amount_greater_than_remaining_is_rejected(): void
    {
        [, , , , $payment] = $this->paidCheckout();

        Http::fake(['*/v2/refunds' => Http::response(['refund' => []], 200)]);

        $this->actingAsEms($this->admin())
            ->postJson($this->url("payments/{$payment->uuid}/refund"), ['amount' => 20])
            ->assertStatus(422);

        $this->assertSame(0, SquareRefund::query()->count());
        Http::assertNothingSent();
    }

    public function test_refund_amount_equal_to_remaining_succeeds(): void
    {
        [, , , $registration, $payment] = $this->paidCheckout();

        Http::fake([
            '*/v2/refunds' => Http::response($this->squareRefundPayload('sq_full', $payment, 1500, 'COMPLETED'), 200),
        ]);

        $this->actingAsEms($this->admin())
            ->postJson($this->url("payments/{$payment->uuid}/refund"), ['amount' => 15])
            ->assertOk();

        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertSame(TicketStatus::Revoked, $registration->tickets()->first()->status);
    }

    public function test_zero_and_negative_refunds_are_rejected(): void
    {
        [, , , , $payment] = $this->paidCheckout();

        Http::fake(['*/v2/refunds' => Http::response(['refund' => []], 200)]);

        $this->actingAsEms($this->admin())
            ->postJson($this->url("payments/{$payment->uuid}/refund"), ['amount' => 0])
            ->assertStatus(422);

        $this->actingAsEms($this->admin())
            ->postJson($this->url("payments/{$payment->uuid}/refund"), ['amount' => -1])
            ->assertStatus(422);

        $this->assertSame(0, SquareRefund::query()->count());
    }

    public function test_excessive_decimal_precision_is_rounded_safely(): void
    {
        [, , , $registration, $payment] = $this->paidCheckout();

        Http::fake([
            '*/v2/refunds' => Http::response($this->squareRefundPayload('sq_cents', $payment, 556, 'COMPLETED'), 200),
        ]);

        $this->actingAsEms($this->admin())
            ->postJson($this->url("payments/{$payment->uuid}/refund"), ['amount' => 5.555])
            ->assertOk();

        $this->assertEquals(5.56, (float) SquareRefund::query()->first()->amount);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $payment->fresh()->status);
        $this->assertSame(TicketStatus::Issued, $registration->tickets()->first()->status);
    }

    public function test_second_refund_cannot_start_while_one_is_pending(): void
    {
        [, , , , $payment] = $this->paidCheckout();

        Http::fake([
            '*/v2/refunds' => Http::response($this->squareRefundPayload('sq_pend', $payment, 1500, 'PENDING'), 200),
        ]);

        $this->actingAsEms($this->admin())
            ->postJson($this->url("payments/{$payment->uuid}/refund"))
            ->assertOk();

        $this->actingAsEms($this->admin())
            ->postJson($this->url("payments/{$payment->uuid}/refund"))
            ->assertStatus(409);

        $this->assertSame(1, SquareRefund::query()->count());
    }

    public function test_retry_after_uncertain_square_failure_reuses_idempotency_key(): void
    {
        [, , , , $payment] = $this->paidCheckout();
        $calls = 0;

        Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$calls, $payment) {
            if ($request->method() !== 'POST' || ! str_contains($request->url(), '/v2/refunds')) {
                return Http::response([], 200);
            }

            $calls++;
            if ($calls === 1) {
                return Http::response(['errors' => [['detail' => 'upstream timeout']]], 500);
            }

            return Http::response($this->squareRefundPayload('sq_retry', $payment, 1500, 'PENDING'), 200);
        });

        $this->actingAsEms($this->admin())
            ->postJson($this->url("payments/{$payment->uuid}/refund"))
            ->assertStatus(502);

        $first = SquareRefund::query()->firstOrFail();
        $this->assertSame(SquareRefundStatus::Pending, $first->status);
        $this->assertSame(SquareRefundService::idempotencyKeyForUuid($first->uuid), $first->idempotency_key);

        $this->actingAsEms($this->admin())
            ->postJson($this->url("payments/{$payment->uuid}/refund"))
            ->assertOk();

        $this->assertSame(1, SquareRefund::query()->count());
        $this->assertSame($first->idempotency_key, SquareRefund::query()->first()->idempotency_key);
        $this->assertSame(2, $calls);

        $postedKeys = [];
        foreach (Http::recorded() as $pair) {
            $request = $pair[0];
            if ($request->method() === 'POST' && str_contains($request->url(), '/v2/refunds')) {
                $postedKeys[] = $request['idempotency_key'] ?? null;
            }
        }
        $this->assertSame([$first->idempotency_key, $first->idempotency_key], $postedKeys);
    }

    public function test_separate_partial_refunds_use_different_idempotency_keys(): void
    {
        [, , , , $payment] = $this->paidCheckout();

        Http::fake([
            '*/v2/refunds' => Http::sequence()
                ->push($this->squareRefundPayload('sq_p1', $payment, 500, 'COMPLETED'), 200)
                ->push($this->squareRefundPayload('sq_p2', $payment, 500, 'COMPLETED'), 200),
        ]);

        $this->actingAsEms($this->admin())
            ->postJson($this->url("payments/{$payment->uuid}/refund"), ['amount' => 5])
            ->assertOk();
        $this->actingAsEms($this->admin())
            ->postJson($this->url("payments/{$payment->uuid}/refund"), ['amount' => 5])
            ->assertOk();

        $keys = SquareRefund::query()->orderBy('id')->pluck('idempotency_key')->all();
        $this->assertCount(2, $keys);
        $this->assertNotSame($keys[0], $keys[1]);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $payment->fresh()->status);
    }

    public function test_rejected_square_refund_does_not_revoke_ticket(): void
    {
        [$event, , , $registration, $payment] = $this->paidCheckout();

        $this->postWebhook([
            'event_id' => 'evt_rejected',
            'type' => 'refund.updated',
            'data' => ['object' => ['refund' => [
                'id' => 'sq_rej',
                'status' => 'REJECTED',
                'payment_id' => $payment->provider_payment_id,
                'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
            ]]],
        ])->assertOk();

        $this->assertSame(SquareRefundStatus::Rejected, SquareRefund::query()->first()->status);
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(TicketStatus::Issued, $registration->tickets()->first()->status);
        $this->assertCheckInAllowed($event->fresh(), $registration->tickets()->first()->code);
    }

    public function test_failed_refund_does_not_revoke_ticket(): void
    {
        [$event, , , $registration, $payment] = $this->paidCheckout();

        Http::fake([
            '*/v2/refunds' => Http::response($this->squareRefundPayload('sq_fail', $payment, 1500, 'FAILED'), 200),
        ]);

        $this->actingAsEms($this->admin())
            ->postJson($this->url("payments/{$payment->uuid}/refund"))
            ->assertOk();

        $this->assertSame(SquareRefundStatus::Failed, SquareRefund::query()->first()->status);
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(TicketStatus::Issued, $registration->tickets()->first()->status);
        $this->assertCheckInAllowed($event->fresh(), $registration->tickets()->first()->code);
    }

    public function test_pending_refund_does_not_revoke_ticket(): void
    {
        [$event, , , $registration, $payment] = $this->paidCheckout();

        Http::fake([
            '*/v2/refunds' => Http::response($this->squareRefundPayload('sq_pend2', $payment, 1500, 'PENDING'), 200),
        ]);

        $this->actingAsEms($this->admin())
            ->postJson($this->url("payments/{$payment->uuid}/refund"))
            ->assertOk();

        $this->assertSame(SquareRefundStatus::Pending, SquareRefund::query()->first()->status);
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(TicketStatus::Issued, $registration->tickets()->first()->status);
        $this->assertCheckInAllowed($event->fresh(), $registration->tickets()->first()->code);
    }

    public function test_completed_partial_refund_keeps_ticket_and_allows_check_in(): void
    {
        [$event, , , $registration, $payment] = $this->paidCheckout();

        Http::fake([
            '*/v2/refunds' => Http::response($this->squareRefundPayload('sq_part', $payment, 500, 'COMPLETED'), 200),
        ]);

        $this->actingAsEms($this->admin())
            ->postJson($this->url("payments/{$payment->uuid}/refund"), ['amount' => 5])
            ->assertOk();

        $this->assertSame(PaymentStatus::PartiallyRefunded, $payment->fresh()->status);
        $this->assertEquals(5.0, (float) $payment->fresh()->amount_refunded);
        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
        $this->assertSame(TicketStatus::Issued, $registration->tickets()->first()->status);
        $this->assertCheckInAllowed($event->fresh(), $registration->tickets()->first()->code);
    }

    public function test_multiple_partial_refunds_that_equal_payment_revoke_tickets(): void
    {
        [$event, , , $registration, $payment] = $this->paidCheckout();

        Http::fake([
            '*/v2/refunds' => Http::sequence()
                ->push($this->squareRefundPayload('sq_m1', $payment, 500, 'COMPLETED'), 200)
                ->push($this->squareRefundPayload('sq_m2', $payment, 500, 'COMPLETED'), 200)
                ->push($this->squareRefundPayload('sq_m3', $payment, 500, 'COMPLETED'), 200),
        ]);

        foreach ([5, 5, 5] as $amount) {
            $this->actingAsEms($this->admin())
                ->postJson($this->url("payments/{$payment->uuid}/refund"), ['amount' => $amount])
                ->assertOk();
        }

        $this->assertSame(3, SquareRefund::query()->count());
        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertEquals(15.0, (float) $payment->fresh()->amount_refunded);
        $this->assertSame(OrderStatus::Refunded, $payment->order()->first()->status);
        $this->assertSame(RegistrationStatus::Refunded, $registration->fresh()->status);
        $this->assertSame(TicketStatus::Revoked, $registration->tickets()->first()->status);
        $this->assertCheckInDenied($event->fresh(), $registration->tickets()->first()->code);
    }

    public function test_completed_then_stale_pending_webhook_stays_completed(): void
    {
        [, , , $registration, $payment] = $this->paidCheckout();

        $this->postWebhook($this->refundWebhook('evt_c1', 'refund.updated', [
            'id' => 'sq_order',
            'status' => 'COMPLETED',
            'payment_id' => $payment->provider_payment_id,
            'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
        ]))->assertOk();

        $this->postWebhook($this->refundWebhook('evt_c1_old', 'refund.created', [
            'id' => 'sq_order',
            'status' => 'PENDING',
            'payment_id' => $payment->provider_payment_id,
            'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
        ]))->assertOk();

        $this->assertSame(SquareRefundStatus::Completed, SquareRefund::query()->first()->status);
        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertSame(TicketStatus::Revoked, $registration->tickets()->first()->status);
        $this->assertSame(1, SquareRefund::query()->count());
    }

    public function test_completed_then_stale_failed_webhook_stays_completed(): void
    {
        [, , , $registration, $payment] = $this->paidCheckout();

        $this->postWebhook($this->refundWebhook('evt_c2', 'refund.updated', [
            'id' => 'sq_cf',
            'status' => 'COMPLETED',
            'payment_id' => $payment->provider_payment_id,
            'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
        ]))->assertOk();

        $this->postWebhook($this->refundWebhook('evt_c2_fail', 'refund.updated', [
            'id' => 'sq_cf',
            'status' => 'FAILED',
            'payment_id' => $payment->provider_payment_id,
            'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
        ]))->assertOk();

        $this->assertSame(SquareRefundStatus::Completed, SquareRefund::query()->first()->status);
        $this->assertSame(TicketStatus::Revoked, $registration->tickets()->first()->status);
    }

    public function test_duplicate_completed_webhook_is_idempotent(): void
    {
        [$event, , , $registration, $payment] = $this->paidCheckout();
        $ticketType = $registration->ticketType;
        $ticketType->quantity_sold = 1;
        $ticketType->quantity = 10;
        $ticketType->save();

        $payload = $this->refundWebhook('evt_dup_a', 'refund.updated', [
            'id' => 'sq_dup',
            'status' => 'COMPLETED',
            'payment_id' => $payment->provider_payment_id,
            'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
        ]);

        $this->postWebhook($payload)->assertOk();
        $this->postWebhook(array_merge($payload, ['event_id' => 'evt_dup_b']))->assertOk();

        $this->assertSame(1, SquareRefund::query()->count());
        $this->assertSame(1, Registration::query()->count());
        $this->assertSame(1, $registration->tickets()->count());
        $this->assertSame(0, (int) $ticketType->fresh()->quantity_sold);
        $this->assertSame(1, EventNotification::query()->where('type', NotificationType::RefundCompleted->value)->count());
        $this->assertCheckInDenied($event->fresh(), $registration->tickets()->first()->code);
    }

    public function test_square_refund_matches_by_payment_id_not_another_payment(): void
    {
        [, , , , $payment] = $this->paidCheckout();
        [, , , $otherReg, $other] = $this->paidCheckout();

        $this->postWebhook($this->refundWebhook('evt_match', 'refund.created', [
            'id' => 'sq_match',
            'status' => 'COMPLETED',
            'payment_id' => $payment->provider_payment_id,
            'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
        ]))->assertOk();

        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $other->fresh()->status);
        $this->assertSame(TicketStatus::Issued, $otherReg->tickets()->first()->status);
        $this->assertSame($payment->id, SquareRefund::query()->first()->payment_id);
    }

    public function test_unknown_square_payment_id_is_not_attached_to_another_payment(): void
    {
        [, , , $registration, $payment] = $this->paidCheckout();

        $this->postWebhook($this->refundWebhook('evt_unknown', 'refund.created', [
            'id' => 'sq_unknown_rf',
            'status' => 'COMPLETED',
            'payment_id' => 'sq_pay_does_not_exist',
            'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
        ]))->assertOk();

        $this->assertSame(0, SquareRefund::query()->count());
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(TicketStatus::Issued, $registration->tickets()->first()->status);
        $this->assertSame(WebhookEventStatus::Unmatched->value, WebhookEvent::query()->where('event_id', 'evt_unknown')->value('status'));
    }

    public function test_duplicate_square_refund_id_does_not_create_another_row(): void
    {
        [, , , , $payment] = $this->paidCheckout();

        $refund = [
            'id' => 'sq_once',
            'status' => 'COMPLETED',
            'payment_id' => $payment->provider_payment_id,
            'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
        ];

        $this->postWebhook($this->refundWebhook('evt_once_1', 'refund.created', $refund))->assertOk();
        $this->postWebhook($this->refundWebhook('evt_once_2', 'refund.updated', $refund))->assertOk();

        $this->assertSame(1, SquareRefund::query()->count());
    }

    public function test_reconciliation_discovers_refund_without_webhook(): void
    {
        [, , , $registration, $payment] = $this->paidCheckout();

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($payment) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/v2/refunds')) {
                return Http::response([
                    'refunds' => [[
                        'id' => 'sq_discovered',
                        'status' => 'COMPLETED',
                        'payment_id' => $payment->provider_payment_id,
                        'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                        'created_at' => now()->toRfc3339String(),
                    ]],
                ], 200);
            }

            if (str_contains($request->url(), '/v2/payments')) {
                return Http::response(['payments' => []], 200);
            }

            if (str_contains($request->url(), '/v2/catalog')) {
                return Http::response(['objects' => []], 200);
            }

            return Http::response([], 200);
        });

        $first = app(SquareReconciliationService::class)->reconcile();
        $second = app(SquareReconciliationService::class)->reconcile();

        $this->assertSame(1, $first['refunds_applied']);
        $this->assertSame(1, SquareRefund::query()->count());
        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertSame(TicketStatus::Revoked, $registration->tickets()->first()->status);
        $this->assertSame(1, $second['refunds_applied']);
        $this->assertSame(1, SquareRefund::query()->count());
    }

    public function test_reconciliation_does_not_duplicate_webhook_created_refund(): void
    {
        [, , , , $payment] = $this->paidCheckout();

        $this->postWebhook($this->refundWebhook('evt_wh', 'refund.updated', [
            'id' => 'sq_wh',
            'status' => 'COMPLETED',
            'payment_id' => $payment->provider_payment_id,
            'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
        ]))->assertOk();

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($payment) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/v2/refunds')) {
                return Http::response([
                    'refunds' => [[
                        'id' => 'sq_wh',
                        'status' => 'COMPLETED',
                        'payment_id' => $payment->provider_payment_id,
                        'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                        'created_at' => now()->toRfc3339String(),
                    ]],
                ], 200);
            }

            if (str_contains($request->url(), '/v2/payments')) {
                return Http::response(['payments' => []], 200);
            }

            return Http::response(['objects' => []], 200);
        });

        app(SquareReconciliationService::class)->reconcile();

        $this->assertSame(1, SquareRefund::query()->count());
        $this->assertSame('sq_wh', SquareRefund::query()->first()->provider_refund_id);
    }

    public function test_full_refund_restores_ticket_type_inventory_once(): void
    {
        [, $ticketType, , $registration, $payment] = $this->paidCheckout();
        $ticketType->quantity = 10;
        $ticketType->quantity_sold = 1;
        $ticketType->save();

        Http::fake([
            '*/v2/refunds' => Http::response($this->squareRefundPayload('sq_inv', $payment, 1500, 'COMPLETED'), 200),
        ]);

        $this->actingAsEms($this->admin())
            ->postJson($this->url("payments/{$payment->uuid}/refund"))
            ->assertOk();

        $this->assertSame(0, (int) $ticketType->fresh()->quantity_sold);

        $this->postWebhook($this->refundWebhook('evt_inv_dup', 'refund.updated', [
            'id' => 'sq_inv',
            'status' => 'COMPLETED',
            'payment_id' => $payment->provider_payment_id,
            'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
        ]))->assertOk();

        $this->assertSame(0, (int) $ticketType->fresh()->quantity_sold);
        $this->assertSame(RegistrationStatus::Refunded, $registration->fresh()->status);
    }

    public function test_partial_refund_does_not_restore_inventory(): void
    {
        [, $ticketType, , , $payment] = $this->paidCheckout();
        $ticketType->quantity_sold = 1;
        $ticketType->save();

        Http::fake([
            '*/v2/refunds' => Http::response($this->squareRefundPayload('sq_invp', $payment, 500, 'COMPLETED'), 200),
        ]);

        $this->actingAsEms($this->admin())
            ->postJson($this->url("payments/{$payment->uuid}/refund"), ['amount' => 5])
            ->assertOk();

        $this->assertSame(1, (int) $ticketType->fresh()->quantity_sold);
    }

    public function test_paid_checkout_creates_one_registration_per_order(): void
    {
        [, , $order, $registration] = $this->paidCheckout();

        $this->assertSame(1, $order->registrations()->count());
        $this->assertSame($order->id, $registration->order_id);
        $this->assertSame(1, $registration->tickets()->count());
    }

    public function test_full_refund_updates_all_registrations_on_the_order(): void
    {
        [, $ticketType, $order, $registration, $payment] = $this->paidCheckout();
        $ticketType->quantity_sold = 2;
        $ticketType->save();

        $sibling = Registration::factory()->create([
            'event_id' => $registration->event_id,
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'status' => RegistrationStatus::Confirmed,
            'type' => 'paid',
            'quantity' => 1,
            'amount_due' => 0,
        ]);
        app(DefaultTicketIssuer::class)->issueFor($sibling);

        Http::fake([
            '*/v2/refunds' => Http::response($this->squareRefundPayload('sq_multi', $payment, 1500, 'COMPLETED'), 200),
        ]);

        $this->actingAsEms($this->admin())
            ->postJson($this->url("payments/{$payment->uuid}/refund"))
            ->assertOk();

        $this->assertSame(RegistrationStatus::Refunded, $registration->fresh()->status);
        $this->assertSame(RegistrationStatus::Refunded, $sibling->fresh()->status);
        $this->assertSame(TicketStatus::Revoked, $registration->tickets()->first()->status);
        $this->assertSame(TicketStatus::Revoked, $sibling->tickets()->first()->status);
        $this->assertSame(0, (int) $ticketType->fresh()->quantity_sold);
    }

    public function test_unauthenticated_user_cannot_refund(): void
    {
        [, , , , $payment] = $this->paidCheckout();

        $this->postJson($this->url("payments/{$payment->uuid}/refund"))
            ->assertUnauthorized();
    }

    public function test_attendee_cannot_refund(): void
    {
        [, , , , $payment] = $this->paidCheckout();

        $this->actingAsEms($this->emsUser(EmsRoles::ATTENDEE))
            ->postJson($this->url("payments/{$payment->uuid}/refund"))
            ->assertForbidden();
    }

    public function test_event_staff_cannot_refund(): void
    {
        [$event, , , , $payment] = $this->paidCheckout();
        $staff = $this->emsUser(EmsRoles::EVENT_STAFF);
        EventStaff::query()->create([
            'event_id' => $event->id,
            'user_id' => $staff->id,
        ]);

        $this->actingAsEms($staff)
            ->postJson($this->url("payments/{$payment->uuid}/refund"))
            ->assertForbidden();
    }

    public function test_organizer_can_refund_in_scope_event(): void
    {
        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        [, , , , $payment] = $this->paidCheckout($organizer);

        Http::fake([
            '*/v2/refunds' => Http::response($this->squareRefundPayload('sq_org', $payment, 1500, 'PENDING'), 200),
        ]);

        $this->actingAsEms($organizer)
            ->postJson($this->url("payments/{$payment->uuid}/refund"))
            ->assertOk();
    }

    public function test_organizer_cannot_refund_out_of_scope_payment(): void
    {
        $organizer = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $this->paidCheckout($organizer);
        [, , , , $theirs] = $this->paidCheckout($this->emsUser(EmsRoles::EVENT_ORGANIZER));

        $this->actingAsEms($organizer)
            ->postJson($this->url("payments/{$theirs->uuid}/refund"))
            ->assertForbidden();
    }

    public function test_pending_to_completed_webhook_revokes_ticket(): void
    {
        [$event, , , $registration, $payment] = $this->paidCheckout();

        Http::fake([
            '*/v2/refunds' => Http::response($this->squareRefundPayload('sq_pc', $payment, 1500, 'PENDING'), 200),
        ]);

        $this->actingAsEms($this->admin())
            ->postJson($this->url("payments/{$payment->uuid}/refund"))
            ->assertOk();

        $this->assertSame(TicketStatus::Issued, $registration->tickets()->first()->status);

        $this->postWebhook($this->refundWebhook('evt_pc', 'refund.updated', [
            'id' => 'sq_pc',
            'status' => 'COMPLETED',
            'payment_id' => $payment->provider_payment_id,
            'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
        ]))->assertOk();

        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertEquals(15.0, (float) $payment->fresh()->amount_refunded);
        $this->assertSame(TicketStatus::Revoked, $registration->tickets()->first()->status);
        $this->assertCheckInDenied($event->fresh(), $registration->tickets()->first()->code);
    }

    public function test_pending_to_failed_webhook_leaves_ticket_valid(): void
    {
        [$event, , , $registration, $payment] = $this->paidCheckout();

        Http::fake([
            '*/v2/refunds' => Http::response($this->squareRefundPayload('sq_pf', $payment, 1500, 'PENDING'), 200),
        ]);

        $this->actingAsEms($this->admin())
            ->postJson($this->url("payments/{$payment->uuid}/refund"))
            ->assertOk();

        $this->postWebhook($this->refundWebhook('evt_pf', 'refund.updated', [
            'id' => 'sq_pf',
            'status' => 'FAILED',
            'payment_id' => $payment->provider_payment_id,
            'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
        ]))->assertOk();

        $this->assertSame(SquareRefundStatus::Failed, SquareRefund::query()->first()->status);
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(TicketStatus::Issued, $registration->tickets()->first()->status);
        $this->assertCheckInAllowed($event->fresh(), $registration->tickets()->first()->code);
    }

    private function admin()
    {
        return $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
    }

    private function openEvent(array $attributes = []): Event
    {
        $category = $this->category(['is_active' => true]);

        return Event::factory()->publiclyDiscoverable()->create(array_merge([
            'category_id' => $category->id,
            'capacity' => 100,
            'status' => EventStatus::RegistrationOpen,
        ], $attributes));
    }

    /**
     * @return array{0: Event, 1: TicketType, 2: Order, 3: Registration, 4: Payment}
     */
    private function paidCheckout($organizer = null): array
    {
        $event = $organizer
            ? $this->openEvent(['organizer_id' => $organizer->id, 'created_by' => $organizer->id])
            : $this->openEvent();
        $ticketType = TicketType::factory()->paid(15)->create(['event_id' => $event->id]);
        $order = Order::factory()->create([
            'event_id' => $event->id,
            'reference' => 'ORD-RFND' . $event->id . '-' . $ticketType->id,
            'total_amount' => 15,
            'status' => OrderStatus::Pending,
        ]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'status' => RegistrationStatus::AwaitingPayment,
            'type' => 'paid',
            'quantity' => 1,
            'amount_due' => 15,
        ]);
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'registration_id' => $registration->id,
            'amount' => 15,
            'currency' => 'CAD',
            'provider' => 'square',
            'status' => PaymentStatus::Processing->value,
            'provider_order_id' => 'sq_order_' . $order->id,
        ]);

        $this->postWebhook([
            'event_id' => 'evt_paid_' . $payment->id,
            'type' => 'payment.updated',
            'data' => ['object' => ['payment' => [
                'id' => 'sq_pay_' . $payment->id,
                'status' => 'COMPLETED',
                'order_id' => $payment->provider_order_id,
                'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                'reference_id' => $order->reference,
            ]]],
        ])->assertOk();

        return [$event, $ticketType->fresh(), $order->fresh(), $registration->fresh(['tickets', 'ticketType']), $payment->fresh()];
    }

    /**
     * @param  array<string, mixed>  $refund
     * @return array<string, mixed>
     */
    private function refundWebhook(string $eventId, string $type, array $refund): array
    {
        return [
            'event_id' => $eventId,
            'type' => $type,
            'data' => ['object' => ['refund' => $refund]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function squareRefundPayload(string $id, Payment $payment, int $cents, string $status): array
    {
        return [
            'refund' => [
                'id' => $id,
                'status' => $status,
                'payment_id' => $payment->provider_payment_id,
                'amount_money' => ['amount' => $cents, 'currency' => 'CAD'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $url = 'https://example.test/api/v1/webhooks/square';
        $signature = base64_encode(hash_hmac('sha256', $url . $body, 'webhook-secret', true));

        return $this->call(
            'POST',
            '/api/v1/webhooks/square',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_SQUARE_HMACSHA256_SIGNATURE' => $signature,
            ],
            $body
        );
    }

    private function assertCheckInAllowed(Event $event, string $code): void
    {
        $event->status = EventStatus::Live;
        $event->save();

        $result = app(CheckInService::class)->checkInByCode($event, $code, $this->admin());
        $this->assertNotNull($result['check_in']);
    }

    private function assertCheckInDenied(Event $event, string $code): void
    {
        $event->status = EventStatus::Live;
        $event->save();

        $this->expectException(\App\Ems\Exceptions\CheckInException::class);
        $this->expectExceptionMessage('Ticket refunded.');
        app(CheckInService::class)->checkInByCode($event, $code, $this->admin());
    }
}
