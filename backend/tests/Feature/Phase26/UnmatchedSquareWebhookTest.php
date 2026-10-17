<?php

namespace Tests\Feature\Phase26;

use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\WebhookEventStatus;
use App\Ems\Jobs\RecoverSquareWebhookEventsJob;
use App\Ems\Models\Ticket;
use App\Ems\Models\WebhookEvent;
use App\Ems\Services\Square\SquareReconciliationService;
use App\Models\ApplicationAccess;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Platform\Models\PlatformAlert;
use Illuminate\Support\Str;
use Tests\Feature\Phase251\SquareFlowTestCase;

/**
 * Bounded retries for Square webhooks that cannot be matched yet. All Square
 * HTTP traffic is faked; this is not provider verification.
 */
class UnmatchedSquareWebhookTest extends SquareFlowTestCase
{
    private function earlyWebhook(string $eventId, string $squareOrderId, string $type = 'payment.updated'): void
    {
        $this->postWebhook([
            'event_id' => $eventId,
            'type' => $type,
            'data' => ['object' => ['payment' => [
                'id' => 'sq_pay_'.$eventId,
                'status' => 'COMPLETED',
                'order_id' => $squareOrderId,
                'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
            ]]],
        ])->assertSuccessful();
    }

    private function row(string $eventId): WebhookEvent
    {
        return WebhookEvent::query()->where('event_id', $eventId)->firstOrFail();
    }

    private function recover(): void
    {
        (new RecoverSquareWebhookEventsJob)->handle();
    }

    private function exhaust(string $eventId): void
    {
        for ($i = 0; $i < WebhookEvent::unmatchedMaxAttempts() + 2; $i++) {
            $this->travel(13)->hours();
            $this->recover();
        }
    }

