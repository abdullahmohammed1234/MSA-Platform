<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\SquareRefundStatus;
use App\Ems\Enums\TicketStatus;
use App\Ems\Models\Event;
use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\SquareRefund;
use App\Ems\Models\Ticket;
use App\Ems\Models\TicketType;
use App\Ems\Services\EmsActivityLogger;
use App\Ems\Services\Square\SquareRefundService;
use App\Ems\Support\EmsRoles;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Http;

class EmsStaleCaptureOperationsTest extends EmsTestCase
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
            'ems.tickets.enabled' => true,
            'queue.default' => 'sync',
        ]);
    }

    public function test_authorized_admin_can_list_stale_captures(): void
    {
        [$event, , , , $payment] = $this->staleCaptureScenario();

        $response = $this->actingAsEms($this->admin())
            ->getJson($this->url('stale-captures'))
            ->assertOk();

        $this->assertSuccessEnvelope($response);
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('data.0.payment_uuid', $payment->uuid);
        $response->assertJsonPath('data.0.event_uuid', $event->uuid);
        $response->assertJsonPath('data.0.resolution_status', 'unresolved');
    }

    public function test_unauthorized_attendee_cannot_list_stale_captures(): void
    {
        $this->staleCaptureScenario();

        $this->actingAsEms($this->emsUser(EmsRoles::ATTENDEE))
            ->getJson($this->url('stale-captures'))
            ->assertForbidden();
    }

    public function test_event_scoped_authorization_prevents_access_to_another_event(): void
    {
        [$eventA, , , , $paymentA] = $this->staleCaptureScenario();
        $organizerB = $this->organizerFor($this->openEvent(['slug' => 'other-event-' . $eventA->id]));

        $this->actingAsEms($organizerB)
            ->getJson($this->url('stale-captures'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAsEms($organizerB)
            ->getJson($this->url("stale-captures/{$paymentA->uuid}/sq_stale_pay_1"))
            ->assertForbidden();
    }

    public function test_stale_capture_detail_returns_structured_data(): void
    {
        [$event, , $order, $registration, $payment] = $this->staleCaptureScenario();

        $response = $this->actingAsEms($this->admin())
            ->getJson($this->url("stale-captures/{$payment->uuid}/sq_stale_pay_1"))
            ->assertOk();

        $this->assertSuccessEnvelope($response);
        $response->assertJsonPath('data.payment_uuid', $payment->uuid);
        $response->assertJsonPath('data.order_reference', $order->reference);
        $response->assertJsonPath('data.registration_uuid', $registration->uuid);
        $response->assertJsonPath('data.event_uuid', $event->uuid);
        $response->assertJsonPath('data.square_payment_id', 'sq_stale_pay_1');
        $response->assertJsonPath('data.resolution_status', 'unresolved');
        $response->assertJsonMissingPath('data.metadata');
    }

    public function test_soft_deleted_event_does_not_cause_failure(): void
    {
        [$event, , , , $payment] = $this->staleCaptureScenario();
        $event->delete();

        $this->actingAsEms($this->admin())
            ->getJson($this->url("stale-captures/{$payment->uuid}/sq_stale_pay_1"))
            ->assertOk()
            ->assertJsonPath('data.event_missing', true)
            ->assertJsonPath('data.event_name', null);
    }

    public function test_resolve_without_refund_requires_a_reason(): void
    {
        [, , , , $payment] = $this->staleCaptureScenario();

        $this->actingAsEms($this->admin())
            ->postJson($this->url("stale-captures/{$payment->uuid}/sq_stale_pay_1/resolve"), [])
            ->assertStatus(422);

        $this->actingAsEms($this->admin())
            ->postJson($this->url("stale-captures/{$payment->uuid}/sq_stale_pay_1/resolve"), ['reason' => 'ab'])
            ->assertStatus(422);
    }

    public function test_resolve_without_refund_records_resolution_state(): void
    {
        $admin = $this->admin();
        [, , , , $payment] = $this->staleCaptureScenario();

        $this->actingAsEms($admin)
            ->postJson($this->url("stale-captures/{$payment->uuid}/sq_stale_pay_1/resolve"), [
                'reason' => 'Chargeback handled externally.',
            ])
            ->assertOk()
            ->assertJsonPath('data.resolution_status', 'resolved_no_refund')
            ->assertJsonPath('data.resolution_reason', 'Chargeback handled externally.');

        $entry = $payment->fresh()->findStaleCaptureEntry('sq_stale_pay_1');
        $this->assertSame('resolved_no_refund', data_get($entry, 'resolution.status'));
        $this->assertSame($admin->id, data_get($entry, 'resolution.resolved_by_user_id'));

        $this->assertTrue(
            AuditLog::query()->where('action', EmsActivityLogger::PREFIX . 'stale_capture.resolved_no_refund')->exists()
        );
    }

    public function test_resolve_without_refund_is_idempotent_for_same_status(): void
    {
        $admin = $this->admin();
        [, , , , $payment] = $this->staleCaptureScenario();

        $url = $this->url("stale-captures/{$payment->uuid}/sq_stale_pay_1/resolve");
        $auditAction = EmsActivityLogger::PREFIX . 'stale_capture.resolved_no_refund';

        $this->assertSame(0, AuditLog::query()->where('action', $auditAction)->count());

        $this->actingAsEms($admin)
            ->postJson($url, ['reason' => 'Duplicate resolution attempt.'])
            ->assertOk();

        $this->assertSame(1, AuditLog::query()->where('action', $auditAction)->count());

        $this->actingAsEms($admin)
            ->postJson($url, ['reason' => 'Duplicate resolution attempt.'])
            ->assertOk()
            ->assertJsonPath('data.resolution_status', 'resolved_no_refund');

        $this->assertSame(1, AuditLog::query()->where('action', $auditAction)->count());

        $fresh = $payment->fresh();
        $entry = $fresh->findStaleCaptureEntry('sq_stale_pay_1');
        $this->assertSame('resolved_no_refund', $fresh->staleCaptureResolutionStatus($entry ?? []));
        $this->assertNotEmpty($fresh->metadata['buyer_cancelled_at'] ?? null);
        $this->assertCount(1, $fresh->metadata['stale_captures_after_buyer_cancel'] ?? []);
    }

    public function test_refund_uses_stale_capture_square_payment_id(): void
    {
        [, , , , $payment] = $this->staleCaptureScenario([
            'provider_payment_id' => 'sq_wrong_provider_id',
        ]);

        $usedPaymentId = null;

        Http::fake(function (\Illuminate\Http\Client\Request $request) use (&$usedPaymentId) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/v2/payments/sq_stale_pay_1')) {
                return Http::response([
                    'payment' => [
                        'id' => 'sq_stale_pay_1',
                        'status' => 'COMPLETED',
                        'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                    ],
                ], 200);
            }

            if ($request->method() === 'POST' && str_contains($request->url(), '/v2/refunds')) {
                $usedPaymentId = $request->data()['payment_id'] ?? null;

                return Http::response([
                    'refund' => [
                        'id' => 'sq_ref_stale',
                        'status' => 'COMPLETED',
                        'payment_id' => 'sq_stale_pay_1',
                        'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $this->actingAsEms($this->admin())
            ->postJson($this->url("stale-captures/{$payment->uuid}/sq_stale_pay_1/refund"), [
                'reason' => 'Admin refund after buyer cancel.',
            ])
            ->assertOk();

        $this->assertSame('sq_stale_pay_1', $usedPaymentId);
        $this->assertNotSame('sq_wrong_provider_id', $usedPaymentId);
    }

    public function test_successful_stale_refund_keeps_payment_and_registration_cancelled(): void
    {
        [, , , $registration, $payment] = $this->staleCaptureScenario();

        $this->fakeStaleRefundHttp('sq_stale_pay_1', 1500, 'COMPLETED');

        $this->actingAsEms($this->admin())
            ->postJson($this->url("stale-captures/{$payment->uuid}/sq_stale_pay_1/refund"), [
                'reason' => 'Refund stale capture.',
            ])
            ->assertOk();

        $this->assertSame(PaymentStatus::Cancelled, $payment->fresh()->status);
        $this->assertSame(RegistrationStatus::Cancelled, $registration->fresh()->status);
    }

    public function test_successful_stale_refund_does_not_create_or_revoke_tickets(): void
    {
        [, , , , $payment] = $this->staleCaptureScenario();

        $this->assertSame(0, Ticket::query()->count());

        $this->fakeStaleRefundHttp('sq_stale_pay_1', 1500, 'COMPLETED');

        $this->actingAsEms($this->admin())
            ->postJson($this->url("stale-captures/{$payment->uuid}/sq_stale_pay_1/refund"), [
                'reason' => 'Refund stale capture.',
            ])
            ->assertOk();

        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_successful_stale_refund_does_not_alter_inventory(): void
    {
        [, $ticketType, , , $payment] = $this->staleCaptureScenario();

        $this->assertSame(0, $ticketType->fresh()->quantity_sold);

        $this->fakeStaleRefundHttp('sq_stale_pay_1', 1500, 'COMPLETED');

        $this->actingAsEms($this->admin())
            ->postJson($this->url("stale-captures/{$payment->uuid}/sq_stale_pay_1/refund"), [
                'reason' => 'Refund stale capture.',
            ])
            ->assertOk();

        $this->assertSame(0, $ticketType->fresh()->quantity_sold);
    }

    public function test_successful_stale_refund_records_refund_uuid_and_amount(): void
    {
        [, , , , $payment] = $this->staleCaptureScenario();

        $this->fakeStaleRefundHttp('sq_stale_pay_1', 1500, 'COMPLETED');

        $this->actingAsEms($this->admin())
            ->postJson($this->url("stale-captures/{$payment->uuid}/sq_stale_pay_1/refund"), [
                'reason' => 'Refund stale capture.',
            ])
            ->assertOk()
            ->assertJsonPath('data.stale_capture.resolution_status', 'refunded')
            ->assertJsonPath('data.stale_capture.amount_refunded', 15);

        $entry = $payment->fresh()->findStaleCaptureEntry('sq_stale_pay_1');
        $this->assertSame('refunded', data_get($entry, 'resolution.status'));
        $this->assertNotEmpty(data_get($entry, 'resolution.square_refund_uuid'));
        $this->assertSame('15.00', data_get($entry, 'resolution.amount_refunded'));
    }

    public function test_duplicate_refund_attempt_is_rejected_when_fully_refunded(): void
    {
        [, , , , $payment] = $this->staleCaptureScenario();

        $this->fakeStaleRefundHttp('sq_stale_pay_1', 1500, 'COMPLETED');

        $url = $this->url("stale-captures/{$payment->uuid}/sq_stale_pay_1/refund");

        $this->actingAsEms($this->admin())
            ->postJson($url, ['reason' => 'First refund.'])
            ->assertOk();

        $this->actingAsEms($this->admin())
            ->postJson($url, ['reason' => 'Second refund.'])
            ->assertStatus(409);
    }

    public function test_already_resolved_no_refund_cannot_be_refunded(): void
    {
        [, , , , $payment] = $this->staleCaptureScenario();

        $this->actingAsEms($this->admin())
            ->postJson($this->url("stale-captures/{$payment->uuid}/sq_stale_pay_1/resolve"), [
                'reason' => 'Handled offline.',
            ])
            ->assertOk();

        $this->fakeStaleRefundHttp('sq_stale_pay_1', 1500, 'COMPLETED');

        $this->actingAsEms($this->admin())
            ->postJson($this->url("stale-captures/{$payment->uuid}/sq_stale_pay_1/refund"), [
                'reason' => 'Attempt after resolve.',
            ])
            ->assertStatus(409);
    }

    public function test_square_payment_that_is_not_captured_is_rejected(): void
    {
        [, , , , $payment] = $this->staleCaptureScenario();

        Http::fake([
            '*/v2/payments/sq_stale_pay_1' => Http::response([
                'payment' => [
                    'id' => 'sq_stale_pay_1',
                    'status' => 'FAILED',
                    'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                ],
            ], 200),
        ]);

        $this->actingAsEms($this->admin())
            ->postJson($this->url("stale-captures/{$payment->uuid}/sq_stale_pay_1/refund"), [
                'reason' => 'Should fail.',
            ])
            ->assertStatus(409);

        $entry = $payment->fresh()->findStaleCaptureEntry('sq_stale_pay_1');
        $this->assertSame('unresolved', $payment->staleCaptureResolutionStatus($entry ?? []));
    }

    public function test_refund_amount_cannot_exceed_captured_amount(): void
    {
        [, , , , $payment] = $this->staleCaptureScenario();

        Http::fake([
            '*/v2/payments/sq_stale_pay_1' => Http::response([
                'payment' => [
                    'id' => 'sq_stale_pay_1',
                    'status' => 'COMPLETED',
                    'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                ],
            ], 200),
        ]);

        $this->actingAsEms($this->admin())
            ->postJson($this->url("stale-captures/{$payment->uuid}/sq_stale_pay_1/refund"), [
                'amount' => 20,
                'reason' => 'Too much.',
            ])
            ->assertStatus(422);
    }

    public function test_refund_amount_cannot_exceed_ems_sanity_limit(): void
    {
        [, , , , $payment] = $this->staleCaptureScenario(['amount' => 10]);

        Http::fake([
            '*/v2/payments/sq_stale_pay_1' => Http::response([
                'payment' => [
                    'id' => 'sq_stale_pay_1',
                    'status' => 'COMPLETED',
                    'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                ],
            ], 200),
        ]);

        $this->actingAsEms($this->admin())
            ->postJson($this->url("stale-captures/{$payment->uuid}/sq_stale_pay_1/refund"), [
                'amount' => 12,
                'reason' => 'Above EMS checkout amount.',
            ])
            ->assertStatus(422);
    }

    public function test_refund_failure_does_not_mark_stale_capture_refunded(): void
    {
        [, , , $registration, $payment] = $this->staleCaptureScenario();

        Http::fake([
            '*/v2/payments/sq_stale_pay_1' => Http::response([
                'payment' => [
                    'id' => 'sq_stale_pay_1',
                    'status' => 'COMPLETED',
                    'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                ],
            ], 200),
            '*/v2/refunds' => Http::response(['errors' => [['detail' => 'declined']]], 422),
        ]);

        $this->actingAsEms($this->admin())
            ->postJson($this->url("stale-captures/{$payment->uuid}/sq_stale_pay_1/refund"), [
                'reason' => 'Should fail at Square.',
            ])
            ->assertStatus(422);

        $payment = $payment->fresh();
        $entry = $payment->findStaleCaptureEntry('sq_stale_pay_1');
        $this->assertSame('unresolved', $payment->staleCaptureResolutionStatus($entry ?? []));
        $this->assertSame(PaymentStatus::Cancelled, $payment->status);
        $this->assertSame(RegistrationStatus::Cancelled, $registration->fresh()->status);
        $this->assertSame(0, Ticket::query()->count());

        $refund = SquareRefund::query()->first();
        $this->assertNotNull($refund);
        $this->assertSame(SquareRefundStatus::Failed, $refund->status);
        $this->assertTrue((bool) data_get($refund->metadata, 'stale_capture', false));

        $this->assertTrue(
            AuditLog::query()->where('action', EmsActivityLogger::PREFIX . 'stale_capture.refund_failed')->exists()
        );
        $this->assertSame(
            1,
            AuditLog::query()->where('action', EmsActivityLogger::PREFIX . 'stale_capture.refund_failed')->count()
        );
    }

    public function test_normal_fulfill_completed_cannot_mutate_cancelled_payment(): void
    {
        $event = $this->openEvent();
        $ticketType = TicketType::factory()->paid(15)->create([
            'event_id' => $event->id,
            'quantity_sold' => 0,
        ]);
        $order = Order::factory()->create([
            'event_id' => $event->id,
            'reference' => 'ORD-CANCELLED-REFUND-' . $event->id,
            'total_amount' => 15,
            'status' => OrderStatus::Cancelled,
        ]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'status' => RegistrationStatus::Cancelled,
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
            'status' => PaymentStatus::Cancelled->value,
            'provider_payment_id' => 'sq_cancelled_with_provider',
            'metadata' => ['inventory_released' => true],
        ]);

        $refund = SquareRefund::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'payment_id' => $payment->id,
            'order_id' => $order->id,
            'registration_id' => $registration->id,
            'idempotency_key' => 'ems-rfnd-cancelled-guard',
            'amount' => '15.00',
            'currency' => 'CAD',
            'status' => SquareRefundStatus::Pending->value,
            'reason' => 'Misapplied normal refund completion.',
        ]);

        app(SquareRefundService::class)->applySquareRefund($refund, [
            'id' => 'sq_misapplied_refund',
            'status' => 'COMPLETED',
            'payment_id' => 'sq_cancelled_with_provider',
            'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
        ]);

        $this->assertSame(PaymentStatus::Cancelled, $payment->fresh()->status);
        $this->assertSame(RegistrationStatus::Cancelled, $registration->fresh()->status);
        $this->assertSame(0, Ticket::query()->count());
        $this->assertSame(0, $ticketType->fresh()->quantity_sold);
    }

    public function test_refund_webhook_resolves_stale_capture_without_provider_payment_id(): void
    {
        [, , , , $payment] = $this->staleCaptureScenario(['provider_payment_id' => null]);

        Http::fake([
            '*/v2/payments/sq_stale_pay_1' => Http::response([
                'payment' => [
                    'id' => 'sq_stale_pay_1',
                    'status' => 'COMPLETED',
                    'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                ],
            ], 200),
            '*/v2/refunds' => Http::response([
                'refund' => [
                    'id' => 'sq_ref_pending',
                    'status' => 'PENDING',
                    'payment_id' => 'sq_stale_pay_1',
                    'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                ],
            ], 200),
        ]);

        $this->actingAsEms($this->admin())
            ->postJson($this->url("stale-captures/{$payment->uuid}/sq_stale_pay_1/refund"), [
                'reason' => 'Pending webhook completion.',
            ])
            ->assertOk();

        $this->assertSame(PaymentStatus::Cancelled, $payment->fresh()->status);
        $entry = $payment->fresh()->findStaleCaptureEntry('sq_stale_pay_1');
        $this->assertSame('unresolved', $payment->staleCaptureResolutionStatus($entry ?? []));

        $this->postWebhook([
            'event_id' => 'evt_stale_refund_done',
            'type' => 'refund.updated',
            'data' => ['object' => ['refund' => [
                'id' => 'sq_ref_pending',
                'status' => 'COMPLETED',
                'payment_id' => 'sq_stale_pay_1',
                'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
            ]]],
        ])->assertOk();

        $payment = $payment->fresh();
        $entry = $payment->findStaleCaptureEntry('sq_stale_pay_1');
        $this->assertSame('refunded', $payment->staleCaptureResolutionStatus($entry ?? []));
        $this->assertSame(PaymentStatus::Cancelled, $payment->status);
        $this->assertSame(RegistrationStatus::Cancelled, $payment->registration?->fresh()->status);
    }

    public function test_normal_paid_payment_refund_still_works(): void
    {
        [, , , $registration, $payment] = $this->paidCheckout();

        Http::fake([
            '*/v2/refunds' => Http::response([
                'refund' => [
                    'id' => 'sq_normal_ref',
                    'status' => 'COMPLETED',
                    'payment_id' => $payment->provider_payment_id,
                    'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                ],
            ], 200),
        ]);

        $this->actingAsEms($this->admin())
            ->postJson($this->url("payments/{$payment->uuid}/refund"), ['amount' => 15])
            ->assertOk();

        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertSame(TicketStatus::Revoked, $registration->tickets()->first()->fresh()->status);
    }

    /**
     * @param  array<string, mixed>  $paymentOverrides
     * @return array{0: Event, 1: TicketType, 2: Order, 3: Registration, 4: Payment}
     */
    private function staleCaptureScenario(array $paymentOverrides = [], ?Event $event = null): array
    {
        $event = $event ?? $this->openEvent();
        $ticketType = TicketType::factory()->paid(15)->create([
            'event_id' => $event->id,
            'quantity_sold' => 0,
        ]);
        $order = Order::factory()->create([
            'event_id' => $event->id,
            'reference' => 'ORD-STALE-' . $event->id,
            'buyer_name' => 'Sara Ahmed',
            'buyer_email' => 'sara-stale@example.com',
            'total_amount' => 15,
            'status' => OrderStatus::Cancelled,
        ]);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'status' => RegistrationStatus::Cancelled,
            'attendee_name' => 'Sara Ahmed',
            'attendee_email' => 'sara-stale@example.com',
            'type' => 'paid',
            'quantity' => 1,
            'amount_due' => 15,
        ]);

        $defaults = [
            'order_id' => $order->id,
            'registration_id' => $registration->id,
            'amount' => 15,
            'currency' => 'CAD',
            'provider' => 'square',
            'status' => PaymentStatus::Cancelled->value,
            'provider_payment_id' => null,
            'provider_order_id' => 'sq_stale_order_1',
            'metadata' => [
                'buyer_cancelled_at' => now()->subMinutes(5)->toIso8601String(),
                'inventory_released' => true,
                'stale_captures_after_buyer_cancel' => [[
                    'square_payment_id' => 'sq_stale_pay_1',
                    'square_order_id' => 'sq_stale_order_1',
                    'reported_at' => now()->toIso8601String(),
                    'webhook_event_id' => 'evt_stale_test',
                    'source' => 'webhook',
                ]],
            ],
        ];

        $payment = Payment::query()->create(array_merge($defaults, $paymentOverrides));

        return [$event, $ticketType->fresh(), $order->fresh(), $registration->fresh(), $payment->fresh()];
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

    private function organizerFor(Event $event)
    {
        $user = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $event->update(['organizer_id' => $user->id, 'created_by' => $user->id]);

        return $user;
    }

    private function admin()
    {
        return $this->emsUser(EmsRoles::EVENT_ADMINISTRATOR);
    }

    /**
     * @return array{0: Event, 1: TicketType, 2: Order, 3: Registration, 4: Payment}
     */
    private function paidCheckout(): array
    {
        $event = $this->openEvent();
        $ticketType = TicketType::factory()->paid(15)->create(['event_id' => $event->id]);
        $order = Order::factory()->create([
            'event_id' => $event->id,
            'reference' => 'ORD-NORMAL-' . $event->id,
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

        $squarePaymentId = 'sq_pay_' . $payment->id;

        $this->postWebhook([
            'event_id' => 'evt_paid_' . $payment->id,
            'type' => 'payment.updated',
            'data' => ['object' => ['payment' => [
                'id' => $squarePaymentId,
                'status' => 'COMPLETED',
                'order_id' => $payment->provider_order_id,
                'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                'reference_id' => $order->reference,
            ]]],
        ])->assertOk();

        return [$event, $ticketType->fresh(), $order->fresh(), $registration->fresh(['tickets', 'ticketType']), $payment->fresh()];
    }

    private function fakeStaleRefundHttp(string $squarePaymentId, int $cents, string $status): void
    {
        Http::fake([
            '*/v2/payments/' . $squarePaymentId => Http::response([
                'payment' => [
                    'id' => $squarePaymentId,
                    'status' => 'COMPLETED',
                    'amount_money' => ['amount' => $cents, 'currency' => 'CAD'],
                ],
            ], 200),
            '*/v2/refunds' => Http::response([
                'refund' => [
                    'id' => 'sq_ref_' . $squarePaymentId,
                    'status' => $status,
                    'payment_id' => $squarePaymentId,
                    'amount_money' => ['amount' => $cents, 'currency' => 'CAD'],
                ],
            ], 200),
        ]);
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
}
