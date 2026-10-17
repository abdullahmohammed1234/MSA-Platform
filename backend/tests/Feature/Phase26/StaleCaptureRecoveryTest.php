<?php

namespace Tests\Feature\Phase26;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\SquareRefundStatus;
use App\Ems\Models\Event;
use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\SquareRefund;
use App\Ems\Models\TicketType;
use App\Ems\Services\EmsActivityLogger;
use App\Ems\Services\Square\SquareRefundService;
use App\Ems\Support\EmsRoles;
use App\Models\AuditLog;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Ems\EmsTestCase;

/**
 * Refund lifecycle visibility for held (stale / capacity-conflict) captures.
 * Square is faked; no refund is ever issued.
 */
class StaleCaptureRecoveryTest extends EmsTestCase
{
    private const SQ_PAYMENT = 'sq_held_pay_1';

    /** @var list<array{status: int, body: array<string, mixed>}> */
    private array $refundResponses = [];

    /** @var list<string> */
    private array $idempotencyKeys = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ems.payments.enabled' => true,
            'ems.payments.square.access_token' => 'test-token',
            'ems.payments.square.location_id' => 'LOCATION_TEST',
            'ems.payments.square.environment' => 'sandbox',
            'queue.default' => 'sync',
        ]);

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/v2/payments/'.self::SQ_PAYMENT)) {
                return Http::response(['payment' => [
                    'id' => self::SQ_PAYMENT,
                    'status' => 'COMPLETED',
                    'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                ]], 200);
            }
            if ($request->method() === 'POST' && str_contains($request->url(), '/v2/refunds')) {
                $this->idempotencyKeys[] = (string) ($request->data()['idempotency_key'] ?? '');
                $next = array_shift($this->refundResponses) ?? ['status' => 500, 'body' => []];

                return Http::response($next['body'], $next['status']);
            }

            return Http::response(['errors' => [['code' => 'NOT_FAKED']]], 404);
        });
    }

    private function heldCapture(string $reason = 'capacity_unavailable'): Payment
    {
        $category = $this->category(['is_active' => true]);
        $event = Event::factory()->publiclyDiscoverable()->create(['category_id' => $category->id, 'capacity' => 1, 'status' => EventStatus::RegistrationOpen]);
        $ticketType = TicketType::factory()->paid(15)->create(['event_id' => $event->id]);
        $order = Order::factory()->create(['event_id' => $event->id, 'total_amount' => 15, 'status' => OrderStatus::Cancelled, 'buyer_email' => 'held@example.com']);
        $registration = Registration::factory()->create([
            'event_id' => $event->id, 'order_id' => $order->id, 'ticket_type_id' => $ticketType->id,
            'status' => RegistrationStatus::Cancelled, 'type' => 'paid', 'quantity' => 1, 'amount_due' => 15,
        ]);

        return Payment::query()->create([
            'order_id' => $order->id,
            'registration_id' => $registration->id,
            'amount' => 15,
            'currency' => 'CAD',
            'provider' => 'square',
            'status' => PaymentStatus::Cancelled->value,
            'provider_payment_id' => self::SQ_PAYMENT,
            'provider_order_id' => 'sq_held_order_1',
            'metadata' => [
                'capacity_conflict_at' => now()->toIso8601String(),
                'stale_captures_after_buyer_cancel' => [[
                    'square_payment_id' => self::SQ_PAYMENT,
                    'square_order_id' => 'sq_held_order_1',
                    'reported_at' => now()->toIso8601String(),
                    'source' => 'payment_confirmation',
                    'reason' => $reason,
                ]],
            ],
        ]);
    }

    private function squareRefund(string $status): array
    {
        return ['status' => 200, 'body' => ['refund' => [
            'id' => 'sq_ref_1', 'status' => $status, 'payment_id' => self::SQ_PAYMENT,
            'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
        ]]];
    }

    private function show(Payment $payment): \Illuminate\Testing\TestResponse
    {
        return $this->actingAsEms($this->emsUser(EmsRoles::EVENT_ADMINISTRATOR))
            ->getJson($this->url("stale-captures/{$payment->uuid}/".self::SQ_PAYMENT))
            ->assertOk();
    }

    private function requestRefund(Payment $payment): \Illuminate\Testing\TestResponse
    {
        return $this->actingAsEms($this->emsUser(EmsRoles::EVENT_ADMINISTRATOR))
            ->postJson($this->url("stale-captures/{$payment->uuid}/".self::SQ_PAYMENT.'/refund'), ['reason' => 'Event sold out before capture.']);
    }

    public function test_capacity_conflict_is_visible_with_no_refund_and_buyer_follow_up_pending(): void
    {
        $payment = $this->heldCapture();

        $this->show($payment)
            ->assertJsonPath('data.conflict_reason', 'capacity_unavailable')
            ->assertJsonPath('data.refund_state', 'none')
            ->assertJsonPath('data.latest_refund', null)
            ->assertJsonPath('data.buyer_notification_required', true)
            ->assertJsonPath('data.resolution_status', 'unresolved');
        $this->assertNotNull($this->show($payment)->json('data.capacity_conflict_at'));
    }

    public function test_uncertain_submission_stays_requested_and_retry_reuses_the_same_idempotency_key(): void
    {
        $payment = $this->heldCapture();

        $this->refundResponses = [['status' => 503, 'body' => []]];
        $this->requestRefund($payment)->assertStatus(502);
        $this->show($payment)->assertJsonPath('data.refund_state', 'requested')
            ->assertJsonPath('data.latest_refund.acknowledged_by_square', false)
            ->assertJsonPath('data.resolution_status', 'unresolved');

        $this->refundResponses = [$this->squareRefund('PENDING')];
        $this->requestRefund($payment)->assertOk();

        $this->assertSame(1, SquareRefund::query()->count(), 'interrupted request was resumed, not duplicated');
        $this->assertCount(2, $this->idempotencyKeys);
        $this->assertSame($this->idempotencyKeys[0], $this->idempotencyKeys[1]);
        $this->show($payment)->assertJsonPath('data.refund_state', 'processing')
            ->assertJsonPath('data.resolution_status', 'unresolved');
    }

    public function test_refund_is_not_complete_until_square_confirms_it(): void
    {
        $payment = $this->heldCapture();
        $this->refundResponses = [$this->squareRefund('PENDING')];
        $this->requestRefund($payment)->assertOk();

        $this->requestRefund($payment)->assertStatus(409);
        $this->assertSame(1, SquareRefund::query()->count());
        $this->assertSame('unresolved', $payment->fresh()->staleCaptureResolutionStatus($payment->fresh()->findStaleCaptureEntry(self::SQ_PAYMENT)));

        app(SquareRefundService::class)->applyFromWebhook([
            'id' => 'sq_ref_1', 'status' => 'COMPLETED', 'payment_id' => self::SQ_PAYMENT,
            'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
        ]);

        $this->show($payment)->assertJsonPath('data.refund_state', 'confirmed')
            ->assertJsonPath('data.resolution_status', 'refunded')
            ->assertJsonPath('data.latest_refund.status', 'completed')
            ->assertJsonPath('data.buyer_notification_required', true);
        $this->assertSame(PaymentStatus::Cancelled, $payment->fresh()->status);
    }

    public function test_rejected_refund_is_shown_as_failed_and_can_be_retried_once(): void
    {
        $payment = $this->heldCapture();
        $this->refundResponses = [$this->squareRefund('PENDING')];
        $this->requestRefund($payment)->assertOk();

        app(SquareRefundService::class)->applyFromWebhook([
            'id' => 'sq_ref_1', 'status' => 'REJECTED', 'payment_id' => self::SQ_PAYMENT,
            'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
        ]);
        $this->show($payment)->assertJsonPath('data.refund_state', 'failed')
            ->assertJsonPath('data.resolution_status', 'unresolved');

        $this->refundResponses = [['status' => 200, 'body' => ['refund' => [
            'id' => 'sq_ref_2', 'status' => 'PENDING', 'payment_id' => self::SQ_PAYMENT,
            'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
        ]]]];
        $this->requestRefund($payment)->assertOk();

        $this->assertSame(2, SquareRefund::query()->count());
        $this->assertSame(1, SquareRefund::query()->where('status', SquareRefundStatus::Pending->value)->count());
        $this->assertNotSame($this->idempotencyKeys[0], $this->idempotencyKeys[1]);
        $this->show($payment)->assertJsonPath('data.refund_state', 'processing');
    }

    public function test_buyer_notification_is_recorded_once_with_authorization_and_audit(): void
    {
        $payment = $this->heldCapture();
        $url = $this->url("stale-captures/{$payment->uuid}/".self::SQ_PAYMENT.'/buyer-notified');

        $this->actingAsEms($this->emsUser(EmsRoles::ATTENDEE))->postJson($url)->assertForbidden();

        $admin = $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
        $first = $this->actingAsEms($admin)->postJson($url, ['note' => 'Phoned buyer'])->assertOk()
            ->assertJsonPath('data.buyer_notification_required', false)
            ->assertJsonPath('data.buyer_notified_by_user_id', $admin->id);

        $this->travel(1)->hours();
        $this->actingAsEms($admin)->postJson($url)->assertOk()
            ->assertJsonPath('data.buyer_notified_at', $first->json('data.buyer_notified_at'));

        $this->assertSame(1, AuditLog::query()->where('action', EmsActivityLogger::PREFIX.'stale_capture.buyer_notified')->count());
    }
}