    private function admin(array $permissions): User
    {
        $role = Role::query()->create(['slug' => 'p26-'.Str::random(6), 'name' => 'P26 '.Str::random(4)]);
        foreach ($permissions as $slug) {
            $role->permissions()->attach(Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'System'])->id);
        }
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->roles()->attach($role->id);
        ApplicationAccess::query()->create(['user_id' => $user->id, 'application' => 'admin-portal']);

        return $user;
    }

    public function test_unsupported_event_type_is_kept_but_never_retried(): void
    {
        $this->postWebhook(['event_id' => 'evt_cust', 'type' => 'customer.created', 'data' => ['object' => ['customer' => ['id' => 'c1']]]])
            ->assertSuccessful();

        $row = $this->row('evt_cust');
        $this->assertSame(WebhookEventStatus::Ignored->value, $row->status);
        $this->assertNull($row->next_retry_at);

        $this->travel(2)->days();
        $this->recover();
        app(SquareReconciliationService::class)->reconcile();

        $this->assertSame(1, (int) $row->fresh()->retry_count);
        $this->assertSame(WebhookEventStatus::Ignored->value, $row->fresh()->status);
    }

    public function test_webhook_arriving_before_its_checkout_reconciles_once_the_record_exists(): void
    {
        [$event, $ticketType] = $this->paidEvent();
        $this->earlyWebhook('evt_early', 'sq_order_1');

        $row = $this->row('evt_early');
        $this->assertSame(WebhookEventStatus::Unmatched->value, $row->status);
        $this->assertNotNull($row->next_retry_at);

        $this->startCheckout($event, $ticketType, 'buyer@example.com')->assertCreated();
        $payment = $this->paymentFor('buyer@example.com');
        $this->assertSame('sq_order_1', $payment->provider_order_id);

        $this->recover();
        $this->assertSame(1, (int) $row->fresh()->retry_count, 'backoff not yet elapsed');
        $this->assertNotSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(0, Ticket::query()->count());

        $this->travel(6)->minutes();
        $this->recover();

        $this->assertSame(WebhookEventStatus::Processed->value, $row->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(1, Ticket::query()->count());

        // Square redelivers the same event: idempotent.
        $this->earlyWebhook('evt_early', 'sq_order_1');
        $this->recover();
        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_unmatched_retries_are_bounded_then_parked_for_review_with_an_alert(): void
    {
        $this->earlyWebhook('evt_orphan', 'sq_order_missing');

        $this->exhaust('evt_orphan');

        $row = $this->row('evt_orphan');
        $this->assertSame(WebhookEventStatus::NeedsReview->value, $row->status);
        $this->assertSame(WebhookEvent::unmatchedMaxAttempts(), (int) $row->retry_count);
        $this->assertNotNull($row->payload, 'event metadata retained for investigation');
        $this->assertSame(1, PlatformAlert::query()->where('application', 'ems')->where('title', 'Square webhook needs review')->count());

        $this->travel(3)->days();
        $this->recover();
        app(SquareReconciliationService::class)->reconcile();
        $this->assertSame(WebhookEvent::unmatchedMaxAttempts(), (int) $row->fresh()->retry_count, 'no automatic retries after parking');
    }

    public function test_reconciliation_respects_unmatched_backoff(): void
    {
        $this->earlyWebhook('evt_backoff', 'sq_order_missing');

        app(SquareReconciliationService::class)->reconcile();
        $this->assertSame(1, (int) $this->row('evt_backoff')->retry_count);

        $this->travel(6)->minutes();
        app(SquareReconciliationService::class)->reconcile();
        $this->assertSame(2, (int) $this->row('evt_backoff')->retry_count);
    }

    public function test_operator_requeue_requires_manage_permission_is_audited_and_reconciles(): void
    {
        [$event, $ticketType] = $this->paidEvent();
        $this->earlyWebhook('evt_late', 'sq_order_1');
        $this->exhaust('evt_late');
        $row = $this->row('evt_late');
        $this->assertSame(WebhookEventStatus::NeedsReview->value, $row->status);

        $this->startCheckout($event, $ticketType, 'late@example.com')->assertCreated();
        $url = "/api/v1/admin/systems/ems/webhooks/{$row->uuid}/requeue";

        $this->actingAs($this->admin(['system.view']))->postJson($url)->assertForbidden();
        $this->assertSame(WebhookEventStatus::NeedsReview->value, $row->fresh()->status);

        $operator = $this->admin(['system.view', 'system.manage']);
        $this->actingAs($operator)->postJson($url)->assertStatus(202);

        $this->assertSame(WebhookEventStatus::Processed->value, $row->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $this->paymentFor('late@example.com')->status);
        $this->assertSame(1, AuditLog::query()->where('action', 'ems.webhook.requeued')->where('user_id', $operator->id)->count());

        $this->actingAs($operator)->postJson($url)->assertStatus(409);
        $this->assertSame(1, Ticket::query()->count());

        $summary = $this->actingAs($operator)->getJson('/api/v1/admin/systems/ems/webhooks')->assertOk();
        $this->assertSame(0, $summary->json('webhooks.needs_review_count'));
    }

    public function test_requeue_cannot_steal_a_row_being_processed(): void
    {
        $this->earlyWebhook('evt_busy', 'sq_order_missing');
        $row = $this->row('evt_busy');
        $row->forceFill(['status' => WebhookEventStatus::Processing->value, 'last_attempt_at' => now()])->save();

        $this->assertFalse(WebhookEvent::requeueForOperator($row->id));
        $this->assertSame(WebhookEventStatus::Processing->value, $row->fresh()->status);
    }

    public function test_crash_on_final_attempt_is_parked_as_ambiguous_not_retried_or_dropped(): void
    {
        $this->earlyWebhook('evt_crash', 'sq_order_missing');
        $row = $this->row('evt_crash');
        $row->forceFill([
            'status' => WebhookEventStatus::Processing->value,
            'retry_count' => WebhookEvent::maxAttempts(),
            'last_attempt_at' => now(),
        ])->save();

        $this->recover();
        $this->assertSame(WebhookEventStatus::Processing->value, $row->fresh()->status, 'lease still held');

        $this->travel(WebhookEvent::processingLeaseSeconds() + 5)->seconds();
        $this->recover();

        $row->refresh();
        $this->assertSame(WebhookEventStatus::NeedsReview->value, $row->status);
        $this->assertStringContainsString('outcome unknown', (string) $row->failure_reason);
        $this->assertSame(1, PlatformAlert::query()->where('title', 'Square webhook outcome unknown')->count());
    }

    public function test_artisan_requeue_lists_by_default_and_requeues_explicit_ids(): void
    {
        $this->earlyWebhook('evt_cli', 'sq_order_missing');
        $this->exhaust('evt_cli');

        $this->artisan('ems:square-webhooks:requeue')->assertExitCode(0);
        $this->assertSame(WebhookEventStatus::NeedsReview->value, $this->row('evt_cli')->status, 'listing does not requeue');

        $this->artisan('ems:square-webhooks:requeue', ['--status' => 'processed'])->assertExitCode(2);

        $this->artisan('ems:square-webhooks:requeue', ['event_ids' => ['evt_cli']])->assertExitCode(0);
        $row = $this->row('evt_cli');
        $this->assertSame(WebhookEventStatus::Unmatched->value, $row->status, 'reprocessed once, still unmatched, fresh retry budget');
        $this->assertSame(1, (int) $row->retry_count);
        $this->assertSame(1, AuditLog::query()->where('action', 'ems.webhook.requeued')->count());
    }
}
