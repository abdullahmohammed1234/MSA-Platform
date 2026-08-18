<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\SquareCatalogSyncStatus;
use App\Ems\Enums\SquareRefundStatus;
use App\Ems\Enums\TicketStatus;
use App\Ems\Enums\WebhookEventStatus;
use App\Ems\Models\Event;
use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\SquareCatalogMapping;
use App\Ems\Models\SquareRefund;
use App\Ems\Models\Ticket;
use App\Ems\Models\TicketType;
use App\Ems\Models\WebhookEvent;
use App\Ems\Services\CheckoutLifecycleService;
use App\Ems\Services\Operations\CheckInService;
use App\Ems\Services\Square\SquareCatalogService;
use App\Ems\Services\Square\SquareReconciliationService;
use App\Ems\Support\EmsRoles;
use Illuminate\Support\Facades\Http;

class EmsSquareIntegrationOverhaulTest extends EmsTestCase
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
            'ems.payments.square.terminal_device_id' => 'device-sandbox',
            'ems.payments.checkout_ttl_minutes' => 60,
            'queue.default' => 'sync',
        ]);
    }

    public function test_creating_ticket_type_upserts_square_catalog_item_and_variation(): void
    {
        $event = $this->openEvent(['name' => 'Frosh 2026']);
        $user = $this->admin();

        Http::fake([
            '*/v2/catalog/batch-upsert' => Http::response([
                'objects' => [[
                    'type' => 'ITEM',
                    'id' => 'ITEM_FROSH',
                    'version' => 11,
                    'item_data' => [
                        'variations' => [[
                            'type' => 'ITEM_VARIATION',
                            'id' => 'VAR_GA',
                            'version' => 12,
                        ]],
                    ],
                ]],
                'id_mappings' => [
                    ['client_object_id' => '#ems-item-' . $event->uuid, 'object_id' => 'ITEM_FROSH'],
                ],
            ], 200),
        ]);

        $create = $this->actingAsEms($user)->postJson($this->url("events/{$event->uuid}/tickets"), [
            'name' => 'General Admission',
            'price' => 15,
            'currency' => 'CAD',
        ]);
        $create->assertCreated();

        $ticket = TicketType::query()->where('event_id', $event->id)->firstOrFail();
        $mapping = SquareCatalogMapping::query()->where('ticket_type_id', $ticket->id)->first();

        $this->assertNotNull($mapping);
        $this->assertSame('ITEM_FROSH', $mapping->square_catalog_item_id);
        $this->assertSame('VAR_GA', $mapping->square_catalog_variation_id);
        $this->assertSame(SquareCatalogSyncStatus::Synced, $mapping->sync_status);
    }

    public function test_updating_ticket_price_and_name_resyncs_without_duplicate_mapping(): void
    {
        $event = $this->openEvent();
        $ticket = TicketType::factory()->paid(15)->create(['event_id' => $event->id, 'name' => 'GA']);
        SquareCatalogMapping::query()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticket->id,
            'square_catalog_item_id' => 'ITEM_EXISTING',
            'square_catalog_variation_id' => 'VAR_EXISTING',
            'sync_status' => SquareCatalogSyncStatus::Synced->value,
            'ems_managed' => true,
        ]);

        Http::fake([
            '*/v2/catalog/object/*' => Http::response([
                'object' => ['id' => 'VAR_EXISTING', 'version' => 4, 'type' => 'ITEM_VARIATION', 'item_variation_data' => ['item_id' => 'ITEM_EXISTING']],
            ], 200),
            '*/v2/catalog/batch-upsert' => Http::response([
                'objects' => [[
                    'type' => 'ITEM_VARIATION',
                    'id' => 'VAR_EXISTING',
                    'version' => 5,
                    'item_variation_data' => ['item_id' => 'ITEM_EXISTING'],
                ]],
            ], 200),
        ]);

        $this->actingAsEms($this->admin())->putJson(
            $this->url("events/{$event->uuid}/tickets/{$ticket->uuid}"),
            ['name' => 'General Admission', 'price' => 20]
        )->assertOk();

        $this->assertSame(1, SquareCatalogMapping::query()->where('ticket_type_id', $ticket->id)->count());
        $this->assertSame('VAR_EXISTING', SquareCatalogMapping::query()->where('ticket_type_id', $ticket->id)->value('square_catalog_variation_id'));
    }

    public function test_unrelated_square_catalog_item_is_not_imported(): void
    {
        Http::fake([
            '*/v2/catalog/search' => Http::response([
                'objects' => [[
                    'type' => 'ITEM_VARIATION',
                    'id' => 'MERCH_VAR',
                    'item_variation_data' => ['name' => 'Hoodie', 'price_money' => ['amount' => 4000, 'currency' => 'CAD']],
                ]],
                'latest_time' => now()->toRfc3339String(),
            ], 200),
        ]);

        $updated = app(SquareCatalogService::class)->pullRemoteChanges();

        $this->assertSame(0, $updated);
        $this->assertSame(0, TicketType::query()->count());
    }

    public function test_online_checkout_persists_and_resumes_payment_link(): void
    {
        $event = $this->openEvent();
        $ticket = TicketType::factory()->paid(15)->create(['event_id' => $event->id]);

        Http::fake([
            '*/v2/catalog/*' => Http::response(['objects' => []], 200),
            '*/v2/online-checkout/payment-links' => Http::response([
                'payment_link' => [
                    'id' => 'plink_resume',
                    'url' => 'https://square.test/checkout/resume',
                    'order_id' => 'sq_order_resume',
                ],
            ], 200),
        ]);

        $first = $this->postJson($this->publicUrl("events/{$event->slug}/checkout"), [
            'first_name' => 'Amina',
            'last_name' => 'Ali',
            'email' => 'amina@example.com',
            'ticket_type_id' => $ticket->uuid,
        ]);
        $first->assertCreated();
        $first->assertJsonPath('data.checkout_url', 'https://square.test/checkout/resume');

        $payment = Payment::query()->firstOrFail();
        $this->assertSame('plink_resume', $payment->provider_checkout_id);
        $this->assertSame('https://square.test/checkout/resume', $payment->checkout_url);
        $this->assertNotNull($payment->checkout_expires_at);

        $second = $this->postJson($this->publicUrl("events/{$event->slug}/checkout"), [
            'first_name' => 'Amina',
            'last_name' => 'Ali',
            'email' => 'amina@example.com',
            'ticket_type_id' => $ticket->uuid,
        ]);
        $second->assertCreated();
        $second->assertJsonPath('data.checkout_url', 'https://square.test/checkout/resume');
        $this->assertSame(1, Registration::query()->count());

        $resume = $this->postJson($this->publicUrl("events/{$event->slug}/checkout/resume"), [
            'email' => 'amina@example.com',
        ]);
        $resume->assertOk();
        $resume->assertJsonPath('data.checkout_url', 'https://square.test/checkout/resume');
    }

    public function test_cancel_and_expire_checkout_release_inventory(): void
    {
        $event = $this->openEvent();
        $ticket = TicketType::factory()->paid(15)->limited(5)->create(['event_id' => $event->id]);

        Http::fake([
            '*/v2/catalog/*' => Http::response(['objects' => []], 200),
            '*/v2/online-checkout/payment-links' => Http::response([
                'payment_link' => [
                    'id' => 'plink_expire',
                    'url' => 'https://square.test/checkout/expire',
                    'order_id' => 'sq_order_expire',
                ],
            ], 200),
            '*/v2/online-checkout/payment-links/*' => Http::response([], 200),
        ]);

        $this->postJson($this->publicUrl("events/{$event->slug}/checkout"), [
            'first_name' => 'Omar',
            'last_name' => 'Hassan',
            'email' => 'omar@example.com',
            'ticket_type_id' => $ticket->uuid,
        ])->assertCreated();

        $this->assertSame(1, $ticket->fresh()->quantity_sold);

        $order = Order::query()->firstOrFail();
        $this->postJson($this->publicUrl("events/{$event->slug}/checkout/cancel"), [
            'email' => 'omar@example.com',
            'order_uuid' => $order->uuid,
        ])->assertOk();

        $this->assertSame(0, $ticket->fresh()->quantity_sold);
        $this->assertSame(PaymentStatus::Cancelled, Payment::query()->first()->status);
        $this->assertSame(RegistrationStatus::Cancelled, Registration::query()->first()->status);
    }

    public function test_expired_checkout_is_abandoned_and_inventory_released(): void
    {
        $event = $this->openEvent();
        $ticket = TicketType::factory()->paid(15)->limited(3)->create(['event_id' => $event->id]);

        Http::fake([
            '*/v2/catalog/*' => Http::response(['objects' => []], 200),
            '*/v2/online-checkout/payment-links' => Http::response([
                'payment_link' => [
                    'id' => 'plink_stale',
                    'url' => 'https://square.test/checkout/stale',
                    'order_id' => 'sq_order_stale',
                ],
            ], 200),
            '*/v2/online-checkout/payment-links/*' => Http::response([], 200),
        ]);

        $this->postJson($this->publicUrl("events/{$event->slug}/checkout"), [
            'first_name' => 'Sara',
            'last_name' => 'Khan',
            'email' => 'sara@example.com',
            'ticket_type_id' => $ticket->uuid,
        ])->assertCreated();

        Payment::query()->update(['checkout_expires_at' => now()->subMinute()]);

        $count = app(CheckoutLifecycleService::class)->expireStale();
        $this->assertSame(1, $count);
        $this->assertSame(PaymentStatus::Abandoned, Payment::query()->first()->status);
        $this->assertSame(0, $ticket->fresh()->quantity_sold);
    }

    public function test_replayed_payment_webhook_does_not_duplicate_tickets(): void
    {
        [$event, $ticketType, $order, $registration, $payment] = $this->pendingCheckout();

        $payload = [
            'event_id' => 'evt_replay',
            'type' => 'payment.updated',
            'data' => ['object' => ['payment' => [
                'id' => 'sq_pay_replay',
                'status' => 'COMPLETED',
                'order_id' => $payment->provider_order_id,
                'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                'reference_id' => $order->reference,
            ]]],
        ];

        $this->postWebhook($payload)->assertOk();
        $this->postWebhook($payload)->assertOk();

        $this->assertSame(1, Ticket::query()->where('registration_id', $registration->id)->count());
        $this->assertSame(1, WebhookEvent::query()->count());
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_unmatched_webhook_is_not_marked_processed(): void
    {
        $this->postWebhook([
            'event_id' => 'evt_unmatched',
            'type' => 'payment.updated',
            'data' => ['object' => ['payment' => [
                'id' => 'sq_unknown',
                'status' => 'COMPLETED',
                'order_id' => 'sq_unknown_order',
                'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
            ]]],
        ])->assertOk();

        $event = WebhookEvent::query()->firstOrFail();
        $this->assertSame(WebhookEventStatus::Unmatched->value, $event->status);
        $this->assertSame(0, Registration::query()->count());
    }

    public function test_pos_sale_creates_walk_in_registration_and_ticket(): void
    {
        $event = $this->openEvent();
        $ticketType = TicketType::factory()->paid(15)->create(['event_id' => $event->id, 'name' => 'General Admission']);
        SquareCatalogMapping::query()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'square_catalog_item_id' => 'ITEM_POS',
            'square_catalog_variation_id' => 'VAR_POS',
            'sync_status' => SquareCatalogSyncStatus::Synced->value,
            'ems_managed' => true,
        ]);

        Http::fake([
            '*/v2/orders/sq_pos_order' => Http::response([
                'order' => [
                    'id' => 'sq_pos_order',
                    'line_items' => [[
                        'catalog_object_id' => 'VAR_POS',
                        'quantity' => '1',
                        'name' => 'General Admission',
                    ]],
                ],
            ], 200),
        ]);

        $this->postWebhook([
            'event_id' => 'evt_pos_1',
            'type' => 'payment.updated',
            'data' => ['object' => ['payment' => [
                'id' => 'sq_pos_pay',
                'status' => 'COMPLETED',
                'order_id' => 'sq_pos_order',
                'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                'application_details' => ['square_product' => 'SQUARE_POS'],
            ]]],
        ])->assertOk();

        $registration = Registration::query()->firstOrFail();
        $this->assertNull($registration->user_id);
        $this->assertSame('Walk-in', $registration->attendee_name);
        $this->assertSame(RegistrationStatus::Confirmed, $registration->status);
        $this->assertSame(1, $registration->tickets()->count());
        $this->assertSame('pos', Payment::query()->first()->source_channel);
    }

    public function test_pos_sale_without_mapping_does_not_create_ticket(): void
    {
        Http::fake([
            '*/v2/orders/sq_merch' => Http::response([
                'order' => [
                    'id' => 'sq_merch',
                    'line_items' => [['catalog_object_id' => 'HOODIE', 'quantity' => '1']],
                ],
            ], 200),
        ]);

        $this->postWebhook([
            'event_id' => 'evt_merch',
            'type' => 'payment.updated',
            'data' => ['object' => ['payment' => [
                'id' => 'sq_merch_pay',
                'status' => 'COMPLETED',
                'order_id' => 'sq_merch',
                'amount_money' => ['amount' => 4000, 'currency' => 'CAD'],
            ]]],
        ])->assertOk();

        $this->assertSame(0, Registration::query()->count());
        $this->assertSame(WebhookEventStatus::Unmatched->value, WebhookEvent::query()->first()->status);
    }

    public function test_reconciliation_imports_missed_pos_sale_idempotently(): void
    {
        $event = $this->openEvent();
        $ticketType = TicketType::factory()->paid(15)->create(['event_id' => $event->id]);
        SquareCatalogMapping::query()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'square_catalog_variation_id' => 'VAR_REC',
            'square_catalog_item_id' => 'ITEM_REC',
            'sync_status' => SquareCatalogSyncStatus::Synced->value,
            'ems_managed' => true,
        ]);

        $squarePayment = [
            'id' => 'sq_rec_pay',
            'status' => 'COMPLETED',
            'order_id' => 'sq_rec_order',
            'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
            'created_at' => now()->toRfc3339String(),
            'application_details' => ['square_product' => 'SQUARE_POS'],
        ];

        Http::fake([
            '*/v2/catalog/search' => Http::response(['objects' => []], 200),
            '*/v2/payments*' => Http::response(['payments' => [$squarePayment]], 200),
            '*/v2/orders/sq_rec_order' => Http::response([
                'order' => ['id' => 'sq_rec_order', 'line_items' => [[
                    'catalog_object_id' => 'VAR_REC',
                    'quantity' => '1',
                ]]],
            ], 200),
        ]);

        $first = app(SquareReconciliationService::class)->reconcile();
        $second = app(SquareReconciliationService::class)->reconcile();

        $this->assertSame(1, $first['ingested']);
        $this->assertSame(0, $second['ingested']);
        $this->assertSame(1, Registration::query()->count());
        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_terminal_checkout_success_failure_and_cancel(): void
    {
        $event = $this->openEvent();
        $ticketType = TicketType::factory()->paid(15)->create(['event_id' => $event->id]);
        $user = $this->admin();

        Http::fake([
            '*/v2/terminals/checkouts' => Http::sequence()
                ->push(['checkout' => ['id' => 'TERM_1', 'status' => 'PENDING', 'order_id' => 'sq_term_order']], 200)
                ->push(['checkout' => ['id' => 'TERM_FAIL', 'status' => 'PENDING']], 200),
        ]);

        $create = $this->actingAsEms($user)->postJson($this->url("events/{$event->uuid}/terminal-checkout"), [
            'ticket_type_id' => $ticketType->uuid,
            'attendee_name' => 'Terminal Guest',
        ]);
        $create->assertCreated();
        $create->assertJsonPath('data.terminal_checkout_id', 'TERM_1');
        $this->assertNull(Registration::query()->first()->user_id);

        $this->postWebhook([
            'event_id' => 'evt_term_ok',
            'type' => 'terminal.checkout.updated',
            'data' => ['object' => ['checkout' => [
                'id' => 'TERM_1',
                'status' => 'COMPLETED',
                'payment_ids' => ['sq_term_pay'],
                'order_id' => 'sq_term_order',
            ]]],
        ])->assertOk();

        $this->assertSame(PaymentStatus::Paid, Payment::query()->where('terminal_checkout_id', 'TERM_1')->first()->status);
        $this->assertSame(1, Ticket::query()->count());

        $second = $this->actingAsEms($user)->postJson($this->url("events/{$event->uuid}/terminal-checkout"), [
            'ticket_type_id' => $ticketType->uuid,
            'attendee_name' => 'Canceled Guest',
        ]);
        $second->assertCreated();
        $second->assertJsonPath('data.terminal_checkout_id', 'TERM_FAIL');
        $failPaymentUuid = $second->json('data.payment.uuid');
        $this->assertNotNull($failPaymentUuid);

        $this->postWebhook([
            'event_id' => 'evt_term_cancel',
            'type' => 'terminal.checkout.updated',
            'data' => ['object' => ['checkout' => [
                'id' => 'TERM_FAIL',
                'status' => 'CANCELED',
            ]]],
        ])->assertOk();

        $this->assertSame(
            PaymentStatus::Cancelled,
            Payment::query()->where('uuid', $failPaymentUuid)->first()?->status
        );
    }

    public function test_ems_refund_and_square_refund_webhook_revoke_ticket(): void
    {
        [$event, $ticketType, $order, $registration, $payment] = $this->paidCheckout();
        $ticket = $registration->tickets()->firstOrFail();

        Http::fake([
            '*/v2/refunds' => Http::response([
                'refund' => [
                    'id' => 'sq_refund_1',
                    'status' => 'PENDING',
                    'payment_id' => $payment->provider_payment_id,
                    'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                ],
            ], 200),
        ]);

        $refund = $this->actingAsEms($this->admin())->postJson(
            $this->url("payments/{$payment->uuid}/refund"),
            ['reason' => 'Attendee requested refund']
        );
        $refund->assertOk();
        $this->assertSame(SquareRefundStatus::Pending->value, SquareRefund::query()->first()->status->value);
        $this->assertSame(TicketStatus::Issued, $ticket->fresh()->status);

        $this->postWebhook([
            'event_id' => 'evt_refund_done',
            'type' => 'refund.updated',
            'data' => ['object' => ['refund' => [
                'id' => 'sq_refund_1',
                'status' => 'COMPLETED',
                'payment_id' => $payment->provider_payment_id,
                'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
            ]]],
        ])->assertOk();

        $this->postWebhook([
            'event_id' => 'evt_refund_done',
            'type' => 'refund.updated',
            'data' => ['object' => ['refund' => [
                'id' => 'sq_refund_1',
                'status' => 'COMPLETED',
                'payment_id' => $payment->provider_payment_id,
                'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
            ]]],
        ])->assertOk();

        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertSame(RegistrationStatus::Refunded, $registration->fresh()->status);
        $this->assertSame(TicketStatus::Revoked, $ticket->fresh()->status);
        $this->assertSame(1, SquareRefund::query()->count());

        $event->status = EventStatus::Live;
        $event->save();

        $this->expectException(\App\Ems\Exceptions\CheckInException::class);
        $this->expectExceptionMessage('Ticket refunded.');
        app(CheckInService::class)->checkInByCode($event, $ticket->code, $this->admin());
    }

    public function test_partial_refund_keeps_ticket_valid(): void
    {
        [, , , $registration, $payment] = $this->paidCheckout();

        Http::fake([
            '*/v2/refunds' => Http::response([
                'refund' => [
                    'id' => 'sq_partial',
                    'status' => 'COMPLETED',
                    'payment_id' => $payment->provider_payment_id,
                    'amount_money' => ['amount' => 500, 'currency' => 'CAD'],
                ],
            ], 200),
        ]);

        $this->actingAsEms($this->admin())->postJson(
            $this->url("payments/{$payment->uuid}/refund"),
            ['amount' => 5]
        )->assertOk();

        $this->assertSame(PaymentStatus::PartiallyRefunded, $payment->fresh()->status);
        $this->assertSame(RegistrationStatus::Confirmed, $registration->fresh()->status);
        $this->assertSame(TicketStatus::Issued, $registration->tickets()->first()->status);
    }

    public function test_failed_square_refund_does_not_revoke_ticket(): void
    {
        [, , , $registration, $payment] = $this->paidCheckout();

        Http::fake([
            '*/v2/refunds' => Http::response([
                'refund' => [
                    'id' => 'sq_fail',
                    'status' => 'FAILED',
                    'payment_id' => $payment->provider_payment_id,
                    'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                ],
            ], 200),
        ]);

        $this->actingAsEms($this->admin())->postJson(
            $this->url("payments/{$payment->uuid}/refund")
        )->assertOk();

        $this->assertSame(SquareRefundStatus::Failed, SquareRefund::query()->first()->status);
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(TicketStatus::Issued, $registration->tickets()->first()->status);
    }

    public function test_square_pos_refund_synchronizes_to_ems(): void
    {
        [, , , $registration, $payment] = $this->paidCheckout();

        $this->postWebhook([
            'event_id' => 'evt_pos_refund',
            'type' => 'refund.created',
            'data' => ['object' => ['refund' => [
                'id' => 'sq_pos_refund',
                'status' => 'COMPLETED',
                'payment_id' => $payment->provider_payment_id,
                'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                'reason' => 'POS refund',
            ]]],
        ])->assertOk();

        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertSame(RegistrationStatus::Refunded, $registration->fresh()->status);
        $this->assertSame(TicketStatus::Revoked, $registration->tickets()->first()->status);
    }

    public function test_organizer_name_persists_separately_from_ids(): void
    {
        $admin = $this->admin();
        $category = $this->category(['is_active' => true]);

        $create = $this->actingAsEms($admin)->postJson($this->url('events'), [
            'name' => 'Organizer Name Event',
            'category_id' => $category->id,
            'start_at' => now()->addDay()->toDateTimeString(),
            'end_at' => now()->addDay()->addHours(2)->toDateTimeString(),
            'organizer_name' => 'MSA Dawah Committee',
        ]);
        $create->assertCreated();
        $create->assertJsonPath('data.organizer_name', 'MSA Dawah Committee');
        $create->assertJsonPath('data.organizer_id', $admin->id);
        $create->assertJsonPath('data.created_by', $admin->id);

        $uuid = $create->json('data.uuid');
        $event = Event::query()->where('uuid', $uuid)->firstOrFail();
        $this->assertSame('MSA Dawah Committee', $event->organizer_name);
        $this->assertSame($admin->id, $event->organizer_id);
        $this->assertSame($admin->id, $event->created_by);

        $this->actingAsEms($admin)->putJson($this->url("events/{$uuid}"), [
            'name' => 'Organizer Name Event',
            'start_at' => $event->start_at->toDateTimeString(),
            'organizer_name' => 'MSA Social Committee',
        ])->assertOk()->assertJsonPath('data.organizer_name', 'MSA Social Committee');

        $event->refresh();
        $this->assertSame('MSA Social Committee', $event->organizer_name);
        $this->assertSame($admin->id, $event->organizer_id);
        $this->assertSame($admin->id, $event->created_by);
    }

    public function test_multiple_paid_walk_ins_are_not_assigned_to_staff(): void
    {
        $event = $this->openEvent();
        $ticketType = TicketType::factory()->paid(15)->create(['event_id' => $event->id]);
        $staff = $this->admin();

        Http::fake([
            '*/v2/catalog/*' => Http::response(['objects' => []], 200),
            '*/v2/online-checkout/payment-links' => Http::sequence()
                ->push(['payment_link' => ['id' => 'plink_w1', 'url' => 'https://square.test/w1', 'order_id' => 'o1']], 200)
                ->push(['payment_link' => ['id' => 'plink_w2', 'url' => 'https://square.test/w2', 'order_id' => 'o2']], 200),
        ]);

        $first = $this->actingAsEms($staff)->postJson($this->url("events/{$event->uuid}/walk-in"), [
            'attendee_name' => 'Walk-in One',
            'ticket_type_id' => $ticketType->uuid,
            'check_in' => false,
        ]);
        $first->assertCreated();

        $second = $this->actingAsEms($staff)->postJson($this->url("events/{$event->uuid}/walk-in"), [
            'attendee_name' => 'Walk-in Two',
            'ticket_type_id' => $ticketType->uuid,
            'check_in' => false,
        ]);
        $second->assertCreated();

        $registrations = Registration::query()->orderBy('id')->get();
        $this->assertCount(2, $registrations);
        $this->assertNull($registrations[0]->user_id);
        $this->assertNull($registrations[1]->user_id);
        $this->assertNotSame($staff->id, $registrations[0]->user_id);
        $this->assertSame('Walk-in One', $registrations[0]->attendee_name);
        $this->assertSame('', $registrations[0]->attendee_email);
    }

    public function test_free_walk_in_ticket_scans_successfully(): void
    {
        $event = $this->openEvent(['status' => EventStatus::Live]);
        $ticketType = TicketType::factory()->free()->create(['event_id' => $event->id]);
        $staff = $this->admin();

        $response = $this->actingAsEms($staff)->postJson($this->url("events/{$event->uuid}/walk-in"), [
            'attendee_name' => 'Free Walk-in',
            'ticket_type_id' => $ticketType->uuid,
            'check_in' => false,
        ]);
        $response->assertCreated();

        $registration = Registration::query()->firstOrFail();
        $this->assertNull($registration->user_id);
        $ticket = $registration->tickets()->firstOrFail();

        $checkIn = $this->actingAsEms($staff)->postJson($this->url("events/{$event->uuid}/check-in"), [
            'code' => $ticket->code,
        ]);
        $checkIn->assertOk();
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

    private function publicUrl(string $path = ''): string
    {
        return $this->url('public/' . ltrim($path, '/'));
    }

    /**
     * @return array{0: Event, 1: TicketType, 2: Order, 3: Registration, 4: Payment}
     */
    private function pendingCheckout(): array
    {
        $event = $this->openEvent();
        $ticketType = TicketType::factory()->paid(15)->create(['event_id' => $event->id]);
        $order = Order::factory()->create([
            'event_id' => $event->id,
            'reference' => 'ORD-OVERHAUL1',
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
            'provider_order_id' => 'sq_order_overhaul',
        ]);

        return [$event, $ticketType, $order, $registration, $payment];
    }

    /**
     * @return array{0: Event, 1: TicketType, 2: Order, 3: Registration, 4: Payment}
     */
    private function paidCheckout(): array
    {
        [$event, $ticketType, $order, $registration, $payment] = $this->pendingCheckout();

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

        return [$event, $ticketType, $order, $registration->fresh(['tickets']), $payment->fresh()];
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
