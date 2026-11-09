<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\PaymentProvider as PaymentProviderEnum;
use App\Ems\Enums\PaymentSourceChannel;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Exceptions\EmsException;
use App\Ems\Models\Event;
use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use App\Ems\Models\TicketType;
use App\Ems\Services\CheckoutService;
use App\Ems\Services\Payments\Providers\SquarePaymentProvider;
use App\Ems\Services\Square\SquareClient;
use App\Ems\Services\Square\SquarePosIngestService;
use App\Ems\Services\Square\SquareReconciliationService;
use App\Ems\Services\Square\SquareTerminalService;
use App\Ems\Services\SquareWebhookService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EmsSquareProductionSignOffTest extends EmsTestCase
{
    private function publishedEvent(): Event
    {
        return Event::factory()->create([
            'status' => EventStatus::RegistrationOpen,
            'is_public' => true,
            'start_at' => now()->addDays(2),
            'end_at' => now()->addDays(2)->addHours(3),
            'capacity' => 100,
        ]);
    }

    private function paidTicketType(Event $event, float $price = 25.0): TicketType
    {
        return TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'General Admission',
            'price' => $price,
            'currency' => 'CAD',
            'quantity' => 50,
            'quantity_sold' => 0,
        ]);
    }

    private function createOrderAndPayment(Event $event, TicketType $type, string $channel = 'terminal', float $amount = 25.0): array
    {
        $order = Order::factory()->create([
            'event_id' => $event->id,
            'reference' => 'TEST-ORD-' . uniqid(),
            'status' => OrderStatus::Pending,
            'total_amount' => $amount,
            'currency' => 'CAD',
            'source_channel' => $channel,
        ]);

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'order_id' => $order->id,
            'ticket_type_id' => $type->id,
            'quantity' => 1,
            'status' => RegistrationStatus::AwaitingPayment,
            'attendee_email' => 'attendee@example.com',
            'amount_due' => $amount,
            'currency' => 'CAD',
        ]);

        $type->quantity_sold = 1;
        $type->save();

        $payment = Payment::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'registration_id' => $registration->id,
            'amount' => $amount,
            'currency' => 'CAD',
            'status' => PaymentStatus::Pending,
            'provider' => PaymentProviderEnum::Square->value,
            'source_channel' => $channel,
            'terminal_checkout_id' => $channel === 'terminal' ? 'term_chk_' . uniqid() : null,
        ]);

        return [$order, $registration, $payment];
    }

    // =========================================================================
    // Phase 1 — Square-disabled fallback: fail-closed verification
    // =========================================================================

    public function test_square_disabled_in_production_fails_closed(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->assertTrue($this->app->isProduction());

        config([
            'ems.payments.enabled' => false,
            'ems.payments.square.access_token' => 'prod-token',
            'ems.payments.square.location_id' => 'LOC_PROD',
            'ems.payments.square.application_id' => 'sq0idp-prod',
        ]);

        $event = $this->publishedEvent();
        $type = $this->paidTicketType($event);
        [$order, $registration, $payment] = $this->createOrderAndPayment($event, $type);

        $terminalService = app(SquareTerminalService::class);
        $result = $terminalService->handleCheckoutUpdated([
            'id' => $payment->terminal_checkout_id,
            'status' => 'COMPLETED',
            'payment_ids' => ['sq_pay_prod_001'],
        ]);

        // Must fail closed: returns null, payment remains Pending, 0 tickets issued
        $this->assertNull($result);
        $payment->refresh();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertNull($payment->provider_payment_id);
        $this->assertTrue($payment->metadata['reconciliation_review_required'] ?? false);
        $this->assertStringContainsString('production', (string) ($payment->metadata['review_reason'] ?? ''));

        $this->assertSame(0, Ticket::query()->where('registration_id', $registration->id)->count());
        $order->refresh();
        $this->assertSame(OrderStatus::Pending, $order->status);
    }

    public function test_missing_square_credentials_in_production_fails_closed(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        // Enabled, but access_token and location_id missing
        config([
            'ems.payments.enabled' => true,
            'ems.payments.square.access_token' => '',
            'ems.payments.square.location_id' => '',
            'ems.payments.square.application_id' => 'sq0idp-prod',
        ]);

        $event = $this->publishedEvent();
        $type = $this->paidTicketType($event);
        [$order, $registration, $payment] = $this->createOrderAndPayment($event, $type);

        $terminalService = app(SquareTerminalService::class);
        $result = $terminalService->handleCheckoutUpdated([
            'id' => $payment->terminal_checkout_id,
            'status' => 'COMPLETED',
            'payment_ids' => ['sq_pay_prod_002'],
        ]);

        $this->assertNull($result);
        $payment->refresh();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertTrue($payment->metadata['reconciliation_review_required'] ?? false);
        $this->assertSame(0, Ticket::query()->where('registration_id', $registration->id)->count());
    }

    public function test_missing_application_id_in_production_fails_closed(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        // Token and location present, but application_id missing
        config([
            'ems.payments.enabled' => true,
            'ems.payments.square.access_token' => 'prod-token',
            'ems.payments.square.location_id' => 'LOC_PROD',
            'ems.payments.square.application_id' => '',
        ]);

        /** @var SquareClient $client */
        $client = app(SquareClient::class);
        $this->assertFalse($client->enabled(), 'SquareClient must not be enabled in production without application_id');

        $event = $this->publishedEvent();
        $type = $this->paidTicketType($event);
        [$order, $registration, $payment] = $this->createOrderAndPayment($event, $type);

        $terminalService = app(SquareTerminalService::class);
        $result = $terminalService->handleCheckoutUpdated([
            'id' => $payment->terminal_checkout_id,
            'status' => 'COMPLETED',
            'payment_ids' => ['sq_pay_prod_003'],
        ]);

        $this->assertNull($result);
        $payment->refresh();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertTrue($payment->metadata['reconciliation_review_required'] ?? false);
        $this->assertSame(0, Ticket::query()->where('registration_id', $registration->id)->count());
    }

    public function test_square_disabled_in_testing_fails_closed_by_default(): void
    {
        // Testing environment without explicit allow_test_simulation
        config([
            'ems.payments.enabled' => false,
            'ems.payments.allow_test_simulation' => false,
        ]);

        $event = $this->publishedEvent();
        $type = $this->paidTicketType($event);
        [$order, $registration, $payment] = $this->createOrderAndPayment($event, $type);

        $terminalService = app(SquareTerminalService::class);
        $result = $terminalService->handleCheckoutUpdated([
            'id' => $payment->terminal_checkout_id,
            'status' => 'COMPLETED',
            'payment_ids' => ['sq_pay_test_001'],
        ]);

        $this->assertNull($result);
        $payment->refresh();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(0, Ticket::query()->where('registration_id', $registration->id)->count());
    }

    public function test_explicitly_configured_local_test_simulation_succeeds_outside_production(): void
    {
        $this->assertFalse($this->app->isProduction());

        // Explicit test simulation flag enabled in testing
        config([
            'ems.payments.enabled' => false,
            'ems.payments.allow_test_simulation' => true,
        ]);

        $event = $this->publishedEvent();
        $type = $this->paidTicketType($event);
        [$order, $registration, $payment] = $this->createOrderAndPayment($event, $type);

        $terminalService = app(SquareTerminalService::class);
        $result = $terminalService->handleCheckoutUpdated([
            'id' => $payment->terminal_checkout_id,
            'status' => 'COMPLETED',
            'payment_ids' => ['sq_pay_simulated_999'],
        ]);

        $this->assertNotNull($result);
        $this->assertSame(PaymentStatus::Paid, $result->status);
        $this->assertTrue($result->metadata['test_simulation'] ?? false);
        $this->assertSame(1, Ticket::query()->where('registration_id', $registration->id)->count());
    }

    public function test_production_configuration_accidentally_enabling_test_fallback_still_fails_closed(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->assertTrue($this->app->isProduction());

        // Accidental test fallback in production config
        config([
            'ems.payments.enabled' => false,
            'ems.payments.allow_test_simulation' => true,
        ]);

        $event = $this->publishedEvent();
        $type = $this->paidTicketType($event);
        [$order, $registration, $payment] = $this->createOrderAndPayment($event, $type);

        $terminalService = app(SquareTerminalService::class);
        $result = $terminalService->handleCheckoutUpdated([
            'id' => $payment->terminal_checkout_id,
            'status' => 'COMPLETED',
            'payment_ids' => ['sq_pay_accidental_001'],
        ]);

        // In production, allow_test_simulation is strictly ignored: fails closed!
        $this->assertNull($result);
        $payment->refresh();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(0, Ticket::query()->where('registration_id', $registration->id)->count());
    }

    public function test_checkout_service_fails_closed_when_square_disabled_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config([
            'ems.payments.enabled' => false,
        ]);

        $event = $this->publishedEvent();
        $type = $this->paidTicketType($event, 20.0);

        $checkoutService = app(CheckoutService::class);

        $this->expectException(EmsException::class);
        $this->expectExceptionCode(Response::HTTP_SERVICE_UNAVAILABLE);

        $checkoutService->checkout($event, [
            'ticket_type_id' => $type->uuid,
            'quantity' => 1,
            'first_name' => 'Failsafe',
            'last_name' => 'User',
            'email' => 'failsafe@example.com',
        ]);
    }

    // =========================================================================
    // Phase 2 — Authorization expiration and capture failure
    // =========================================================================

    public function test_terminal_approved_never_fulfills_and_retains_authorized_state(): void
    {
        config([
            'ems.payments.enabled' => true,
            'ems.payments.square.access_token' => 'token-test',
            'ems.payments.square.location_id' => 'LOC_APPROVED',
            'ems.payments.square.application_id' => 'app-test',
        ]);

        $event = $this->publishedEvent();
        $type = $this->paidTicketType($event, 30.0);
        [$order, $registration, $payment] = $this->createOrderAndPayment($event, $type, 'terminal', 30.0);

        $mockSquare = $this->createMock(SquareClient::class);
        $mockSquare->method('enabled')->willReturn(true);
        $mockSquare->method('locationId')->willReturn('LOC_APPROVED');
        $mockSquare->method('get')->willReturnCallback(function ($path) use ($order) {
            if (str_contains($path, '/v2/payments/sq_pay_appr_001')) {
                return [
                    'payment' => [
                        'id' => 'sq_pay_appr_001',
                        'status' => 'APPROVED',
                        'amount_money' => ['amount' => 3000, 'currency' => 'CAD'],
                        'location_id' => 'LOC_APPROVED',
                        'reference_id' => $order->reference,
                    ],
                ];
            }
            return [];
        });
        $this->app->instance(SquareClient::class, $mockSquare);

        $terminalService = app(SquareTerminalService::class);
        $result = $terminalService->handleCheckoutUpdated([
            'id' => $payment->terminal_checkout_id,
            'status' => 'COMPLETED',
            'payment_ids' => ['sq_pay_appr_001'],
        ]);

        $this->assertNotNull($result);
        $payment->refresh();
        $this->assertSame(PaymentStatus::Authorized, $payment->status);
        $this->assertSame('APPROVED', $payment->metadata['square_payment_status']);
        $this->assertSame('sq_pay_appr_001', $payment->provider_payment_id);

        // Crucial invariant: 0 tickets issued, order remains Pending, inventory held
        $this->assertSame(0, Ticket::query()->where('registration_id', $registration->id)->count());
        $order->refresh();
        $this->assertSame(OrderStatus::Pending, $order->status);
        $type->refresh();
        $this->assertSame(1, $type->quantity_sold, 'Reservation inventory must remain held');
    }

    public function test_reconciliation_distinguishes_payment_lifecycle_states(): void
    {
        config([
            'ems.payments.enabled' => true,
            'ems.payments.square.access_token' => 'token-test',
            'ems.payments.square.location_id' => 'LOC_RECON',
            'ems.payments.square.application_id' => 'app-test',
        ]);

        $event = $this->publishedEvent();
        $type = $this->paidTicketType($event, 25.0);

        // Case A: Authorized payment settles to COMPLETED on Square
        [$orderA, $regA, $payA] = $this->createOrderAndPayment($event, $type, 'online', 25.0);
        $payA->status = PaymentStatus::Authorized;
        $payA->provider_payment_id = 'sq_pay_settled_123';
        $payA->created_at = now()->subMinutes(10);
        $payA->save();

        // Case B: Authorized payment voided/canceled on Square
        [$orderB, $regB, $payB] = $this->createOrderAndPayment($event, $type, 'online', 25.0);
        $payB->status = PaymentStatus::Authorized;
        $payB->provider_payment_id = 'sq_pay_voided_456';
        $payB->created_at = now()->subMinutes(10);
        $payB->save();

        // Case C: Authorized payment failed on Square
        [$orderC, $regC, $payC] = $this->createOrderAndPayment($event, $type, 'online', 25.0);
        $payC->status = PaymentStatus::Authorized;
        $payC->provider_payment_id = 'sq_pay_failed_789';
        $payC->created_at = now()->subMinutes(10);
        $payC->save();

        // Case D: Authorized payment still APPROVED on Square
        [$orderD, $regD, $payD] = $this->createOrderAndPayment($event, $type, 'online', 25.0);
        $payD->status = PaymentStatus::Authorized;
        $payD->provider_payment_id = 'sq_pay_still_appr_101';
        $payD->created_at = now()->subMinutes(10);
        $payD->save();

        $mockSquare = $this->createMock(SquareClient::class);
        $mockSquare->method('enabled')->willReturn(true);
        $mockSquare->method('locationId')->willReturn('LOC_RECON');
        $mockSquare->method('get')->willReturnCallback(function ($path) {
            if (str_contains($path, 'sq_pay_settled_123')) {
                return ['payment' => ['id' => 'sq_pay_settled_123', 'status' => 'COMPLETED', 'amount_money' => ['amount' => 2500, 'currency' => 'CAD']]];
            }
            if (str_contains($path, 'sq_pay_voided_456')) {
                return ['payment' => ['id' => 'sq_pay_voided_456', 'status' => 'CANCELED', 'amount_money' => ['amount' => 2500, 'currency' => 'CAD']]];
            }
            if (str_contains($path, 'sq_pay_failed_789')) {
                return ['payment' => ['id' => 'sq_pay_failed_789', 'status' => 'FAILED', 'amount_money' => ['amount' => 2500, 'currency' => 'CAD']]];
            }
            if (str_contains($path, 'sq_pay_still_appr_101')) {
                return ['payment' => ['id' => 'sq_pay_still_appr_101', 'status' => 'APPROVED', 'amount_money' => ['amount' => 2500, 'currency' => 'CAD']]];
            }
            return [];
        });
        $this->app->instance(SquareClient::class, $mockSquare);

        $reconcileService = app(SquareReconciliationService::class);
        $outcomes = $reconcileService->reconcileAmbiguousPayments(0);

        // Verify Case A (COMPLETED -> Paid, exactly 1 ticket)
        $payA->refresh();
        $this->assertSame(PaymentStatus::Paid, $payA->status);
        $this->assertSame(1, Ticket::query()->where('registration_id', $regA->id)->count());

        // Verify Case B (CANCELED -> Cancelled, inventory released, 0 tickets)
        $payB->refresh();
        $this->assertSame(PaymentStatus::Cancelled, $payB->status);
        $this->assertSame(0, Ticket::query()->where('registration_id', $regB->id)->count());

        // Verify Case C (FAILED -> Cancelled, inventory released, 0 tickets)
        $payC->refresh();
        $this->assertSame(PaymentStatus::Cancelled, $payC->status);
        $this->assertSame(0, Ticket::query()->where('registration_id', $regC->id)->count());

        // Verify Case D (APPROVED -> remains Authorized, reservation retained, 0 tickets)
        $payD->refresh();
        $this->assertSame(PaymentStatus::Authorized, $payD->status);
        $this->assertSame(0, Ticket::query()->where('registration_id', $regD->id)->count());

        $this->assertSame(1, $outcomes['reconciled_paid']);
        $this->assertSame(2, $outcomes['inventory_released']);
        $this->assertSame(1, $outcomes['uncertain_retained']);
    }

    public function test_authorized_payment_beyond_threshold_becomes_visible_to_admins(): void
    {
        config([
            'ems.payments.enabled' => true,
            'ems.payments.square.access_token' => 'token-test',
            'ems.payments.square.location_id' => 'LOC_VISIBILITY',
            'ems.payments.square.application_id' => 'app-test',
            'ems.payments.authorized_review_threshold_hours' => 24,
        ]);

        $event = $this->publishedEvent();
        $type = $this->paidTicketType($event, 25.0);
        [$order, $registration, $payment] = $this->createOrderAndPayment($event, $type, 'terminal', 25.0);

        // Created 30 hours ago, past 24-hour threshold
        $payment->status = PaymentStatus::Authorized;
        $payment->provider_payment_id = 'sq_pay_stale_appr_888';
        $payment->created_at = now()->subHours(30);
        $payment->save();

        $mockSquare = $this->createMock(SquareClient::class);
        $mockSquare->method('enabled')->willReturn(true);
        $mockSquare->method('locationId')->willReturn('LOC_VISIBILITY');
        $mockSquare->method('get')->willReturn(['payment' => [
            'id' => 'sq_pay_stale_appr_888',
            'status' => 'APPROVED',
            'amount_money' => ['amount' => 2500, 'currency' => 'CAD'],
        ]]);
        $this->app->instance(SquareClient::class, $mockSquare);

        $reconcileService = app(SquareReconciliationService::class);
        $reconcileService->reconcileAmbiguousPayments();

        $payment->refresh();
        $this->assertTrue($payment->metadata['reconciliation_review_required'] ?? false);
        $this->assertStringContainsString('24 hours', (string) ($payment->metadata['reconciliation_note'] ?? ''));

        // Admin collection must include this payment
        $unresolved = $reconcileService->getUnresolvedAmbiguousPayments();
        $this->assertTrue($unresolved->contains('id', $payment->id));
    }

    public function test_delayed_completed_webhook_on_cancelled_order_records_stale_capture_without_resurrecting(): void
    {
        $event = $this->publishedEvent();
        $type = $this->paidTicketType($event, 50.0);
        [$order, $registration, $payment] = $this->createOrderAndPayment($event, $type, 'terminal', 50.0);

        // Legitimate prior cancellation
        $payment->status = PaymentStatus::Cancelled;
        $payment->failure_reason = 'Buyer canceled checkout before completion';
        $payment->metadata = ['buyer_cancelled_at' => now()->subMinutes(10)->toIso8601String()];
        $payment->save();

        $order->status = OrderStatus::Cancelled;
        $order->save();
        $registration->status = RegistrationStatus::Cancelled;
        $registration->save();

        $terminalService = app(SquareTerminalService::class);
        $result = $terminalService->handleCheckoutUpdated([
            'id' => $payment->terminal_checkout_id,
            'status' => 'COMPLETED',
            'payment_ids' => ['sq_delayed_charge_999'],
            'order_id' => 'sq_delayed_order_999',
        ]);

        $payment->refresh();
        $order->refresh();
        $registration->refresh();

        // Invariants: Order remains Cancelled, 0 tickets, stale capture queued for admin refund
        $this->assertSame(PaymentStatus::Cancelled, $payment->status);
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(RegistrationStatus::Cancelled, $registration->status);
        $this->assertSame(0, Ticket::query()->where('registration_id', $registration->id)->count());

        $staleCaptures = $payment->metadata['stale_captures_after_buyer_cancel'] ?? [];
        $this->assertNotEmpty($staleCaptures);
        $this->assertSame('sq_delayed_charge_999', $staleCaptures[0]['square_payment_id']);
    }

    // =========================================================================
    // Phase 3 — POS versus Terminal isolation
    // =========================================================================

    public function test_pos_approved_settles_only_for_pos_channel(): void
    {
        $this->assertTrue(PaymentSourceChannel::Pos->treatsApprovedAsSettled());
        $this->assertFalse(PaymentSourceChannel::Terminal->treatsApprovedAsSettled());
        $this->assertFalse(PaymentSourceChannel::Online->treatsApprovedAsSettled());
        $this->assertFalse(PaymentSourceChannel::SquareOnlineStore->treatsApprovedAsSettled());
        $this->assertFalse(PaymentSourceChannel::Other->treatsApprovedAsSettled());
    }

    public function test_existing_terminal_order_cannot_be_settled_on_approved_via_pos_ingest(): void
    {
        $event = $this->publishedEvent();
        $type = $this->paidTicketType($event, 40.0);
        [$order, $registration, $payment] = $this->createOrderAndPayment($event, $type, 'terminal', 40.0);

        $posService = app(SquarePosIngestService::class);

        // Reflection to call private fulfillExistingIfSettled
        $refMethod = new \ReflectionMethod(SquarePosIngestService::class, 'fulfillExistingIfSettled');
        $refMethod->setAccessible(true);

        $squarePaymentPayload = [
            'id' => 'sq_pos_spoofed_001',
            'status' => 'APPROVED',
            'amount_money' => ['amount' => 4000, 'currency' => 'CAD'],
            'source_type' => 'CARD',
        ];

        // Attempt to pass channel Pos with APPROVED status on an existing Terminal payment
        $outcome = $refMethod->invoke(
            $posService,
            $payment,
            $squarePaymentPayload,
            PaymentSourceChannel::Pos,
            'APPROVED'
        );

        $payment->refresh();
        $this->assertNotSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(0, Ticket::query()->where('registration_id', $registration->id)->count());
    }

    public function test_unknown_or_missing_square_product_defaults_to_other_and_rejects_approved(): void
    {
        $paymentDataMissingProduct = [
            'id' => 'sq_unk_001',
            'status' => 'APPROVED',
        ];

        $channel = PaymentSourceChannel::fromSquarePayment($paymentDataMissingProduct);
        $this->assertSame(PaymentSourceChannel::Other, $channel);
        $this->assertFalse($channel->treatsApprovedAsSettled());

        $paymentDataTerminalApi = [
            'id' => 'sq_term_001',
            'status' => 'APPROVED',
            'application_details' => ['square_product' => 'TERMINAL_API'],
        ];

        $channelTerm = PaymentSourceChannel::fromSquarePayment($paymentDataTerminalApi);
        $this->assertSame(PaymentSourceChannel::Terminal, $channelTerm);
        $this->assertFalse($channelTerm->treatsApprovedAsSettled());
    }

    // =========================================================================
    // Phase 4 — End-to-end payment lifecycle verification & Adversarial Scenarios
    // =========================================================================

    public function test_webhook_invalid_signature_rejected_with_401(): void
    {
        config([
            'ems.payments.enabled' => true,
            'ems.payments.square.webhook_signature_key' => 'valid-secret',
        ]);

        $webhookService = app(SquareWebhookService::class);

        $this->expectException(EmsException::class);
        $this->expectExceptionCode(Response::HTTP_UNAUTHORIZED);

        $webhookService->handle(
            '{"event_id":"evt_1","type":"payment.updated"}',
            'invalid-signature',
            'https://example.test/api/v1/webhooks/square'
        );
    }

    public function test_webhook_payments_disabled_fails_closed_with_503(): void
    {
        config([
            'ems.payments.enabled' => false,
            'ems.payments.square.webhook_signature_key' => 'valid-secret',
        ]);

        $webhookService = app(SquareWebhookService::class);

        $this->expectException(EmsException::class);
        $this->expectExceptionCode(Response::HTTP_SERVICE_UNAVAILABLE);

        $webhookService->handle(
            '{"event_id":"evt_1","type":"payment.updated"}',
            'any-sig',
            'https://example.test/api/v1/webhooks/square'
        );
    }

    public function test_duplicate_webhook_is_strictly_idempotent(): void
    {
        config([
            'ems.payments.enabled' => true,
            'ems.payments.square.access_token' => 'token-test',
            'ems.payments.square.location_id' => 'LOC_IDEMPOTENT',
            'ems.payments.square.application_id' => 'app-test',
        ]);

        $event = $this->publishedEvent();
        $type = $this->paidTicketType($event, 20.0);
        [$order, $registration, $payment] = $this->createOrderAndPayment($event, $type, 'terminal', 20.0);

        $mockSquare = $this->createMock(SquareClient::class);
        $mockSquare->method('enabled')->willReturn(true);
        $mockSquare->method('locationId')->willReturn('LOC_IDEMPOTENT');
        $mockSquare->method('get')->willReturn(['payment' => [
            'id' => 'sq_pay_idem_001',
            'status' => 'COMPLETED',
            'amount_money' => ['amount' => 2000, 'currency' => 'CAD'],
            'location_id' => 'LOC_IDEMPOTENT',
            'reference_id' => $order->reference,
        ]]);
        $this->app->instance(SquareClient::class, $mockSquare);

        $terminalService = app(SquareTerminalService::class);

        // First delivery: fulfills
        $first = $terminalService->handleCheckoutUpdated([
            'id' => $payment->terminal_checkout_id,
            'status' => 'COMPLETED',
            'payment_ids' => ['sq_pay_idem_001'],
        ]);
        $this->assertNotNull($first);
        $this->assertSame(1, Ticket::query()->where('registration_id', $registration->id)->count());

        // Replay delivery: idempotent, exactly 1 ticket persists
        $second = $terminalService->handleCheckoutUpdated([
            'id' => $payment->terminal_checkout_id,
            'status' => 'COMPLETED',
            'payment_ids' => ['sq_pay_idem_001'],
        ]);
        $this->assertNotNull($second);
        $this->assertSame(1, Ticket::query()->where('registration_id', $registration->id)->count());
    }

    public function test_delayed_approved_webhook_cannot_downgrade_already_paid_payment(): void
    {
        $event = $this->publishedEvent();
        $type = $this->paidTicketType($event, 20.0);
        [$order, $registration, $payment] = $this->createOrderAndPayment($event, $type, 'terminal', 20.0);

        $payment->status = PaymentStatus::Paid;
        $payment->provider_payment_id = 'sq_already_paid_999';
        $payment->save();

        $terminalService = app(SquareTerminalService::class);
        $res = $terminalService->handleCheckoutUpdated([
            'id' => $payment->terminal_checkout_id,
            'status' => 'COMPLETED',
            'payment_ids' => ['sq_late_approved_111'],
        ]);

        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame('sq_already_paid_999', $payment->provider_payment_id);
    }
}
