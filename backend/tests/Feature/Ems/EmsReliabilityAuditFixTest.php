<?php

namespace Tests\Feature\Ems;

use App\Ems\Contracts\PaymentProvider;
use App\Ems\Contracts\TicketIssuer;
use App\Ems\Enums\CheckInMethod;
use App\Ems\Enums\EventStatus;
use App\Ems\Enums\NotificationStatus;
use App\Ems\Enums\NotificationType;
use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\PaymentProvider as PaymentProviderEnum;
use App\Ems\Enums\PaymentSourceChannel;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\TicketStatus;
use App\Ems\Enums\WebhookEventStatus;
use App\Ems\Exceptions\CheckInException;
use App\Ems\Exceptions\EmsException;
use App\Ems\Jobs\SendEventNotificationJob;
use App\Ems\Mail\RegistrationEmailFailedAlertMail;
use App\Ems\Models\CheckIn;
use App\Ems\Models\CheckInAudit;
use App\Ems\Models\Event;
use App\Ems\Models\EventNotification;
use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use App\Ems\Models\TicketType;
use App\Ems\Models\WebhookEvent;
use App\Ems\Services\CheckoutService;
use App\Ems\Services\Operations\CheckInService;
use App\Ems\Services\Operations\EventOperationsService;
use App\Ems\Services\PaymentFulfillmentService;
use App\Ems\Services\Payments\CheckoutSession;
use App\Ems\Services\Payments\PaymentProviderManager;
use App\Ems\Services\Payments\Providers\SquarePaymentProvider;
use App\Ems\Services\Square\SquareClient;
use App\Ems\Services\Square\SquareTerminalService;
use App\Ems\Support\EmsRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EmsReliabilityAuditFixTest extends EmsTestCase
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

    private function ticketType(Event $event, float $price = 0.0, int $capacity = 50): TicketType
    {
        return TicketType::factory()->create([
            'event_id' => $event->id,
            'price' => $price,
            'quantity' => $capacity,
            'quantity_sold' => 0,
            'sales_start_at' => now()->subDay(),
            'sales_end_at' => now()->addDay(),
        ]);
    }

    public function test_first_qr_scan_succeeds_and_repeated_scan_returns_already_checked_in_without_duplicate_records(): void
    {
        $event = $this->publishedEvent();
        $staff = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $type = $this->ticketType($event);

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $type->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_name' => 'Bilal Hassan',
            'attendee_email' => 'bilal@example.com',
        ]);

        $ticket = app(TicketIssuer::class)->issueFor($registration)->first();
        $this->assertNotNull($ticket);

        $checkInService = app(CheckInService::class);

        // First scan succeeds
        $first = $checkInService->checkInByCode(
            $event,
            $ticket->code,
            $staff,
            CheckInMethod::QrScan,
            'scanner-1'
        );

        $this->assertSame(TicketStatus::Redeemed, $first['ticket']->status);
        $this->assertSame(1, CheckIn::query()->where('ticket_id', $ticket->id)->count());
        $this->assertSame(1, CheckInAudit::query()->where('ticket_id', $ticket->id)->count());

        // Repeated scan fails with already_checked_in CheckInException without creating duplicate check-in row
        try {
            $checkInService->checkInByCode(
                $event,
                $ticket->code,
                $staff,
                CheckInMethod::QrScan,
                'scanner-2'
            );
            $this->fail('Expected CheckInException alreadyCheckedIn was not thrown.');
        } catch (CheckInException $e) {
            $this->assertSame('already_checked_in', $e->resultCode());
        }

        $this->assertSame(1, CheckIn::query()->where('ticket_id', $ticket->id)->count());
        $this->assertSame(2, CheckInAudit::query()->where('event_id', $event->id)->count());
    }

    /**
     * Scenario A: Ambiguous Square checkout outcome (gateway timeout / connection drop)
     * must preserve reservation and inventory, allowing subsequent webhook to fulfill cleanly.
     */
    public function test_checkout_timeout_preserves_reservation_and_subsequent_webhook_fulfills_order(): void
    {
        $event = $this->publishedEvent();
        $type = $this->ticketType($event, price: 25.0, capacity: 10);
        $this->assertSame(0, $type->quantity_sold);

        // Mock payment provider to simulate Square API gateway timeout
        $mockProvider = $this->createMock(SquarePaymentProvider::class);
        $mockProvider->method('name')->willReturn('square');
        $mockProvider->method('createCheckout')->willThrowException(
            new EmsException('Square gateway timeout', [], Response::HTTP_GATEWAY_TIMEOUT)
        );

        $this->app->instance(SquarePaymentProvider::class, $mockProvider);
        config(['ems.payments.enabled' => true]);

        $checkoutService = app(CheckoutService::class);

        try {
            $checkoutService->checkout($event, [
                'ticket_type_id' => $type->uuid,
                'quantity' => 2,
                'first_name' => 'Fatima',
                'last_name' => 'Zahra',
                'email' => 'fatima@example.com',
            ]);
            $this->fail('Expected EmsException was not thrown on timeout.');
        } catch (EmsException $e) {
            $this->assertSame(Response::HTTP_GATEWAY_TIMEOUT, $e->getCode());
        }

        // 1. Reserved capacity is NOT released; held safely against overbooking
        $type->refresh();
        $this->assertSame(2, $type->quantity_sold);

        // 2. Registration and Payment remain active and ambiguous (NOT cancelled)
        $registration = Registration::query()->where('attendee_email', 'fatima@example.com')->first();
        $this->assertNotNull($registration);
        $this->assertSame(RegistrationStatus::AwaitingPayment, $registration->status);

        $payment = Payment::query()->where('registration_id', $registration->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertTrue($payment->metadata['provider_creation_ambiguous'] ?? false);

        // 3. Subsequent webhook arrives indicating payment was captured
        $fulfillment = app(PaymentFulfillmentService::class);
        $fulfilledPayment = $fulfillment->markPaid($payment, [
            'provider_payment_id' => 'sq_pay_scenario_a_123',
            'provider_order_id' => 'sq_ord_scenario_a_123',
        ]);

        $this->assertSame(PaymentStatus::Paid, $fulfilledPayment->status);
        $registration->refresh();
        $this->assertSame(RegistrationStatus::Confirmed, $registration->status);

        // 4. Exactly 2 tickets issued for the 2-quantity registration
        $tickets = Ticket::query()->where('registration_id', $registration->id)->get();
        $this->assertCount(2, $tickets);

        // 5. Inventory count remains 2 (not double-incremented or decremented)
        $type->refresh();
        $this->assertSame(2, $type->quantity_sold);
    }

    /**
     * Definitive 4xx client rejection from Square must safely cancel the local reservation
     * and release inventory idempotently.
     */
    public function test_checkout_creation_outside_transaction_restores_capacity_on_definitive_square_rejection(): void
    {
        $event = $this->publishedEvent();
        $type = $this->ticketType($event, price: 25.0, capacity: 10);
        $this->assertSame(0, $type->quantity_sold);

        $mockProvider = $this->createMock(SquarePaymentProvider::class);
        $mockProvider->method('name')->willReturn('square');
        $mockProvider->method('createCheckout')->willThrowException(
            new EmsException('Square rejected checkout parameters: Invalid location', ['payment' => ['Invalid location']], Response::HTTP_UNPROCESSABLE_ENTITY)
        );

        $this->app->instance(SquarePaymentProvider::class, $mockProvider);
        config(['ems.payments.enabled' => true]);

        $checkoutService = app(CheckoutService::class);

        try {
            $checkoutService->checkout($event, [
                'ticket_type_id' => $type->uuid,
                'quantity' => 2,
                'first_name' => 'Tariq',
                'last_name' => 'Ali',
                'email' => 'tariq@example.com',
            ]);
            $this->fail('Expected EmsException was not thrown on rejection.');
        } catch (EmsException $e) {
            $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $e->getCode());
        }

        // Reserved capacity was decremented back
        $type->refresh();
        $this->assertSame(0, $type->quantity_sold);

        // Registration and payment are marked cancelled
        $registration = Registration::query()->where('attendee_email', 'tariq@example.com')->first();
        $this->assertNotNull($registration);
        $this->assertSame(RegistrationStatus::Cancelled, $registration->status);

        $payment = Payment::query()->where('registration_id', $registration->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame(PaymentStatus::Cancelled, $payment->status);
        $this->assertTrue($payment->metadata['inventory_released'] ?? false);
    }

    /**
     * Scenario B: Terminal checkout times out before terminal_checkout_id is persisted locally.
     * Subsequent completion webhook matches via reference_id and fulfills smoothly.
     */
    public function test_terminal_timeout_preserves_reservation_and_subsequent_webhook_correlates_by_reference(): void
    {
        $event = $this->publishedEvent();
        $staff = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $type = $this->ticketType($event, price: 10.0, capacity: 20);

        // Mock SquareClient to simulate timeout on terminal checkout creation
        $mockSquare = $this->createMock(SquareClient::class);
        $mockSquare->method('enabled')->willReturn(true);
        $mockSquare->method('post')->willThrowException(
            new EmsException('Connection to Square Terminal timed out', [], Response::HTTP_GATEWAY_TIMEOUT)
        );
        $mockSquare->method('get')->willReturnCallback(function ($path) {
            if (str_contains($path, '/v2/payments/sq_pay_term_completed_777')) {
                return [
                    'payment' => [
                        'id' => 'sq_pay_term_completed_777',
                        'status' => 'COMPLETED',
                        'amount_money' => ['amount' => 1000, 'currency' => 'CAD'],
                    ],
                ];
            }
            return [];
        });

        $this->app->instance(SquareClient::class, $mockSquare);
        config(['ems.payments.enabled' => true]);

        $terminalService = app(SquareTerminalService::class);

        try {
            $terminalService->createCheckout($event, [
                'ticket_type_id' => $type->uuid,
                'quantity' => 1,
                'attendee_name' => 'Zayd Khan',
                'attendee_email' => 'zayd@example.com',
                'device_id' => 'device_pos_test_001',
            ], $staff);
            $this->fail('Expected EmsException was not thrown.');
        } catch (EmsException $e) {
            $this->assertSame(Response::HTTP_GATEWAY_TIMEOUT, $e->getCode());
        }

        // Reservation and inventory preserved
        $type->refresh();
        $this->assertSame(1, $type->quantity_sold);

        $order = Order::query()->where('buyer_email', 'zayd@example.com')->first();
        $this->assertNotNull($order);
        $this->assertSame(OrderStatus::Pending, $order->status);

        $payment = Payment::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($payment);
        $this->assertNull($payment->terminal_checkout_id);
        $this->assertTrue($payment->metadata['terminal_creation_ambiguous'] ?? false);

        // Later, Square sends webhook terminal.checkout.updated with reference_id
        $webhookCheckoutPayload = [
            'id' => 'term_checkout_remote_999',
            'status' => 'COMPLETED',
            'reference_id' => $order->reference,
            'payment_ids' => ['sq_pay_term_completed_777'],
            'order_id' => 'sq_ord_term_completed_888',
        ];

        $reconciledPayment = $terminalService->handleCheckoutUpdated($webhookCheckoutPayload);

        $this->assertNotNull($reconciledPayment);
        $this->assertSame(PaymentStatus::Paid, $reconciledPayment->status);
        $this->assertSame('term_checkout_remote_999', $reconciledPayment->terminal_checkout_id);

        $order->refresh();
        $this->assertSame(OrderStatus::Completed, $order->status);

        $registration = Registration::query()->where('order_id', $order->id)->first();
        $this->assertSame(RegistrationStatus::Confirmed, $registration->status);
        $this->assertSame(1, Ticket::query()->where('registration_id', $registration->id)->count());
    }

    public function test_event_operations_summary_consolidates_registrations_and_payments(): void
    {
        $event = $this->publishedEvent();
        $type = $this->ticketType($event, price: 15.0);

        $reg1 = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $type->id,
            'status' => RegistrationStatus::Confirmed,
            'quantity' => 2,
            'metadata' => ['source' => 'walk_in'],
        ]);

        $reg2 = Registration::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $type->id,
            'status' => RegistrationStatus::Pending,
            'quantity' => 1,
        ]);

        // Attach a paid payment to reg1
        Payment::query()->create([
            'registration_id' => $reg1->id,
            'amount' => 30.0,
            'currency' => 'CAD',
            'status' => PaymentStatus::Paid,
            'provider' => 'square',
        ]);

        $summary = app(EventOperationsService::class)->summary($event, includePaymentSummary: true);

        $this->assertSame(3, $summary['registered_count']);
        $this->assertSame(2, $summary['confirmed_count']);
        $this->assertSame(1, $summary['walk_in_count']);
        $this->assertArrayHasKey('payment_summary', $summary);
        $this->assertSame(30.0, (float) $summary['payment_summary']['paid_amount']);
    }

    public function test_unmatched_square_remediation_command_dry_run_and_execution(): void
    {
        $event = WebhookEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'provider' => 'square',
            'event_id' => 'evt_unmatched_pos_test_123',
            'event_type' => 'payment.created',
            'status' => WebhookEventStatus::Unmatched->value,
            'retry_count' => 3,
            'failure_reason' => 'No EMS catalog mapping for this Square sale.',
            'payload' => [
                'data' => [
                    'object' => [
                        'payment' => [
                            'amount_money' => ['amount' => 500, 'currency' => 'CAD'],
                            'source_type' => 'CARD',
                        ],
                        'order' => [
                            'line_items' => [['name' => 'Samosa Fundraiser']],
                        ],
                    ],
                ],
            ],
        ]);

        // Dry-run by default: no mutation
        $this->artisan('ems:square:remediate-unmatched')
            ->assertExitCode(0);

        $event->refresh();
        $this->assertSame(WebhookEventStatus::Unmatched->value, $event->status);

        // Targeted mutation: classify as external transaction
        $this->artisan('ems:square:remediate-unmatched', [
            '--event-id' => 'evt_unmatched_pos_test_123',
            '--classify-external' => true,
        ])->assertExitCode(0);

        $event->refresh();
        $this->assertSame(WebhookEventStatus::Ignored->value, $event->status);
        $this->assertNull($event->next_retry_at);
        $this->assertStringContainsString('Classified by operator', $event->failure_reason);
    }

    /**
     * Scenario F: Notifications retry command must skip future scheduled notifications
     * and only dispatch eligible due/failed notifications.
     */
    public function test_notifications_retry_command_skips_future_scheduled_notifications(): void
    {
        Mail::fake();
        config(['ems.notifications.enabled' => true]);

        $event = $this->publishedEvent();
        $reg = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_email' => 'future@example.com',
        ]);

        // 1. Future notification: scheduled for 2 days from now
        $futureNotification = EventNotification::query()->create([
            'event_id' => $event->id,
            'registration_id' => $reg->id,
            'recipient_email' => 'future@example.com',
            'type' => NotificationType::EventReminder->value,
            'subject' => 'Future Reminder',
            'body' => 'See you in 2 days.',
            'status' => NotificationStatus::Scheduled->value,
            'retry_count' => 0,
            'idempotency_key' => 'test-future-notif-' . Str::uuid(),
            'scheduled_at' => now()->addDays(2),
        ]);

        // 2. Due failed notification: eligible for retry
        $dueNotification = EventNotification::query()->create([
            'event_id' => $event->id,
            'registration_id' => $reg->id,
            'recipient_email' => 'student@example.com',
            'type' => NotificationType::RegistrationConfirmed->value,
            'subject' => 'Registration Confirmation',
            'body' => 'Your ticket is attached.',
            'status' => NotificationStatus::Failed->value,
            'retry_count' => 1,
            'error' => 'Temporary transport exception',
            'idempotency_key' => 'test-retry-notif-' . Str::uuid(),
            'scheduled_at' => now()->subMinute(),
        ]);

        // Run retry command with --execute
        $this->artisan('ems:notifications:retry', ['--execute' => true])
            ->assertExitCode(0);

        // Future notification was NOT touched or dispatched early
        $this->assertSame(NotificationStatus::Scheduled, $futureNotification->fresh()->status);

        // Due failed notification was retried and sent
        $this->assertSame(NotificationStatus::Sent, $dueNotification->fresh()->status);
    }

    /**
     * Scenario E: A transient failure scheduled for retry must NOT trigger an admin failure alert.
     */
    public function test_send_notification_job_does_not_alert_admin_on_transient_retry(): void
    {
        config([
            'ems.notifications.enabled' => true,
            'ems.notifications.admin_alert_recipients' => 'admin@example.com',
            'queue.default' => 'database',
        ]);

        $event = $this->publishedEvent();
        $reg = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_email' => 'attendee@example.com',
        ]);

        $notification = EventNotification::query()->create([
            'event_id' => $event->id,
            'registration_id' => $reg->id,
            'recipient_email' => 'attendee@example.com',
            'type' => NotificationType::RegistrationConfirmed->value,
            'subject' => 'Registration Confirmation',
            'body' => 'Confirmation content',
            'status' => NotificationStatus::Pending->value,
            'idempotency_key' => 'test-alert-dedup-' . Str::uuid(),
        ]);

        $throwingMailer = new class($this->app->make('mail.manager')) extends \Illuminate\Support\Testing\Fakes\MailFake {
            public function send($mailable, array $dependencies = [], $queue = null) {
                throw new \RuntimeException('Connection dropped by SMTP host');
            }
        };
        Mail::swap($throwingMailer);

        // When dispatched synchronously with an async connection available,
        // the job handles the transient failure by queuing a background retry.
        SendEventNotificationJob::dispatchSync($notification->id);

        // Notification queue_status is marked retry_pending for background worker retry
        $notification->refresh();
        $this->assertSame('retry_pending', $notification->queue_status);
        $this->assertSame(NotificationStatus::Failed, $notification->status);

        // Admin alert was NOT sent for this transient retry attempt
        $this->assertNull($notification->alert_sent_at);
    }
}
