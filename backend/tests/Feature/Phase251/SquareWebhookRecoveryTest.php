<?php

namespace Tests\Feature\Phase251;

use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\WebhookEventStatus;
use App\Ems\Jobs\ProcessSquareWebhookJob;
use App\Ems\Jobs\QueueRegistrationConfirmation;
use App\Ems\Jobs\RecoverSquareWebhookEventsJob;
use App\Ems\Models\Payment;
use App\Ems\Models\Ticket;
use App\Ems\Models\WebhookEvent;
use App\Ems\Services\PaymentFulfillmentService;
use App\Ems\Services\Square\SquareReconciliationService;
use App\Platform\Models\PlatformAlert;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

class SquareWebhookRecoveryTest extends SquareFlowTestCase
{
    public function test_claim_is_exclusive_and_stale_processing_lease_is_reclaimable(): void
    {
        $row = $this->storedEvent('evt_claim', WebhookEventStatus::Received);

        $this->assertTrue(WebhookEvent::claimForProcessing($row->id));
        $this->assertFalse(WebhookEvent::claimForProcessing($row->id), 'a fresh processing lease must not be claimed twice');
        $this->assertSame(1, (int) $row->fresh()->retry_count);

        $row->fresh()->forceFill(['last_attempt_at' => now()->subSeconds(WebhookEvent::processingLeaseSeconds() + 5)])->save();
        $this->assertTrue(WebhookEvent::claimForProcessing($row->id));
        $this->assertSame(2, (int) $row->fresh()->retry_count);

        $row->fresh()->forceFill(['status' => WebhookEventStatus::Processed->value])->save();
        $this->assertFalse(WebhookEvent::claimForProcessing($row->id));
    }

