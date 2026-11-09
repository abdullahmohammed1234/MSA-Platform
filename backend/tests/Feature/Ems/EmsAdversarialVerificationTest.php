<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\NotificationStatus;
use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\PaymentSourceChannel;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\TicketStatus;
use App\Ems\Exceptions\CheckInException;
use App\Ems\Exceptions\EmsException;
use App\Ems\Mail\RegistrationEmailFailedAlertMail;
use App\Ems\Models\CheckIn;
use App\Ems\Models\Event;
use App\Ems\Models\EventNotification;
use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use App\Ems\Models\TicketType;
use App\Ems\Services\CheckoutService;
use App\Ems\Services\Notifications\NotificationFailureAlertService;
use App\Ems\Services\Operations\CheckInService;
use App\Ems\Services\Square\SquareTerminalService;
use App\Ems\Support\EmsRoles;
use App\Ems\Jobs\SendEventNotificationJob;
use App\Ems\Services\Square\SquareClient;
use App\Ems\Services\Square\SquareReconciliationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;

class EmsAdversarialVerificationTest extends EmsTestCase
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

    private function ticketType(Event $event, float $price = 10.0, int $capacity = 50): TicketType
    {
        return TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'General Admission',
            'price' => $price,
            'quantity' => $capacity,
            'quantity_sold' => 0,
            'is_active' => true,
            'currency' => 'CAD',
        ]);
    }

    private function createPayment(Order $order, Registration $registration, array $attributes = []): Payment
    {
        return Payment::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'registration_id' => $registration->id,
            'amount' => 25.0,
            'currency' => 'CAD',
            'status' => PaymentStatus::Pending,
            'provider' => 'square',
            'metadata' => [],
        ], $attributes));
    }

    /**
     * Priority 1: Exhaustive verification of Square error classification.
     */
    public function test_square_error_classification_exhaustive(): void
    {
        /** @var CheckoutService $checkoutService */
        $checkoutService = app(CheckoutService::class);

        // 1. HTTP 400 with definitive validation code -> definitive rejection
        $e1 = new EmsException('Invalid value provided', [], 400, 'INVALID_VALUE');
        $this->assertTrue($checkoutService->isDefinitiveProviderRejection($e1), 'HTTP 400 with INVALID_VALUE must be definitive rejection');

        $e2 = new EmsException('Missing required parameter', [], 400, 'MISSING_REQUIRED_PARAMETER');
        $this->assertTrue($checkoutService->isDefinitiveProviderRejection($e2), 'HTTP 400 with MISSING_REQUIRED_PARAMETER must be definitive rejection');

        // 2. HTTP 400 with IDEMPOTENCY_KEY_REUSED -> ambiguous (must NOT cancel order)
        $e3 = new EmsException('Idempotency key reused', [], 400, 'IDEMPOTENCY_KEY_REUSED');
        $this->assertFalse($checkoutService->isDefinitiveProviderRejection($e3), 'HTTP 400 with IDEMPOTENCY_KEY_REUSED must be ambiguous');

        // 3. HTTP 401 (Unauthorized) -> ambiguous (merchant credentials issue, not customer rejection)
        $e4 = new EmsException('Unauthorized access', [], 401, 'UNAUTHORIZED');
        $this->assertFalse($checkoutService->isDefinitiveProviderRejection($e4), 'HTTP 401 must be ambiguous');

        // 4. HTTP 403 (Forbidden) -> ambiguous
        $e5 = new EmsException('Forbidden', [], 403, 'FORBIDDEN');
        $this->assertFalse($checkoutService->isDefinitiveProviderRejection($e5), 'HTTP 403 must be ambiguous');

        // 5. HTTP 404 (Not Found) -> ambiguous
        $e6 = new EmsException('Resource not found', [], 404, 'NOT_FOUND');
        $this->assertFalse($checkoutService->isDefinitiveProviderRejection($e6), 'HTTP 404 must be ambiguous');

        // 6. HTTP 408 (Request Timeout) -> ambiguous
        $e7 = new EmsException('Request timed out', [], 408, 'REQUEST_TIMEOUT');
        $this->assertFalse($checkoutService->isDefinitiveProviderRejection($e7), 'HTTP 408 must be ambiguous');

        // 7. HTTP 409 (Conflict) -> ambiguous
        $e8 = new EmsException('Conflict', [], 409, 'CONFLICT');
        $this->assertFalse($checkoutService->isDefinitiveProviderRejection($e8), 'HTTP 409 must be ambiguous');

        // 8. HTTP 422 with definitive card rejection -> definitive rejection
        $e9 = new EmsException('Card declined', [], 422, 'CARD_DECLINED');
        $this->assertTrue($checkoutService->isDefinitiveProviderRejection($e9), 'HTTP 422 with CARD_DECLINED must be definitive rejection');

        $e10 = new EmsException('CVV Verification failed', [], 422, 'VERIFY_CVV_FAILURE');
        $this->assertTrue($checkoutService->isDefinitiveProviderRejection($e10), 'HTTP 422 with VERIFY_CVV_FAILURE must be definitive rejection');

        // 9. HTTP 422 with CONFLICT -> ambiguous
        $e11 = new EmsException('Conflict during processing', [], 422, 'CONFLICT');
        $this->assertFalse($checkoutService->isDefinitiveProviderRejection($e11), 'HTTP 422 with CONFLICT must be ambiguous');

        // 10. HTTP 429 (Rate Limited) -> ambiguous
        $e12 = new EmsException('Rate limit exceeded', [], 429, 'RATE_LIMITED');
        $this->assertFalse($checkoutService->isDefinitiveProviderRejection($e12), 'HTTP 429 must be ambiguous');

        // 11. 5xx Server Errors -> ambiguous
        $e13 = new EmsException('Internal server error', [], 500, 'INTERNAL_SERVER_ERROR');
        $this->assertFalse($checkoutService->isDefinitiveProviderRejection($e13), 'HTTP 500 must be ambiguous');

        $e14 = new EmsException('Bad gateway', [], 502, 'GATEWAY_TIMEOUT');
        $this->assertFalse($checkoutService->isDefinitiveProviderRejection($e14), 'HTTP 502 must be ambiguous');

        $e15 = new EmsException('Service unavailable', [], 503, 'SERVICE_UNAVAILABLE');
        $this->assertFalse($checkoutService->isDefinitiveProviderRejection($e15), 'HTTP 503 must be ambiguous');

        $e16 = new EmsException('Gateway timeout', [], 504, 'GATEWAY_TIMEOUT');
        $this->assertFalse($checkoutService->isDefinitiveProviderRejection($e16), 'HTTP 504 must be ambiguous');

        // 12. Network connection drops and unknown exceptions -> ambiguous
        $e17 = new ConnectionException('cURL error 28: Operation timed out after 30000 milliseconds');
        $this->assertFalse($checkoutService->isDefinitiveProviderRejection($e17), 'ConnectionException must be ambiguous');

        $e18 = new \RuntimeException('Unexpected database or network disconnect');
        $this->assertFalse($checkoutService->isDefinitiveProviderRejection($e18), 'RuntimeException must be ambiguous');
    }

    /**
     * Priority 2: Atomic concurrency protection on inventory release.
     */
    public function test_inventory_release_idempotent_concurrency_simulation(): void
    {
        $event = $this->publishedEvent();
        $ticketType = $this->ticketType($event, 15.0, 50);
        $ticketType->quantity_sold = 10;
        $ticketType->save();

        $order = Order::factory()->create([
            'event_id' => $event->id,
            'status' => OrderStatus::Pending,
            'total_amount' => 30.0,
            'currency' => 'CAD',
        ]);

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 2,
            'status' => RegistrationStatus::AwaitingPayment,
        ]);

        $payment = $this->createPayment($order, $registration, [
            'amount' => 30.0,
            'status' => PaymentStatus::Pending,
        ]);

        /** @var CheckoutService $checkoutService */
        $checkoutService = app(CheckoutService::class);

        // Attempt 1: First release call
        $checkoutService->releaseReservationIdempotent(
            $order,
            $payment,
            $registration,
            'Provider card declined'
        );

        $ticketType->refresh();
        $payment->refresh();
        $order->refresh();
        $registration->refresh();

        $this->assertSame(8, (int) $ticketType->quantity_sold, 'Quantity sold should decrement from 10 to 8');
        $this->assertTrue((bool) ($payment->metadata['inventory_released'] ?? false));
        $this->assertSame(RegistrationStatus::Cancelled, $registration->status);
        $this->assertSame(PaymentStatus::Cancelled, $payment->status);
        $this->assertSame(OrderStatus::Cancelled, $order->status);

        // Attempt 2: Duplicate / simultaneous release call on the same records
        $checkoutService->releaseReservationIdempotent(
            $order,
            $payment,
            $registration,
            'Duplicate release call'
        );

        $ticketType->refresh();
        $this->assertSame(8, (int) $ticketType->quantity_sold, 'Second release must be a no-op; capacity must not be decremented again');
    }

    /**
     * Priority 3: Square Terminal webhook verification - amount, currency, and payment_ids.
     */
    public function test_square_terminal_webhook_verifies_amount_and_currency(): void
    {
        $event = $this->publishedEvent();
        $ticketType = $this->ticketType($event, 25.0, 50);

        $order = Order::factory()->create([
            'event_id' => $event->id,
            'reference' => 'TERM-AMT-VERIFY-' . Str::random(6),
            'status' => OrderStatus::Pending,
            'total_amount' => 25.0,
            'currency' => 'CAD',
        ]);

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'status' => RegistrationStatus::AwaitingPayment,
        ]);

        $payment = $this->createPayment($order, $registration, [
            'source_channel' => PaymentSourceChannel::Terminal->value,
            'amount' => 25.0,
            'currency' => 'CAD',
            'status' => PaymentStatus::Pending,
            'terminal_checkout_id' => 'term_checkout_amt_test',
        ]);

        config(['ems.payments.allow_test_simulation' => true]);

        /** @var SquareTerminalService $terminalService */
        $terminalService = app(SquareTerminalService::class);

        // Case A: Mismatched amount ($10 vs expected $25)
        $mismatchedAmountPayload = [
            'id' => 'term_checkout_amt_test',
            'status' => 'COMPLETED',
            'reference_id' => $order->reference,
            'payment_ids' => ['sq_payment_amt_1'],
            'amount_money' => [
                'amount' => 1000, // 10.00 CAD
                'currency' => 'CAD',
            ],
        ];

        $resultA = $terminalService->handleCheckoutUpdated($mismatchedAmountPayload);
        $this->assertNull($resultA, 'Mismatched amount must reject fulfillment and return null');
        $payment->refresh();
        $this->assertSame(PaymentStatus::Pending, $payment->status, 'Payment must remain pending on amount mismatch');

        // Case B: Wrong currency (USD vs expected CAD)
        $wrongCurrencyPayload = [
            'id' => 'term_checkout_amt_test',
            'status' => 'COMPLETED',
            'reference_id' => $order->reference,
            'payment_ids' => ['sq_payment_amt_2'],
            'amount_money' => [
                'amount' => 2500, // 25.00
                'currency' => 'USD', // Wrong currency
            ],
        ];

        $resultB = $terminalService->handleCheckoutUpdated($wrongCurrencyPayload);
        $this->assertNull($resultB, 'Wrong currency must reject fulfillment and return null');
        $payment->refresh();
        $this->assertSame(PaymentStatus::Pending, $payment->status, 'Payment must remain pending on currency mismatch');

        // Case C: Exact amount and currency match
        $matchingPayload = [
            'id' => 'term_checkout_amt_test',
            'status' => 'COMPLETED',
            'reference_id' => $order->reference,
            'payment_ids' => ['sq_payment_amt_valid'],
            'amount_money' => [
                'amount' => 2500, // 25.00 CAD
                'currency' => 'CAD',
            ],
        ];

        $resultC = $terminalService->handleCheckoutUpdated($matchingPayload);
        $this->assertNotNull($resultC);
        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status, 'Matching amount and currency must fulfill payment');
    }

    /**
     * Priority 3: Square Terminal completion without associated payment_ids must not mark paid.
     */
    public function test_square_terminal_webhook_requires_payment_ids(): void
    {
        $event = $this->publishedEvent();
        $ticketType = $this->ticketType($event, 20.0, 50);

        $order = Order::factory()->create([
            'event_id' => $event->id,
            'reference' => 'TERM-NOPAY-' . Str::random(6),
            'status' => OrderStatus::Pending,
            'total_amount' => 20.0,
            'currency' => 'CAD',
        ]);

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'status' => RegistrationStatus::AwaitingPayment,
        ]);

        $payment = $this->createPayment($order, $registration, [
            'source_channel' => PaymentSourceChannel::Terminal->value,
            'amount' => 20.0,
            'currency' => 'CAD',
            'status' => PaymentStatus::Pending,
            'terminal_checkout_id' => 'term_checkout_nopay_test',
        ]);

        /** @var SquareTerminalService $terminalService */
        $terminalService = app(SquareTerminalService::class);

        // Terminal status says COMPLETED but payment_ids is empty
        $emptyPaymentIdsPayload = [
            'id' => 'term_checkout_nopay_test',
            'status' => 'COMPLETED',
            'reference_id' => $order->reference,
            'payment_ids' => [],
            'amount_money' => [
                'amount' => 2000,
                'currency' => 'CAD',
            ],
        ];

        $result = $terminalService->handleCheckoutUpdated($emptyPaymentIdsPayload);
        $this->assertNull($result, 'Terminal completion without payment_ids must not fulfill payment');
        $payment->refresh();
        $this->assertSame(PaymentStatus::Pending, $payment->status, 'Payment must remain pending');
    }

    /**
     * Priority 6: Administrator failure-alert deduplication across multiple attempts and handle/failed races.
     */
    public function test_admin_failure_alert_is_strictly_deduplicated_atomically(): void
    {
        Mail::fake();
        config(['ems.notifications.admin_alert_recipients' => 'admin@sfumsa.ca,operations@sfumsa.ca']);

        $event = $this->publishedEvent();
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'attendee_email' => 'student@sfu.ca',
            'status' => RegistrationStatus::Confirmed,
        ]);

        $notification = EventNotification::query()->create([
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'type' => 'registration_confirmed',
            'recipient_email' => 'student@sfu.ca',
            'status' => NotificationStatus::Pending,
            'alert_sent_at' => null,
            'retry_count' => 0,
        ]);

        /** @var NotificationFailureAlertService $alertService */
        $alertService = app(NotificationFailureAlertService::class);

        // Call sendAlert 3 times in rapid succession (simulating handle() + failed() + worker race)
        $alertService->sendAlert($notification, 'SMTP delivery failed: timeout');
        $alertService->sendAlert($notification, 'Duplicate failed event delivery');
        $alertService->sendAlert($notification, 'Third concurrent delivery attempt');

        $notification->refresh();
        $this->assertNotNull($notification->alert_sent_at, 'alert_sent_at must be populated on first send');

        // Crucial invariant: Exactly 1 email sent, never 3
        Mail::assertSent(RegistrationEmailFailedAlertMail::class, 1);
    }

    /**
     * Priority 7: Concurrent QR check-in row-level locking & duplicate prevention.
     */
    public function test_concurrent_qr_scan_of_same_ticket_prevents_duplicate_check_in(): void
    {
        $event = $this->publishedEvent();
        $staff = $this->emsUser(EmsRoles::EVENT_STAFF);

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $ticket = Ticket::factory()->forRegistration($registration)->create([
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'status' => TicketStatus::Issued,
        ]);

        /** @var CheckInService $checkInService */
        $checkInService = app(CheckInService::class);

        // Staff 1 scans ticket: succeeds
        $result1 = $checkInService->checkInByCode(
            $event,
            $ticket->code,
            $staff
        );

        $this->assertNotNull($result1['check_in']);
        $this->assertSame(TicketStatus::Redeemed, $result1['ticket']->status);

        // Staff 2 scans the exact same ticket concurrently: must throw alreadyCheckedIn
        $this->expectException(CheckInException::class);

        try {
            $checkInService->checkInByCode(
                $event,
                $ticket->code,
                $staff
            );
        } finally {
            // Verify database has exactly 1 check_in record
            $checkInCount = CheckIn::query()->where('ticket_id', $ticket->id)->count();
            $this->assertSame(1, $checkInCount, 'Exactly 1 check_in record must exist for the ticket');
        }
    }

    /**
     * Section 1.1: Two simultaneous alert attempts - atomic claim allows only one.
     */
    public function test_admin_alert_two_simultaneous_attempts_only_one_claims(): void
    {
        $event = $this->publishedEvent();
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
        ]);

        $notification = EventNotification::query()->create([
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'type' => 'registration_confirmed',
            'recipient_email' => 'student@sfu.ca',
            'status' => NotificationStatus::Pending,
            'alert_sent_at' => null,
            'alert_claimed_at' => null,
            'retry_count' => 0,
        ]);

        /** @var NotificationFailureAlertService $alertService */
        $alertService = app(NotificationFailureAlertService::class);

        // Worker A claims: succeeds
        $claimedA = $alertService->claimAlert($notification);
        $this->assertTrue($claimedA, 'Worker A should successfully claim the alert');

        // Worker B attempts to claim simultaneously: fails because Worker A holds lease
        $claimedB = $alertService->claimAlert($notification);
        $this->assertFalse($claimedB, 'Worker B must be rejected while Worker A holds active lease');

        $notification->refresh();
        $this->assertNotNull($notification->alert_claimed_at);
        $this->assertNull($notification->alert_sent_at);
    }

    /**
     * Section 1.2: Successful email dispatch sets alert_sent_at and clears claim.
     */
    public function test_admin_alert_successful_dispatch_sets_sent_at_and_clears_claim(): void
    {
        Mail::fake();
        config(['ems.notifications.admin_alert_recipients' => 'admin@sfumsa.ca']);

        $event = $this->publishedEvent();
        $registration = Registration::factory()->create(['event_id' => $event->id, 'status' => RegistrationStatus::Confirmed]);

        $notification = EventNotification::query()->create([
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'type' => 'registration_confirmed',
            'recipient_email' => 'student@sfu.ca',
            'status' => NotificationStatus::Pending,
            'alert_sent_at' => null,
            'alert_claimed_at' => null,
        ]);

        /** @var NotificationFailureAlertService $alertService */
        $alertService = app(NotificationFailureAlertService::class);

        $sent = $alertService->sendAlert($notification, 'Original failure reason');
        $this->assertTrue($sent);

        $notification->refresh();
        $this->assertNotNull($notification->alert_sent_at);
        Mail::assertSent(RegistrationEmailFailedAlertMail::class, 1);
    }

    /**
     * Section 1.3: Definite mail transport failure immediately releases claim for retry.
     */
    public function test_admin_alert_definite_transport_failure_releases_claim_for_retry(): void
    {
        config(['ems.notifications.admin_alert_recipients' => 'admin@sfumsa.ca']);

        $event = $this->publishedEvent();
        $registration = Registration::factory()->create(['event_id' => $event->id, 'status' => RegistrationStatus::Confirmed]);

        $notification = EventNotification::query()->create([
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'type' => 'registration_confirmed',
            'recipient_email' => 'student@sfu.ca',
            'status' => NotificationStatus::Pending,
            'alert_sent_at' => null,
            'alert_claimed_at' => null,
        ]);

        /** @var NotificationFailureAlertService $alertService */
        $alertService = app(NotificationFailureAlertService::class);

        Mail::shouldReceive('to->send')
            ->once()
            ->andThrow(new TransportException('Connection refused by remote host'));

        $sent = $alertService->sendAlert($notification, 'Definite failure');
        $this->assertFalse($sent);

        $notification->refresh();
        $this->assertNull($notification->alert_sent_at);
        $this->assertNull($notification->alert_claimed_at, 'Claim must be released on definite transport failure');

        // Can immediately be re-claimed by another attempt
        $reclaimed = $alertService->claimAlert($notification);
        $this->assertTrue($reclaimed, 'Should be immediately re-claimable after definite transport failure');
    }

    /**
     * Section 1.4: Process termination after claiming is recovered after lease expiration.
     */
    public function test_admin_alert_process_termination_lease_expiration_recovery(): void
    {
        Mail::fake();
        config(['ems.notifications.admin_alert_recipients' => 'admin@sfumsa.ca']);

        $event = $this->publishedEvent();
        $registration = Registration::factory()->create(['event_id' => $event->id, 'status' => RegistrationStatus::Confirmed]);

        // Simulating a crashed process that claimed the alert 10 minutes ago
        $notification = EventNotification::query()->create([
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'type' => 'registration_confirmed',
            'recipient_email' => 'student@sfu.ca',
            'status' => NotificationStatus::Pending,
            'alert_sent_at' => null,
            'alert_claimed_at' => now()->subMinutes(10),
        ]);

        /** @var NotificationFailureAlertService $alertService */
        $alertService = app(NotificationFailureAlertService::class);

        // Recovery worker arrives after lease expiration (5 mins default): successfully claims and sends
        $recovered = $alertService->sendAlert($notification, 'Crash recovery send');
        $this->assertTrue($recovered);

        $notification->refresh();
        $this->assertNotNull($notification->alert_sent_at);
        Mail::assertSent(RegistrationEmailFailedAlertMail::class, 1);
    }

    /**
     * Section 1.5: Uncertain transport outcome (timeout) retains claim until lease expiry.
     */
    public function test_admin_alert_uncertain_transport_outcome_retains_claim_until_lease_expiry(): void
    {
        config(['ems.notifications.admin_alert_recipients' => 'admin@sfumsa.ca']);

        $event = $this->publishedEvent();
        $registration = Registration::factory()->create(['event_id' => $event->id, 'status' => RegistrationStatus::Confirmed]);

        $notification = EventNotification::query()->create([
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'type' => 'registration_confirmed',
            'recipient_email' => 'student@sfu.ca',
            'status' => NotificationStatus::Pending,
            'alert_sent_at' => null,
            'alert_claimed_at' => null,
        ]);

        /** @var NotificationFailureAlertService $alertService */
        $alertService = app(NotificationFailureAlertService::class);

        // Throw an uncertain transport outcome (timed out waiting for server)
        Mail::shouldReceive('to->send')
            ->once()
            ->andThrow(new TransportException('Connection timed out after sending DATA command'));

        $sent = $alertService->sendAlert($notification, 'Uncertain timeout failure');
        $this->assertFalse($sent);

        $notification->refresh();
        $this->assertNull($notification->alert_sent_at);
        $this->assertNotNull($notification->alert_claimed_at, 'Claim must be retained when SMTP outcome is uncertain');

        // Immediate retry is blocked to prevent duplicate email spamming
        $immediateRetry = $alertService->claimAlert($notification);
        $this->assertFalse($immediateRetry, 'Immediate retry must be rejected while uncertain claim lease is active');
    }

    /**
     * Section 1.6: Duplicate handle() and failed() execution sends exactly 1 alert email.
     */
    public function test_admin_alert_duplicate_handle_and_failed_execution_prevents_duplicate_email(): void
    {
        Mail::fake();
        config(['ems.notifications.admin_alert_recipients' => 'admin@sfumsa.ca']);

        $event = $this->publishedEvent();
        $registration = Registration::factory()->create(['event_id' => $event->id, 'status' => RegistrationStatus::Confirmed]);

        $notification = EventNotification::query()->create([
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'type' => 'registration_confirmed',
            'recipient_email' => 'student@sfu.ca',
            'status' => NotificationStatus::Pending,
            'alert_sent_at' => null,
            'alert_claimed_at' => null,
        ]);

        /** @var NotificationFailureAlertService $alertService */
        $alertService = app(NotificationFailureAlertService::class);

        // 1. handle() attempt fails permanently and triggers sendAlert()
        $alertService->sendAlert($notification, 'First permanent failure from handle()');

        // 2. Queue worker invokes failed() hook on job
        $job = new SendEventNotificationJob($notification->id);
        $job->failed(new \RuntimeException('Job failed hook'));

        // Invariant: Exactly 1 email sent, never 2
        Mail::assertSent(RegistrationEmailFailedAlertMail::class, 1);
    }

    /**
     * Section 2.1: Separate reservations for the same ticket type remain independent during release.
     */
    public function test_inventory_release_separate_reservations_remain_independent(): void
    {
        $event = $this->publishedEvent();
        $ticketType = $this->ticketType($event, 20.0, 50);
        $ticketType->quantity_sold = 10;
        $ticketType->save();

        $order1 = Order::factory()->create(['event_id' => $event->id, 'status' => OrderStatus::Pending, 'total_amount' => 40.0, 'currency' => 'CAD']);
        $reg1 = Registration::factory()->create(['event_id' => $event->id, 'order_id' => $order1->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 2, 'status' => RegistrationStatus::AwaitingPayment]);
        $pay1 = $this->createPayment($order1, $reg1, ['amount' => 40.0]);

        $order2 = Order::factory()->create(['event_id' => $event->id, 'status' => OrderStatus::Pending, 'total_amount' => 60.0, 'currency' => 'CAD']);
        $reg2 = Registration::factory()->create(['event_id' => $event->id, 'order_id' => $order2->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 3, 'status' => RegistrationStatus::AwaitingPayment]);
        $pay2 = $this->createPayment($order2, $reg2, ['amount' => 60.0]);

        /** @var CheckoutService $checkoutService */
        $checkoutService = app(CheckoutService::class);

        // Release Reservation 1: 10 - 2 = 8
        $checkoutService->releaseReservationIdempotent($order1, $pay1, $reg1, 'Customer cancelled order 1');
        $ticketType->refresh();
        $this->assertSame(8, (int) $ticketType->quantity_sold);

        // Release Reservation 2: 8 - 3 = 5
        $checkoutService->releaseReservationIdempotent($order2, $pay2, $reg2, 'Customer cancelled order 2');
        $ticketType->refresh();
        $this->assertSame(5, (int) $ticketType->quantity_sold);

        // Duplicate release of Reservation 1 must be no-op: stays 5
        $checkoutService->releaseReservationIdempotent($order1, $pay1, $reg1, 'Duplicate release');
        $ticketType->refresh();
        $this->assertSame(5, (int) $ticketType->quantity_sold);
    }

    /**
     * Section 2.2: Consistent lock ordering and non-negative capacity lower bound.
     */
    public function test_inventory_release_non_negative_boundary(): void
    {
        $event = $this->publishedEvent();
        $ticketType = $this->ticketType($event, 20.0, 50);
        $ticketType->quantity_sold = 2;
        $ticketType->save();

        $order = Order::factory()->create(['event_id' => $event->id, 'status' => OrderStatus::Pending, 'total_amount' => 100.0, 'currency' => 'CAD']);
        $reg = Registration::factory()->create(['event_id' => $event->id, 'order_id' => $order->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 5, 'status' => RegistrationStatus::AwaitingPayment]);
        $pay = $this->createPayment($order, $reg, ['amount' => 100.0]);

        /** @var CheckoutService $checkoutService */
        $checkoutService = app(CheckoutService::class);

        // Release quantity 5 when quantity_sold was 2: must bound at 0, never negative
        $checkoutService->releaseReservationIdempotent($order, $pay, $reg, 'Oversold release test');
        $ticketType->refresh();
        $this->assertSame(0, (int) $ticketType->quantity_sold);
    }

    /**
     * Section 3: Square Terminal payment verification across all 10 scenarios.
     */
    public function test_terminal_webhook_payment_verification_scenarios_comprehensive(): void
    {
        config([
            'ems.payments.enabled' => true,
            'ems.payments.square.location_id' => 'LOC_SFU_MSA',
            'ems.payments.square.access_token' => 'test-sq-token',
        ]);

        $event = $this->publishedEvent();
        $ticketType = $this->ticketType($event, 25.0, 50);

        // 1. Scenario: Successful payment (all fields match)
        $order1 = Order::factory()->create([
            'event_id' => $event->id,
            'reference' => 'TERM-ORD-SUCCESS-1',
            'status' => OrderStatus::Pending,
            'total_amount' => 25.0,
            'currency' => 'CAD',
        ]);
        $reg1 = Registration::factory()->create([
            'event_id' => $event->id,
            'order_id' => $order1->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'status' => RegistrationStatus::AwaitingPayment,
        ]);
        $pay1 = $this->createPayment($order1, $reg1, [
            'source_channel' => PaymentSourceChannel::Terminal->value,
            'amount' => 25.0,
            'currency' => 'CAD',
            'status' => PaymentStatus::Pending,
            'terminal_checkout_id' => 'term_checkout_succ_1',
        ]);

        // 1. Setup mock Square Client
        $mockSquare = $this->createMock(SquareClient::class);
        $mockSquare->method('enabled')->willReturn(true);
        $mockSquare->method('locationId')->willReturn('LOC_SFU_MSA');
        $mockSquare->method('get')->willReturnCallback(function ($path) use ($order1) {
            if (str_contains($path, '/v2/payments/sq_pay_success')) {
                return [
                    'payment' => [
                        'id' => 'sq_pay_success',
                        'status' => 'COMPLETED',
                        'amount_money' => ['amount' => 2500, 'currency' => 'CAD'],
                        'location_id' => 'LOC_SFU_MSA',
                        'reference_id' => $order1->reference,
                        'order_id' => 'sq_order_123',
                    ],
                ];
            }
            if (str_contains($path, '/v2/payments/sq_pay_approved')) {
                return [
                    'payment' => [
                        'id' => 'sq_pay_approved',
                        'status' => 'APPROVED',
                        'amount_money' => ['amount' => 2500, 'currency' => 'CAD'],
                        'location_id' => 'LOC_SFU_MSA',
                        'reference_id' => $order1->reference,
                    ],
                ];
            }
            if (str_contains($path, '/v2/payments/sq_pay_failed')) {
                return [
                    'payment' => [
                        'id' => 'sq_pay_failed',
                        'status' => 'FAILED',
                        'amount_money' => ['amount' => 2500, 'currency' => 'CAD'],
                        'location_id' => 'LOC_SFU_MSA',
                    ],
                ];
            }
            if (str_contains($path, '/v2/payments/sq_pay_canceled')) {
                return [
                    'payment' => [
                        'id' => 'sq_pay_canceled',
                        'status' => 'CANCELED',
                        'amount_money' => ['amount' => 2500, 'currency' => 'CAD'],
                        'location_id' => 'LOC_SFU_MSA',
                    ],
                ];
            }
            if (str_contains($path, '/v2/payments/sq_pay_pending')) {
                return [
                    'payment' => [
                        'id' => 'sq_pay_pending',
                        'status' => 'PENDING',
                        'amount_money' => ['amount' => 2500, 'currency' => 'CAD'],
                        'location_id' => 'LOC_SFU_MSA',
                    ],
                ];
            }
            if (str_contains($path, '/v2/payments/sq_pay_mystery')) {
                return [
                    'payment' => [
                        'id' => 'sq_pay_mystery',
                        'status' => 'MYSTERY_STATUS',
                        'amount_money' => ['amount' => 2500, 'currency' => 'CAD'],
                        'location_id' => 'LOC_SFU_MSA',
                    ],
                ];
            }
            if (str_contains($path, '/v2/payments/sq_pay_amt_bad')) {
                return [
                    'payment' => [
                        'id' => 'sq_pay_amt_bad',
                        'status' => 'COMPLETED',
                        'amount_money' => ['amount' => 1000, 'currency' => 'CAD'], // 10.00 vs 25.00
                        'location_id' => 'LOC_SFU_MSA',
                    ],
                ];
            }
            if (str_contains($path, '/v2/payments/sq_pay_curr_bad')) {
                return [
                    'payment' => [
                        'id' => 'sq_pay_curr_bad',
                        'status' => 'COMPLETED',
                        'amount_money' => ['amount' => 2500, 'currency' => 'USD'], // USD vs CAD
                        'location_id' => 'LOC_SFU_MSA',
                    ],
                ];
            }
            if (str_contains($path, '/v2/payments/sq_pay_loc_bad')) {
                return [
                    'payment' => [
                        'id' => 'sq_pay_loc_bad',
                        'status' => 'COMPLETED',
                        'amount_money' => ['amount' => 2500, 'currency' => 'CAD'],
                        'location_id' => 'WRONG_LOCATION',
                    ],
                ];
            }
            if (str_contains($path, '/v2/payments/sq_pay_ref_bad')) {
                return [
                    'payment' => [
                        'id' => 'sq_pay_ref_bad',
                        'status' => 'COMPLETED',
                        'amount_money' => ['amount' => 2500, 'currency' => 'CAD'],
                        'location_id' => 'LOC_SFU_MSA',
                        'reference_id' => 'WRONG_REFERENCE_ID',
                    ],
                ];
            }
            if (str_contains($path, '/v2/payments/sq_pay_unknown')) {
                throw new EmsException('Payment not found', [], 404, 'NOT_FOUND');
            }
            if (str_contains($path, '/v2/payments/sq_pay_approved_recon')) {
                return [
                    'payment' => [
                        'id' => 'sq_pay_approved_recon',
                        'status' => 'APPROVED',
                        'amount_money' => ['amount' => 2500, 'currency' => 'CAD'],
                        'location_id' => 'LOC_SFU_MSA',
                    ],
                ];
            }
            if (str_contains($path, '/v2/terminals/checkouts/term_checkout_appr_recon')) {
                return [
                    'checkout' => [
                        'id' => 'term_checkout_appr_recon',
                        'status' => 'COMPLETED',
                        'payment_ids' => ['sq_pay_approved_recon'],
                    ],
                ];
            }

            return [];
        });

        $this->app->instance(SquareClient::class, $mockSquare);
        /** @var SquareTerminalService $terminalService */
        $terminalService = app(SquareTerminalService::class);

        // 1. Scenario 1: COMPLETED payment successfully fulfills
        $res1 = $terminalService->handleCheckoutUpdated([
            'id' => 'term_checkout_succ_1',
            'status' => 'COMPLETED',
            'payment_ids' => ['sq_pay_success'],
        ]);
        $this->assertNotNull($res1);
        $pay1->refresh();
        $this->assertSame(PaymentStatus::Paid, $pay1->status);
        $this->assertSame('sq_pay_success', $pay1->provider_payment_id);
        $this->assertSame(1, Ticket::query()->where('registration_id', $reg1->id)->count(), 'COMPLETED payment must issue ticket');

        // 2. Scenario 2: APPROVED payment remains pending/authorized and issues no tickets
        $orderAppr = Order::factory()->create(['event_id' => $event->id, 'reference' => 'TERM-ORD-APPR', 'status' => OrderStatus::Pending, 'total_amount' => 25.0, 'currency' => 'CAD']);
        $regAppr = Registration::factory()->create(['event_id' => $event->id, 'order_id' => $orderAppr->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 1, 'status' => RegistrationStatus::AwaitingPayment]);
        $payAppr = $this->createPayment($orderAppr, $regAppr, ['source_channel' => PaymentSourceChannel::Terminal->value, 'amount' => 25.0, 'currency' => 'CAD', 'terminal_checkout_id' => 'term_appr']);

        $resAppr = $terminalService->handleCheckoutUpdated([
            'id' => 'term_appr',
            'status' => 'COMPLETED',
            'payment_ids' => ['sq_pay_approved'],
        ]);
        $this->assertNotNull($resAppr);
        $payAppr->refresh();
        $this->assertSame(PaymentStatus::Authorized, $payAppr->status, 'APPROVED payment must remain Authorized, not Paid');
        $this->assertSame(0, Ticket::query()->where('registration_id', $regAppr->id)->count(), 'APPROVED payment must NOT issue tickets');
        $orderAppr->refresh();
        $this->assertSame(OrderStatus::Pending, $orderAppr->status, 'Order must remain Pending when payment is only APPROVED');

        // 3. Scenario 3: PENDING payment remains pending
        $orderPend = Order::factory()->create(['event_id' => $event->id, 'reference' => 'TERM-ORD-PEND', 'status' => OrderStatus::Pending, 'total_amount' => 25.0, 'currency' => 'CAD']);
        $regPend = Registration::factory()->create(['event_id' => $event->id, 'order_id' => $orderPend->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 1, 'status' => RegistrationStatus::AwaitingPayment]);
        $payPend = $this->createPayment($orderPend, $regPend, ['source_channel' => PaymentSourceChannel::Terminal->value, 'amount' => 25.0, 'currency' => 'CAD', 'terminal_checkout_id' => 'term_pend']);

        $resPend = $terminalService->handleCheckoutUpdated([
            'id' => 'term_pend',
            'status' => 'COMPLETED',
            'payment_ids' => ['sq_pay_pending'],
        ]);
        $payPend->refresh();
        $this->assertSame(PaymentStatus::Pending, $payPend->status, 'Pending Square payment must remain pending');
        $this->assertSame(0, Ticket::query()->where('registration_id', $regPend->id)->count(), 'PENDING payment must not issue tickets');

        // 4. Scenario 4: FAILED payment is safely cancelled
        $orderFail = Order::factory()->create(['event_id' => $event->id, 'reference' => 'TERM-ORD-FAIL', 'status' => OrderStatus::Pending, 'total_amount' => 25.0, 'currency' => 'CAD']);
        $regFail = Registration::factory()->create(['event_id' => $event->id, 'order_id' => $orderFail->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 1, 'status' => RegistrationStatus::AwaitingPayment]);
        $payFail = $this->createPayment($orderFail, $regFail, ['source_channel' => PaymentSourceChannel::Terminal->value, 'amount' => 25.0, 'currency' => 'CAD', 'terminal_checkout_id' => 'term_fail']);

        $resFail = $terminalService->handleCheckoutUpdated([
            'id' => 'term_fail',
            'status' => 'COMPLETED',
            'payment_ids' => ['sq_pay_failed'],
        ]);
        $payFail->refresh();
        $this->assertSame(PaymentStatus::Cancelled, $payFail->status, 'FAILED Square payment must cancel local payment');
        $orderFail->refresh();
        $this->assertSame(OrderStatus::Cancelled, $orderFail->status);

        // 5. Scenario 5: CANCELED payment is safely cancelled
        $orderCanc = Order::factory()->create(['event_id' => $event->id, 'reference' => 'TERM-ORD-CANC', 'status' => OrderStatus::Pending, 'total_amount' => 25.0, 'currency' => 'CAD']);
        $regCanc = Registration::factory()->create(['event_id' => $event->id, 'order_id' => $orderCanc->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 1, 'status' => RegistrationStatus::AwaitingPayment]);
        $payCanc = $this->createPayment($orderCanc, $regCanc, ['source_channel' => PaymentSourceChannel::Terminal->value, 'amount' => 25.0, 'currency' => 'CAD', 'terminal_checkout_id' => 'term_canc']);

        $resCanc = $terminalService->handleCheckoutUpdated([
            'id' => 'term_canc',
            'status' => 'COMPLETED',
            'payment_ids' => ['sq_pay_canceled'],
        ]);
        $payCanc->refresh();
        $this->assertSame(PaymentStatus::Cancelled, $payCanc->status, 'CANCELED Square payment must cancel local payment');

        // 6. Scenario 6: Unknown payment status is retained for review
        $orderUnk = Order::factory()->create(['event_id' => $event->id, 'reference' => 'TERM-ORD-UNK', 'status' => OrderStatus::Pending, 'total_amount' => 25.0, 'currency' => 'CAD']);
        $regUnk = Registration::factory()->create(['event_id' => $event->id, 'order_id' => $orderUnk->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 1, 'status' => RegistrationStatus::AwaitingPayment]);
        $payUnk = $this->createPayment($orderUnk, $regUnk, ['source_channel' => PaymentSourceChannel::Terminal->value, 'amount' => 25.0, 'currency' => 'CAD', 'terminal_checkout_id' => 'term_unk']);

        $resUnk = $terminalService->handleCheckoutUpdated([
            'id' => 'term_unk',
            'status' => 'COMPLETED',
            'payment_ids' => ['sq_pay_mystery'],
        ]);
        $payUnk->refresh();
        $this->assertSame(PaymentStatus::Pending, $payUnk->status, 'Unknown payment status must remain pending');
        $this->assertTrue((bool) ($payUnk->metadata['reconciliation_review_required'] ?? false), 'Unknown status must be flagged for review');

        // 7. Scenario 7: Duplicate COMPLETED webhook does not duplicate fulfillment
        $resDup = $terminalService->handleCheckoutUpdated([
            'id' => 'term_checkout_succ_1',
            'status' => 'COMPLETED',
            'payment_ids' => ['sq_pay_success'],
        ]);
        $this->assertNotNull($resDup);
        $this->assertSame(1, Ticket::query()->where('registration_id', $reg1->id)->count(), 'Duplicate COMPLETED webhook must not duplicate tickets');

        // 8. Scenario 8: Delayed APPROVED webhook cannot downgrade an already completed payment
        $resDelayed = $terminalService->handleCheckoutUpdated([
            'id' => 'term_checkout_succ_1',
            'status' => 'COMPLETED',
            'payment_ids' => ['sq_pay_approved'],
        ]);
        $pay1->refresh();
        $this->assertSame(PaymentStatus::Paid, $pay1->status, 'Delayed APPROVED webhook must NOT downgrade already Paid payment');
        $this->assertSame(1, Ticket::query()->where('registration_id', $reg1->id)->count());

        // 9. Scenario 9: Amount, currency, location, order-reference, and missing ID mismatches are rejected
        // 9a. Amount mismatch
        $resAmtBad = $terminalService->handleCheckoutUpdated([
            'id' => 'term_pend',
            'status' => 'COMPLETED',
            'payment_ids' => ['sq_pay_amt_bad'],
        ]);
        $this->assertNull($resAmtBad, 'Mismatched amount must return null');

        // 9b. Currency mismatch
        $resCurrBad = $terminalService->handleCheckoutUpdated([
            'id' => 'term_pend',
            'status' => 'COMPLETED',
            'payment_ids' => ['sq_pay_curr_bad'],
        ]);
        $this->assertNull($resCurrBad, 'Mismatched currency must return null');

        // 9c. Location mismatch
        $resLocBad = $terminalService->handleCheckoutUpdated([
            'id' => 'term_pend',
            'status' => 'COMPLETED',
            'payment_ids' => ['sq_pay_loc_bad'],
        ]);
        $this->assertNull($resLocBad, 'Mismatched location must return null');

        // 9d. Reference mismatch
        $resRefBad = $terminalService->handleCheckoutUpdated([
            'id' => 'term_pend',
            'status' => 'COMPLETED',
            'payment_ids' => ['sq_pay_ref_bad'],
        ]);
        $this->assertNull($resRefBad, 'Mismatched reference must return null');

        // 9e. Missing payment ID
        $resMissing = $terminalService->handleCheckoutUpdated([
            'id' => 'term_pend',
            'status' => 'COMPLETED',
            'payment_ids' => [],
        ]);
        $this->assertNull($resMissing, 'Missing payment_ids must return null');

        // 9f. Unknown payment ID (404)
        $resUnknown = $terminalService->handleCheckoutUpdated([
            'id' => 'term_pend',
            'status' => 'COMPLETED',
            'payment_ids' => ['sq_pay_unknown'],
        ]);
        $this->assertNull($resUnknown, 'Unknown payment ID must return null');

        // 10. Scenario 10: Reconciliation never fulfills an APPROVED payment
        $orderReconAppr = Order::factory()->create(['event_id' => $event->id, 'reference' => 'TERM-RECON-APPR', 'status' => OrderStatus::Pending, 'total_amount' => 25.0, 'currency' => 'CAD']);
        $regReconAppr = Registration::factory()->create(['event_id' => $event->id, 'order_id' => $orderReconAppr->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 1, 'status' => RegistrationStatus::AwaitingPayment]);
        $payReconAppr = $this->createPayment($orderReconAppr, $regReconAppr, [
            'source_channel' => PaymentSourceChannel::Terminal->value,
            'amount' => 25.0,
            'currency' => 'CAD',
            'status' => PaymentStatus::Processing,
            'terminal_checkout_id' => 'term_checkout_appr_recon',
            'metadata' => ['terminal_creation_ambiguous' => true],
        ]);
        DB::table('ems_payments')->where('id', $payReconAppr->id)->update(['created_at' => now()->subMinutes(15)]);

        /** @var SquareReconciliationService $reconService */
        $reconService = app(SquareReconciliationService::class);
        $reconOutcomes = $reconService->reconcileAmbiguousPayments(5);

        $payReconAppr->refresh();
        $this->assertNotSame(PaymentStatus::Paid, $payReconAppr->status, 'Reconciliation must NEVER mark an APPROVED payment as Paid');
        $this->assertSame(PaymentStatus::Authorized, $payReconAppr->status, 'APPROVED payment must remain Authorized');
        $this->assertSame(0, Ticket::query()->where('registration_id', $regReconAppr->id)->count(), 'Reconciliation of APPROVED payment must NOT issue tickets');
        $this->assertSame('uncertain_retained', $payReconAppr->metadata['reconciliation_status']);
    }

    /**
     * Section 4: Ambiguous payment reconciliation operational lifecycle.
     */
    public function test_ambiguous_payment_reconciliation_lifecycle(): void
    {
        config([
            'ems.payments.enabled' => true,
            'ems.payments.square.location_id' => 'LOC_SFU_MSA',
            'ems.payments.square.access_token' => 'test-sq-token',
        ]);

        $event = $this->publishedEvent();
        $ticketType = $this->ticketType($event, 30.0, 50);
        $ticketType->quantity_sold = 10;
        $ticketType->save();

        // 1. Ambiguous Terminal payment that was actually COMPLETED on Square
        $order1 = Order::factory()->create(['event_id' => $event->id, 'reference' => 'TERM-AMB-1', 'status' => OrderStatus::Pending, 'total_amount' => 30.0, 'currency' => 'CAD']);
        $reg1 = Registration::factory()->create(['event_id' => $event->id, 'order_id' => $order1->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 1, 'status' => RegistrationStatus::AwaitingPayment]);
        $pay1 = $this->createPayment($order1, $reg1, [
            'source_channel' => PaymentSourceChannel::Terminal->value,
            'amount' => 30.0,
            'currency' => 'CAD',
            'status' => PaymentStatus::Processing,
            'terminal_checkout_id' => 'term_amb_comp_1',
            'created_at' => now()->subMinutes(10),
            'metadata' => ['terminal_creation_ambiguous' => true],
        ]);

        // 2. Ambiguous Terminal payment that was CANCELED on Square
        $order2 = Order::factory()->create(['event_id' => $event->id, 'reference' => 'TERM-AMB-2', 'status' => OrderStatus::Pending, 'total_amount' => 30.0, 'currency' => 'CAD']);
        $reg2 = Registration::factory()->create(['event_id' => $event->id, 'order_id' => $order2->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 1, 'status' => RegistrationStatus::AwaitingPayment]);
        $pay2 = $this->createPayment($order2, $reg2, [
            'source_channel' => PaymentSourceChannel::Terminal->value,
            'amount' => 30.0,
            'currency' => 'CAD',
            'status' => PaymentStatus::Processing,
            'terminal_checkout_id' => 'term_amb_canc_2',
            'created_at' => now()->subMinutes(10),
            'metadata' => ['terminal_creation_ambiguous' => true],
        ]);

        // 3. Ambiguous Terminal payment that is still PENDING on Square (uncertain -> retain)
        $order3 = Order::factory()->create(['event_id' => $event->id, 'reference' => 'TERM-AMB-3', 'status' => OrderStatus::Pending, 'total_amount' => 30.0, 'currency' => 'CAD']);
        $reg3 = Registration::factory()->create(['event_id' => $event->id, 'order_id' => $order3->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 1, 'status' => RegistrationStatus::AwaitingPayment]);
        $pay3 = $this->createPayment($order3, $reg3, [
            'source_channel' => PaymentSourceChannel::Terminal->value,
            'amount' => 30.0,
            'currency' => 'CAD',
            'status' => PaymentStatus::Processing,
            'terminal_checkout_id' => 'term_amb_pend_3',
            'created_at' => now()->subMinutes(10),
            'metadata' => ['terminal_creation_ambiguous' => true],
        ]);

        // 4. Ambiguous Online payment with expired checkout link (TTL expired policy -> release)
        $order4 = Order::factory()->create(['event_id' => $event->id, 'reference' => 'ONLINE-EXP-4', 'status' => OrderStatus::Pending, 'total_amount' => 30.0, 'currency' => 'CAD']);
        $reg4 = Registration::factory()->create(['event_id' => $event->id, 'order_id' => $order4->id, 'ticket_type_id' => $ticketType->id, 'quantity' => 1, 'status' => RegistrationStatus::AwaitingPayment]);
        $pay4 = $this->createPayment($order4, $reg4, [
            'source_channel' => PaymentSourceChannel::Online->value,
            'amount' => 30.0,
            'currency' => 'CAD',
            'status' => PaymentStatus::Processing,
            'provider_order_id' => 'sq_ord_exp_4',
            'checkout_expires_at' => now()->subMinutes(20),
            'created_at' => now()->subMinutes(30),
            'metadata' => ['provider_creation_ambiguous' => true],
        ]);

        DB::table('ems_payments')->whereIn('id', [$pay1->id, $pay2->id, $pay3->id, $pay4->id])
            ->update(['created_at' => now()->subMinutes(15)]);

        $mockSquare = $this->createMock(SquareClient::class);
        $mockSquare->method('enabled')->willReturn(true);
        $mockSquare->method('locationId')->willReturn('LOC_SFU_MSA');
        $mockSquare->method('get')->willReturnCallback(function ($path) use ($order1) {
            if (str_contains($path, '/v2/terminals/checkouts/term_amb_comp_1')) {
                return [
                    'checkout' => [
                        'id' => 'term_amb_comp_1',
                        'status' => 'COMPLETED',
                        'payment_ids' => ['sq_pay_amb_1'],
                        'reference_id' => $order1->reference,
                    ],
                ];
            }
            if (str_contains($path, '/v2/payments/sq_pay_amb_1')) {
                return [
                    'payment' => [
                        'id' => 'sq_pay_amb_1',
                        'status' => 'COMPLETED',
                        'amount_money' => ['amount' => 3000, 'currency' => 'CAD'],
                        'location_id' => 'LOC_SFU_MSA',
                        'reference_id' => $order1->reference,
                    ],
                ];
            }
            if (str_contains($path, '/v2/terminals/checkouts/term_amb_canc_2')) {
                return [
                    'checkout' => [
                        'id' => 'term_amb_canc_2',
                        'status' => 'CANCELED',
                    ],
                ];
            }
            if (str_contains($path, '/v2/terminals/checkouts/term_amb_pend_3')) {
                return [
                    'checkout' => [
                        'id' => 'term_amb_pend_3',
                        'status' => 'PENDING',
                    ],
                ];
            }
            if (str_contains($path, '/v2/orders/sq_ord_exp_4')) {
                return [
                    'order' => [
                        'id' => 'sq_ord_exp_4',
                        'state' => 'OPEN',
                        'tenders' => [],
                    ],
                ];
            }

            return [];
        });

        $this->app->instance(SquareClient::class, $mockSquare);
        /** @var SquareReconciliationService $reconService */
        $reconService = app(SquareReconciliationService::class);

        $outcomes = $reconService->reconcileAmbiguousPayments(5);

        $this->assertSame(4, $outcomes['examined']);
        $this->assertSame(1, $outcomes['reconciled_paid']);
        $this->assertSame(2, $outcomes['inventory_released']); // terminal cancelled (pay2) + online expired (pay4)
        $this->assertSame(1, $outcomes['uncertain_retained']); // terminal pending (pay3)

        // Verify Pay 1: Reconciled to Paid
        $pay1->refresh();
        $this->assertSame(PaymentStatus::Paid, $pay1->status);
        $this->assertSame('reconciled_paid', $pay1->metadata['reconciliation_status']);
        $this->assertSame(1, Ticket::query()->where('registration_id', $reg1->id)->count());

        // Verify Pay 2: Cancelled, inventory released
        $pay2->refresh();
        $this->assertSame(PaymentStatus::Cancelled, $pay2->status);
        $this->assertSame('released_cancelled', $pay2->metadata['reconciliation_status']);
        $this->assertTrue((bool) ($pay2->metadata['inventory_released'] ?? false));

        // Verify Pay 3: Uncertain outcome -> reservation held, flagged for admin review
        $pay3->refresh();
        $this->assertSame(PaymentStatus::Processing, $pay3->status, 'Uncertain payment must NOT be cancelled');
        $this->assertSame('uncertain_retained', $pay3->metadata['reconciliation_status']);
        $this->assertTrue((bool) ($pay3->metadata['reconciliation_review_required'] ?? false));
        $this->assertFalse((bool) ($pay3->metadata['inventory_released'] ?? false));

        // Verify Pay 4: Expired checkout -> safely released
        $pay4->refresh();
        $this->assertSame(PaymentStatus::Cancelled, $pay4->status);
        $this->assertSame('released_expired', $pay4->metadata['reconciliation_status']);

        // Verify capacity: 10 - 1 (pay2 released) - 1 (pay4 released) = 8
        $ticketType->refresh();
        $this->assertSame(8, (int) $ticketType->quantity_sold);

        // Verify admin visibility
        $unresolved = $reconService->getUnresolvedAmbiguousPayments();
        $this->assertTrue($unresolved->contains('id', $pay3->id), 'Pay 3 must be visible in unresolved admin list');

        // Verify idempotency: running reconciliation again produces no duplicate actions
        $secondRun = $reconService->reconcileAmbiguousPayments(5);
        $this->assertSame(0, $secondRun['reconciled_paid']);
        $this->assertSame(0, $secondRun['inventory_released']);
        $ticketType->refresh();
        $this->assertSame(8, (int) $ticketType->quantity_sold, 'Capacity must remain exactly 8 on second run');
    }
}
