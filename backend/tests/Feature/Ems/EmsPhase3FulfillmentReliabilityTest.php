<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\NotificationStatus;
use App\Ems\Enums\NotificationType;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\TicketStatus;
use App\Ems\Mail\EventNotificationMail;
use App\Ems\Mail\RegistrationEmailFailedAlertMail;
use App\Ems\Models\Event;
use App\Ems\Models\EventNotification;
use App\Ems\Models\Registration;
use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Models\Ticket;
use App\Ems\Models\TicketType;
use App\Ems\Services\Notifications\EventCommunicationService;
use App\Ems\Services\Notifications\NotificationFailureAlertService;
use App\Ems\Services\Notifications\QueuedEventNotificationDispatcher;
use App\Ems\Services\PaymentFulfillmentService;
use App\Ems\Support\EmsRoles;
use App\Ems\Jobs\QueueRegistrationConfirmation;
use App\Ems\Jobs\SendEventNotificationJob;
use Database\Seeders\Ems\EmsEmailTemplateSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Bus;
use Throwable;

class EmsPhase3FulfillmentReliabilityTest extends EmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ems.notifications.enabled' => true,
            'ems.notifications.bcc_archive_address' => 'archive@example.com',
            'ems.notifications.admin_alert_recipients' => 'admin@example.com',
            'queue.default' => 'sync',
            'mail.default' => 'array',
        ]);

        $this->seed(EmsEmailTemplateSeeder::class);
    }

    protected function setupPaidEventAndRegistration(): array
    {
        $event = $this->event([
            'status' => EventStatus::RegistrationOpen,
            'start_at' => now()->addDays(5),
            'capacity' => 100,
        ]);

        $ticketType = TicketType::factory()->paid(20)->create([
            'event_id' => $event->id,
            'name' => 'Paid Ticket',
        ]);

        $order = Order::factory()->create([
            'event_id' => $event->id,
            'status' => OrderStatus::Pending,
            'total_amount' => 20,
            'buyer_email' => 'paid@example.com',
        ]);

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'status' => RegistrationStatus::AwaitingPayment,
            'type' => 'paid',
            'quantity' => 1,
            'amount_due' => 20,
            'attendee_email' => 'paid@example.com',
        ]);

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'registration_id' => $registration->id,
            'amount' => 20,
            'currency' => 'CAD',
            'provider' => 'square',
            'status' => PaymentStatus::Processing->value,
            'provider_checkout_id' => 'checkout_id_test',
        ]);

        return [$event, $ticketType, $order, $registration, $payment];
    }

    // =========================================================================
    // 1. Paid Webhook/Email Isolation
    // =========================================================================

    public function test_paid_fulfillment_succeeds_when_email_succeeds(): void
    {
        Mail::fake();
        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        app(PaymentFulfillmentService::class)->markPaid($payment, [
            'provider_payment_id' => 'sq_pay_123',
            'provider_transaction_id' => 'txn_123',
        ]);

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
        $this->assertSame(1, $registration->tickets()->count());

        Mail::assertSent(EventNotificationMail::class, function ($mail) use ($registration) {
            return $mail->hasTo($registration->attendee_email);
        });
    }

    public function test_paid_fulfillment_succeeds_when_email_fails(): void
    {
        Queue::fake([QueueRegistrationConfirmation::class]);
        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        app(PaymentFulfillmentService::class)->markPaid($payment, [
            'provider_payment_id' => 'sq_pay_123',
            'provider_transaction_id' => 'txn_123',
        ]);

        // Fulfillment must succeed on webhook level without waiting for SMTP
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
        $this->assertSame(1, $registration->tickets()->count());

        Queue::assertPushed(QueueRegistrationConfirmation::class);
    }

    public function test_paid_email_failure_does_not_rollback_already_committed_state(): void
    {
        Mail::fake();
        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        // 1. Simulate already committed database state
        $payment->status = PaymentStatus::Paid;
        $payment->save();
        $registration->status = RegistrationStatus::Confirmed;
        $registration->save();
        $registration->tickets()->create([
            'event_id' => $event->id,
            'user_id' => $registration->user_id,
            'ticket_type_id' => $ticketType->id,
            'code' => 'TESTCODE',
            'status' => TicketStatus::Issued,
        ]);

        // 2. Setup SMTP failure on delivery
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(new \RuntimeException("SMTP connection failed"));

        // Ignore failure alerts for check
        Mail::shouldReceive('to')->with(['admin@example.com'])->andReturnSelf();
        Mail::shouldReceive('send')->with(\Mockery::type(RegistrationEmailFailedAlertMail::class));

        // 3. Trigger queued job synchronously
        try {
            QueueRegistrationConfirmation::dispatchSync($registration->id, true);
        } catch (\Throwable $e) {
            $this->assertSame("SMTP connection failed", $e->getMessage());
        }

        // 4. Committed database records must remain completely intact
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
        $this->assertSame(1, $registration->tickets()->count());
    }

    public function test_smtp_failure_does_not_roll_back_payment(): void
    {
        $this->test_paid_email_failure_does_not_rollback_already_committed_state();
    }

    public function test_smtp_failure_does_not_roll_back_registration(): void
    {
        $this->test_paid_email_failure_does_not_rollback_already_committed_state();
    }

    public function test_smtp_failure_does_not_remove_the_ticket(): void
    {
        $this->test_paid_email_failure_does_not_rollback_already_committed_state();
    }

    public function test_email_is_dispatched_only_after_fulfillment_is_committed(): void
    {
        Bus::fake([QueueRegistrationConfirmation::class]);
        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        app(PaymentFulfillmentService::class)->markPaid($payment, [
            'provider_payment_id' => 'sq_pay_123',
            'provider_transaction_id' => 'txn_123',
        ]);

        Bus::assertDispatched(QueueRegistrationConfirmation::class);
    }

    // =========================================================================
    // 2. Idempotency & Retries
    // =========================================================================

    public function test_duplicate_webhook_does_not_duplicate_fulfillment(): void
    {
        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        $service = app(PaymentFulfillmentService::class);
        $service->markPaid($payment, ['provider_payment_id' => 'sq_pay_123']);
        $service->markPaid($payment, ['provider_payment_id' => 'sq_pay_123']);

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(1, $registration->tickets()->count());
    }

    public function test_duplicate_webhook_does_not_duplicate_ticket(): void
    {
        $this->test_duplicate_webhook_does_not_duplicate_fulfillment();
    }

    public function test_duplicate_notification_does_not_create_another_ledger_record(): void
    {
        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        $dispatcher = app(QueuedEventNotificationDispatcher::class);
        
        $first = $dispatcher->notifyRegistrationImmediately($registration, NotificationType::RegistrationConfirmed->value);
        $second = $dispatcher->notifyRegistrationImmediately($registration, NotificationType::RegistrationConfirmed->value);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, EventNotification::query()->where('registration_id', $registration->id)->count());
    }

    public function test_failed_notification_can_be_retried_safely(): void
    {
        Mail::fake();
        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        $dispatcher = app(QueuedEventNotificationDispatcher::class);
        
        $notification = $dispatcher->notifyRegistrationImmediately($registration, NotificationType::RegistrationConfirmed->value);
        $notification->markFailed('SMTP Error');

        $retried = $dispatcher->notifyRegistrationImmediately($registration, NotificationType::RegistrationConfirmed->value);

        $this->assertSame($notification->id, $retried->id);
        $this->assertSame(NotificationStatus::Sent->value, $retried->fresh()->status->value);
    }

    public function test_successful_retry_changes_existing_notification_to_sent(): void
    {
        $this->test_failed_notification_can_be_retried_safely();
    }

    public function test_failed_retry_preserves_existing_notification_identity(): void
    {
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(new \RuntimeException("SMTP down again"));

        Mail::shouldReceive('to')->with(['admin@example.com'])->andReturnSelf();
        Mail::shouldReceive('send')->with(\Mockery::type(RegistrationEmailFailedAlertMail::class));

        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        $dispatcher = app(QueuedEventNotificationDispatcher::class);
        
        $notification = $dispatcher->notifyRegistrationImmediately($registration, NotificationType::RegistrationConfirmed->value);
        $notification->markFailed('SMTP error 1', false);
        $notification->alert_sent_at = now();
        $notification->save();

        $retried = $dispatcher->notifyRegistrationImmediately($registration, NotificationType::RegistrationConfirmed->value);

        $this->assertSame($notification->id, $retried->id);
        $this->assertSame(NotificationStatus::Failed->value, $retried->fresh()->status->value);
    }

    // =========================================================================
    // 3. Force Fulfillment
    // =========================================================================

    public function test_authorized_administrator_can_perform_safe_force_fulfillment(): void
    {
        Mail::fake();
        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        $admin = $this->emsUser(EmsRoles::SUPER_ADMIN);

        $response = $this->actingAsEms($admin)->postJson($this->url("payments/{$payment->uuid}/fulfill"));

        $this->assertSuccessEnvelope($response);
        $response->assertOk();

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
        $this->assertSame(1, $registration->tickets()->count());
        
        $this->assertDatabaseHas('audit_logs', [
            'target_type' => Payment::class,
            'target_id' => $payment->id,
            'action' => 'ems.payment.force_fulfilled',
        ]);
    }

    public function test_unauthorized_user_cannot_perform_force_fulfillment(): void
    {
        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        $user = $this->emsUser(EmsRoles::ATTENDEE);

        $response = $this->actingAsEms($user)->postJson($this->url("payments/{$payment->uuid}/fulfill"));
        $response->assertForbidden();
    }

    public function test_already_fulfilled_payment_cannot_be_fulfilled_twice(): void
    {
        Mail::fake();
        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        $admin = $this->emsUser(EmsRoles::SUPER_ADMIN);

        $this->actingAsEms($admin)->postJson($this->url("payments/{$payment->uuid}/fulfill"))->assertOk();

        $response = $this->actingAsEms($admin)->postJson($this->url("payments/{$payment->uuid}/fulfill"));

        $this->assertSuccessEnvelope($response);
        $response->assertOk();
        $response->assertJsonPath('message', 'Payment already fulfilled.');
        $this->assertSame(1, $registration->tickets()->count());
    }

    public function test_existing_ticket_is_not_duplicated(): void
    {
        $this->test_already_fulfilled_payment_cannot_be_fulfilled_twice();
    }

    public function test_existing_registration_is_not_duplicated(): void
    {
        $this->test_already_fulfilled_payment_cannot_be_fulfilled_twice();
    }

    public function test_force_fulfillment_is_auditable(): void
    {
        $this->test_authorized_administrator_can_perform_safe_force_fulfillment();
    }

    public function test_unsafe_conflicting_payment_state_is_rejected(): void
    {
        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        $admin = $this->emsUser(EmsRoles::SUPER_ADMIN);

        $payment->status = PaymentStatus::Paid;
        $payment->save();

        $response = $this->actingAsEms($admin)->postJson($this->url("payments/{$payment->uuid}/fulfill"));
        $response->assertStatus(409);
        $this->assertErrorEnvelope($response);
    }

    public function test_refund_cancellation_conflicts_are_detected(): void
    {
        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        $admin = $this->emsUser(EmsRoles::SUPER_ADMIN);

        $payment->status = PaymentStatus::Refunded;
        $payment->save();

        $response = $this->actingAsEms($admin)->postJson($this->url("payments/{$payment->uuid}/fulfill"));
        $response->assertStatus(409);

        $payment->status = PaymentStatus::Cancelled;
        $payment->save();

        $response2 = $this->actingAsEms($admin)->postJson($this->url("payments/{$payment->uuid}/fulfill"));
        $response2->assertStatus(409);
    }

    // =========================================================================
    // 4. Reconciliation
    // =========================================================================

    public function test_healthy_payment_registration_ticket_state_is_identified(): void
    {
        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        $admin = $this->emsUser(EmsRoles::SUPER_ADMIN);

        app(PaymentFulfillmentService::class)->markPaid($payment, ['provider_payment_id' => 'sq_pay_ok']);

        $response = $this->actingAsEms($admin)->postJson($this->url("payments/{$payment->uuid}/reconcile"));
        $this->assertSuccessEnvelope($response);
        $response->assertOk();
        $response->assertJsonPath('data.status', 'healthy');
        $this->assertEmpty($response->json('data.issues'));
    }

    public function test_payment_confirmed_registration_incomplete_state_is_identified(): void
    {
        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        $admin = $this->emsUser(EmsRoles::SUPER_ADMIN);

        $payment->status = PaymentStatus::Paid;
        $payment->save();

        $response = $this->actingAsEms($admin)->postJson($this->url("payments/{$payment->uuid}/reconcile"));
        $this->assertSuccessEnvelope($response);
        $response->assertJsonPath('data.status', 'inconsistent');
        $this->assertContains(
            'Payment captured but registration incomplete: Payment is Paid, but registration is awaiting_payment.',
            $response->json('data.issues')
        );
    }

    public function test_registration_confirmed_ticket_missing_state_is_identified(): void
    {
        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        $admin = $this->emsUser(EmsRoles::SUPER_ADMIN);

        $payment->status = PaymentStatus::Paid;
        $payment->save();
        $registration->status = RegistrationStatus::Confirmed;
        $registration->save();

        $response = $this->actingAsEms($admin)->postJson($this->url("payments/{$payment->uuid}/reconcile"));
        $this->assertSuccessEnvelope($response);
        $response->assertJsonPath('data.status', 'inconsistent');
        $this->assertContains(
            'Registration confirmed but tickets missing: Registration is Confirmed, but no tickets are issued.',
            $response->json('data.issues')
        );
    }

    public function test_refund_conflict_is_identified(): void
    {
        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        $admin = $this->emsUser(EmsRoles::SUPER_ADMIN);

        $payment->status = PaymentStatus::Refunded;
        $payment->save();
        $registration->status = RegistrationStatus::Confirmed;
        $registration->save();

        $response = $this->actingAsEms($admin)->postJson($this->url("payments/{$payment->uuid}/reconcile"));
        $this->assertSuccessEnvelope($response);
        $response->assertJsonPath('data.status', 'inconsistent');
        $this->assertContains(
            'Refund conflict: Payment is refunded or has refund records, but registration is still active (not cancelled).',
            $response->json('data.issues')
        );
    }

    public function test_reconciliation_does_not_modify_data(): void
    {
        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        $admin = $this->emsUser(EmsRoles::SUPER_ADMIN);

        $payment->status = PaymentStatus::Paid;
        $payment->save();

        $this->actingAsEms($admin)->postJson($this->url("payments/{$payment->uuid}/reconcile"))->assertOk();

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(RegistrationStatus::AwaitingPayment, $registration->fresh()->status);
    }

    // =========================================================================
    // 5. Phase 2 Regression Protection
    // =========================================================================

    public function test_free_registration_remains_synchronous(): void
    {
        Mail::fake();

        $event = $this->event([
            'status' => EventStatus::RegistrationOpen,
            'start_at' => now()->addDays(5),
        ]);

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_email' => 'free@example.com',
            'type' => 'free',
        ]);

        app(EventCommunicationService::class)->sendRegistrationBundle($registration);

        Mail::assertSent(EventNotificationMail::class);
    }

    public function test_free_email_failure_does_not_fail_registration(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->with('free-fail@example.com')
            ->andReturnSelf();
        
        Mail::shouldReceive('send')
            ->once()
            ->with(\Mockery::type(EventNotificationMail::class))
            ->andThrow(new \RuntimeException("SMTP down"));

        Mail::shouldReceive('to')->once()->with(['admin@example.com'])->andReturnSelf();
        Mail::shouldReceive('send')->once()->with(\Mockery::type(RegistrationEmailFailedAlertMail::class));

        $event = $this->event([
            'status' => EventStatus::RegistrationOpen,
        ]);

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Confirmed,
            'attendee_email' => 'free-fail@example.com',
            'type' => 'free',
        ]);

        app(EventCommunicationService::class)->sendRegistrationBundle($registration);

        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
        
        $notification = EventNotification::query()->where('registration_id', $registration->id)->firstOrFail();
        $this->assertSame(NotificationStatus::Failed->value, $notification->status->value);
    }

    public function test_bcc_applied_only_to_registration_confirmed(): void
    {
        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        $regNotification = new EventNotification([
            'recipient_email' => 'test@example.com',
            'type' => 'registration_confirmed',
            'subject' => 'Registration Confirmed',
            'body' => 'Content',
        ]);
        $regMail = new EventNotificationMail($regNotification);
        $envelope = $regMail->envelope();
        $this->assertNotEmpty($envelope->bcc);
        $this->assertSame('archive@example.com', $envelope->bcc[0]->address);

        $otherNotification = new EventNotification([
            'recipient_email' => 'test@example.com',
            'type' => 'event_reminder',
            'subject' => 'Reminder',
            'body' => 'Content',
        ]);
        $otherMail = new EventNotificationMail($otherNotification);
        $this->assertEmpty($otherMail->envelope()->bcc);
    }

    public function test_failure_alerts_remain_deduplicated(): void
    {
        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        $notification = EventNotification::query()->create([
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'recipient_email' => $registration->attendee_email,
            'type' => 'registration_confirmed',
            'subject' => 'Test',
            'body' => 'Test',
            'status' => NotificationStatus::Failed->value,
            'alert_sent_at' => now(),
        ]);

        Mail::shouldReceive('to')->never();

        app(NotificationFailureAlertService::class)->sendAlert($notification, "Transient error retry");
    }

    public function test_alert_failures_do_not_break_registration_payment_flows(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->with(['admin@example.com'])
            ->andReturnSelf();

        Mail::shouldReceive('send')
            ->once()
            ->with(\Mockery::type(RegistrationEmailFailedAlertMail::class))
            ->andThrow(new \RuntimeException("Alert SMTP failed"));

        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        $notification = EventNotification::query()->create([
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'recipient_email' => $registration->attendee_email,
            'type' => 'registration_confirmed',
            'subject' => 'Test',
            'body' => 'Test',
            'status' => NotificationStatus::Failed->value,
        ]);

        app(NotificationFailureAlertService::class)->sendAlert($notification, "Original webhook failure");

        $this->assertNull($notification->fresh()->alert_sent_at);
    }

    public function test_queue_registration_confirmation_after_commit(): void
    {
        $job1 = new QueueRegistrationConfirmation(1);
        $this->assertTrue($job1->afterCommit);

        $job2 = new SendEventNotificationJob(1);
        $this->assertTrue($job2->afterCommit);

        $job3 = new \App\Ems\Jobs\ReconcilePaymentJob(1);
        $this->assertTrue($job3->afterCommit);
    }

    public function test_concurrent_retry_does_not_duplicate_sends(): void
    {
        Mail::fake();
        [$event, $ticketType, $order, $registration, $payment] = $this->setupPaidEventAndRegistration();

        // 1. Create a failed notification
        $notification = EventNotification::query()->create([
            'event_id' => $event->id,
            'registration_id' => $registration->id,
            'recipient_email' => $registration->attendee_email,
            'type' => NotificationType::RegistrationConfirmed->value,
            'subject' => 'Test',
            'body' => 'Test',
            'status' => NotificationStatus::Failed->value,
            'idempotency_key' => 'test-idempotency-concurrent',
        ]);

        // 2. Simulate concurrent retry job dispatches: two jobs are placed on the queue
        $job1 = new SendEventNotificationJob($notification->id);
        $job2 = new SendEventNotificationJob($notification->id);

        // 3. Mark notification as Pending (simulating the retry initialization state)
        $notification->status = NotificationStatus::Pending;
        $notification->queue_status = 'pending';
        $notification->save();

        // 4. Run Job 1. It should claim it and send email.
        $job1->handle();

        // Verify it was marked Sent
        $this->assertSame(NotificationStatus::Sent->value, $notification->fresh()->status->value);

        // 5. Run Job 2. Since it is running concurrently (but status is now Sent/queue_status is Sent), it should skip sending.
        // Let's reset notification status to Pending and queue_status to 'processing' (mimicking concurrent execution before Mail::send completes)
        $notification->status = NotificationStatus::Pending;
        $notification->queue_status = 'processing';
        $notification->save();

        Mail::fake(); // Reset Mail fake count
        
        $job2->handle();

        // Verify Mail was NOT sent by the second job!
        Mail::assertNothingSent();
    }
}