    public function test_crash_after_receipt_before_processing_is_recovered(): void
    {
        $payment = $this->pendingPayment('received@example.com');
        $row = $this->storedEvent('evt_received', WebhookEventStatus::Received, $this->paidPayload($payment), createdMinutesAgo: 10);

        (new RecoverSquareWebhookEventsJob())->handle();

        $this->assertSame(WebhookEventStatus::Processed->value, $row->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(1, Ticket::query()->where('registration_id', $payment->registration_id)->count());
    }

    public function test_freshly_received_row_is_left_for_its_own_queue_job(): void
    {
        $payment = $this->pendingPayment('fresh@example.com');
        $row = $this->storedEvent('evt_fresh', WebhookEventStatus::Received, $this->paidPayload($payment), createdMinutesAgo: 0);

        (new RecoverSquareWebhookEventsJob())->handle();

        $this->assertSame(WebhookEventStatus::Received->value, $row->fresh()->status);
    }

    public function test_crash_mid_processing_is_recovered_only_after_lease_expiry(): void
    {
        $payment = $this->pendingPayment('stuck@example.com');
        $row = $this->storedEvent('evt_stuck', WebhookEventStatus::Processing, $this->paidPayload($payment));
        $row->forceFill(['last_attempt_at' => now()->subSeconds(30), 'retry_count' => 1])->save();

        (new RecoverSquareWebhookEventsJob())->handle();
        $this->completedWebhook($payment, 'evt_stuck')->assertOk();
        $this->assertSame(WebhookEventStatus::Processing->value, $row->fresh()->status, 'live lease must not be stolen');
        $this->assertSame(PaymentStatus::Processing, $payment->fresh()->status);

        $row->fresh()->forceFill(['last_attempt_at' => now()->subMinutes(10)])->save();
        (new RecoverSquareWebhookEventsJob())->handle();

        $this->assertSame(WebhookEventStatus::Processed->value, $row->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_worker_does_not_process_a_row_another_worker_holds(): void
    {
        $payment = $this->pendingPayment('worker@example.com');
        $row = $this->storedEvent('evt_worker', WebhookEventStatus::Processing, $this->paidPayload($payment));
        $row->forceFill(['last_attempt_at' => now()->subSeconds(10), 'retry_count' => 1])->save();

        app(\App\Ems\Services\SquareWebhookService::class)->processRecord($row->fresh());
        (new ProcessSquareWebhookJob($row->id))->handle(app(\App\Ems\Services\SquareWebhookService::class));

        $this->assertSame(PaymentStatus::Processing, $payment->fresh()->status);
        $this->assertSame(1, (int) $row->fresh()->retry_count);
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_duplicate_delivery_after_lease_expiry_reprocesses_stuck_row(): void
    {
        $payment = $this->pendingPayment('redeliver@example.com');
        $row = $this->storedEvent('evt_redeliver', WebhookEventStatus::Processing, $this->paidPayload($payment));
        $row->forceFill(['last_attempt_at' => now()->subMinutes(10), 'retry_count' => 1])->save();

        $this->completedWebhook($payment, 'evt_redeliver')->assertOk();

        $this->assertSame(WebhookEventStatus::Processed->value, $row->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_crash_after_business_effect_before_marking_processed_does_not_duplicate_effects(): void
    {
        $payment = $this->pendingPayment('effect@example.com');
        $row = $this->storedEvent('evt_effect', WebhookEventStatus::Processing, $this->paidPayload($payment));

        app(PaymentFulfillmentService::class)->markPaid($payment, ['provider_payment_id' => 'sq_pay_'.$payment->id]);
        $this->assertSame(1, Ticket::query()->where('registration_id', $payment->registration_id)->count());
        $row->forceFill(['last_attempt_at' => now()->subMinutes(10), 'retry_count' => 1])->save();

        Bus::fake([QueueRegistrationConfirmation::class]);
        (new RecoverSquareWebhookEventsJob())->handle();

        $this->assertSame(WebhookEventStatus::Processed->value, $row->fresh()->status);
        $this->assertSame(1, Ticket::query()->where('registration_id', $payment->registration_id)->count());
        Bus::assertNotDispatched(QueueRegistrationConfirmation::class);
    }

    public function test_duplicate_delivery_of_processed_event_is_ignored(): void
    {
        $payment = $this->pendingPayment('dup@example.com');
        $this->completedWebhook($payment, 'evt_dup')->assertOk();

        Bus::fake([QueueRegistrationConfirmation::class]);
        $this->completedWebhook($payment->fresh(), 'evt_dup')->assertOk();

        $this->assertSame(1, WebhookEvent::query()->where('event_id', 'evt_dup')->count());
        $this->assertSame(1, (int) WebhookEvent::query()->where('event_id', 'evt_dup')->value('retry_count'));
        $this->assertSame(1, Ticket::query()->where('registration_id', $payment->registration_id)->count());
        Bus::assertNotDispatched(QueueRegistrationConfirmation::class);
    }

    public function test_invalid_signature_is_rejected_without_persisting(): void
    {
        $payment = $this->pendingPayment('forged@example.com');

        $this->postWebhook($this->paidPayload($payment, 'evt_forged'), 'wrong-secret')->assertStatus(401);

        $this->assertSame(0, WebhookEvent::query()->count());
        $this->assertSame(PaymentStatus::Processing, $payment->fresh()->status);
    }

    public function test_retries_are_bounded_and_permanent_failure_is_visible(): void
    {
        $job = new ProcessSquareWebhookJob(1);
        $this->assertSame(5, $job->tries);
        $this->assertNotEmpty($job->backoff);

        $row = $this->storedEvent('evt_doomed', WebhookEventStatus::Failed);
        $row->forceFill(['retry_count' => 3, 'last_attempt_at' => now()->subMinutes(5)])->save();

        (new ProcessSquareWebhookJob($row->id))->failed(new \RuntimeException('provider exploded'));

        $row = $row->fresh();
        $this->assertSame(WebhookEventStatus::Failed->value, $row->status);
        $this->assertStringStartsWith('Permanently failed after 3 attempts', (string) $row->failure_reason);
        $this->assertGreaterThanOrEqual(WebhookEvent::maxAttempts(), (int) $row->retry_count);
        $this->assertSame(1, PlatformAlert::query()->where('application', 'ems')->count());

        $row->forceFill(['last_attempt_at' => now()->subDays(2)])->save();
        $this->assertSame(0, WebhookEvent::query()->awaitingRecovery()->count(), 'exhausted rows stay failed for staff');

        Http::fake(['*' => Http::response(['payments' => [], 'objects' => []], 200)]);
        app(SquareReconciliationService::class)->reconcile();
        $this->assertSame((int) $row->retry_count, (int) $row->fresh()->retry_count, 'reconciliation must not retry exhausted rows');
    }

    public function test_failed_row_with_attempts_remaining_is_retried_after_cool_down(): void
    {
        $payment = $this->pendingPayment('cooldown@example.com');
        $row = $this->storedEvent('evt_cooldown', WebhookEventStatus::Failed, $this->paidPayload($payment));
        $row->forceFill(['retry_count' => 2, 'last_attempt_at' => now()->subMinutes(5)])->save();

        (new RecoverSquareWebhookEventsJob())->handle();
        $this->assertSame(WebhookEventStatus::Failed->value, $row->fresh()->status);

        $row->fresh()->forceFill(['last_attempt_at' => now()->subMinutes(61)])->save();
        (new RecoverSquareWebhookEventsJob())->handle();

        $this->assertSame(WebhookEventStatus::Processed->value, $row->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_recovery_job_is_scheduled(): void
    {
        $descriptions = collect(app(Schedule::class)->events())->map->description;

        $this->assertContains('EMS: Recover stuck or failed Square webhooks', $descriptions);
    }

    private function pendingPayment(string $email): Payment
    {
        [$event, $ticketType] = $this->paidEvent();
        $this->startCheckout($event, $ticketType, $email)->assertCreated();

        return $this->paymentFor($email);
    }

    private function paidPayload(Payment $payment, ?string $eventId = null): array
    {
        $payment->loadMissing('order');

        return [
            'event_id' => $eventId ?? 'evt_payload_'.$payment->id,
            'type' => 'payment.updated',
            'data' => ['object' => ['payment' => [
                'id' => 'sq_pay_'.$payment->id,
                'status' => 'COMPLETED',
                'order_id' => $payment->provider_order_id,
                'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                'reference_id' => $payment->order->reference,
            ]]],
        ];
    }

    private function storedEvent(string $eventId, WebhookEventStatus $status, array $payload = ['type' => 'payment.updated'], int $createdMinutesAgo = 0): WebhookEvent
    {
        $row = new WebhookEvent();
        $row->provider = 'square';
        $row->event_id = $eventId;
        $row->event_type = 'payment.updated';
        $row->status = $status->value;
        $row->payload = $payload;
        $row->retry_count = 0;
        $row->save();

        if ($createdMinutesAgo > 0) {
            $row->forceFill(['created_at' => now()->subMinutes($createdMinutesAgo)])->save();
        }

        return $row->fresh();
    }
}
