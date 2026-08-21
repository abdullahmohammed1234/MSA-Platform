<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\NotificationStatus;
use App\Ems\Enums\NotificationType;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\SquareCatalogSyncStatus;
use App\Ems\Enums\TicketStatus;
use App\Ems\Enums\WebhookEventStatus;
use App\Ems\Jobs\QueueRegistrationConfirmation;
use App\Ems\Mail\EventNotificationMail;
use App\Ems\Models\Event;
use App\Ems\Models\EventNotification;
use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\SquareCatalogMapping;
use App\Ems\Models\Ticket;
use App\Ems\Models\TicketType;
use App\Ems\Models\WebhookEvent;
use App\Ems\Services\Square\SquareReconciliationService;
use Database\Seeders\Ems\EmsEmailTemplateSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class EmsSquareOnlineStoreTest extends EmsTestCase
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
            'mail.default' => 'array',
        ]);

        $this->seed(EmsEmailTemplateSeeder::class);
    }

    public function test_online_store_single_ticket_is_not_labeled_pos_or_walk_in(): void
    {
        Mail::fake();
        Bus::fake([QueueRegistrationConfirmation::class]);

        $event = $this->openEvent();
        $ticketType = $this->mappedType($event, 'VAR_ONLINE', 15);

        $this->fakeOrder('sq_online_order', [[
            'catalog_object_id' => 'VAR_ONLINE',
            'quantity' => '1',
        ]], [
            'buyer_email' => 'buyer@example.com',
            'fulfillments' => [[
                'shipment_details' => [
                    'recipient' => ['display_name' => 'Aisha Rahman'],
                ],
            ]],
        ]);

        $this->postOnlinePayment('evt_online_1', 'sq_online_pay', 'sq_online_order', [
            'buyer_email_address' => 'buyer@example.com',
            'billing_address' => ['first_name' => 'Aisha', 'last_name' => 'Rahman'],
        ])->assertOk();

        $registration = Registration::query()->firstOrFail();
        $payment = Payment::query()->firstOrFail();
        $ticket = Ticket::query()->firstOrFail();

        $this->assertSame(1, Registration::query()->count());
        $this->assertSame(1, Ticket::query()->count());
        $this->assertNotEmpty($ticket->qr_payload);
        $this->assertSame(RegistrationStatus::Confirmed, $registration->status);
        $this->assertSame('Aisha Rahman', $registration->attendee_name);
        $this->assertSame('buyer@example.com', $registration->attendee_email);
        $this->assertSame('square_online_store', $payment->source_channel);
        $this->assertSame('square_online_store', $registration->metadata['source']);
        $this->assertFalse((bool) ($registration->metadata['walk_in'] ?? false));
        $this->assertNotSame('pos', $payment->source_channel);
        $this->assertNotSame('square_pos', $registration->metadata['source']);
        $this->assertSame($ticketType->id, $ticket->ticket_type_id);
        $this->assertSame($event->id, $ticket->event_id);

        Bus::assertDispatched(QueueRegistrationConfirmation::class);
    }

    public function test_online_store_quantity_creates_unique_tickets(): void
    {
        Mail::fake();

        $event = $this->openEvent();
        $this->mappedType($event, 'VAR_QTY', 15);

        $this->fakeOrder('sq_qty_order', [[
            'catalog_object_id' => 'VAR_QTY',
            'quantity' => '3',
        ]]);

        $this->postOnlinePayment('evt_qty', 'sq_qty_pay', 'sq_qty_order', [
            'buyer_email_address' => 'qty@example.com',
            'amount_money' => ['amount' => 4500, 'currency' => 'CAD'],
        ])->assertOk();

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, Registration::query()->count());
        $this->assertSame(3, Ticket::query()->count());
        $this->assertSame(3, Registration::query()->first()->quantity);

        $codes = Ticket::query()->pluck('code')->all();
        $this->assertCount(3, array_unique($codes));
        $this->assertTrue(Ticket::query()->whereNotNull('qr_payload')->count() === 3);

        $notification = EventNotification::query()
            ->where('type', NotificationType::RegistrationConfirmed->value)
            ->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString((string) $codes[0], (string) data_get($notification->payload, 'body_html'));
        $this->assertStringContainsString((string) $codes[1], (string) data_get($notification->payload, 'body_html'));
        $this->assertStringContainsString((string) $codes[2], (string) data_get($notification->payload, 'body_html'));
    }

    public function test_online_store_mixed_ticket_types_keep_their_types(): void
    {
        Mail::fake();

        $event = $this->openEvent();
        $typeA = $this->mappedType($event, 'VAR_A', 15, 'General');
        $typeB = $this->mappedType($event, 'VAR_B', 25, 'VIP');

        $this->fakeOrder('sq_mixed_order', [
            ['catalog_object_id' => 'VAR_A', 'quantity' => '2'],
            ['catalog_object_id' => 'VAR_B', 'quantity' => '1'],
        ]);

        $this->postOnlinePayment('evt_mixed', 'sq_mixed_pay', 'sq_mixed_order', [
            'buyer_email_address' => 'mixed@example.com',
            'amount_money' => ['amount' => 5500, 'currency' => 'CAD'],
        ])->assertOk();

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(2, Registration::query()->count());
        $this->assertSame(3, Ticket::query()->count());

        $ticketsA = Ticket::query()->where('ticket_type_id', $typeA->id)->get();
        $ticketsB = Ticket::query()->where('ticket_type_id', $typeB->id)->get();
        $this->assertCount(2, $ticketsA);
        $this->assertCount(1, $ticketsB);
        $this->assertTrue($ticketsA->every(fn (Ticket $ticket) => $ticket->event_id === $event->id));
        $this->assertSame($event->id, $ticketsB->first()->event_id);
    }

    public function test_online_store_mixed_events_keep_separate_event_ids(): void
    {
        Mail::fake();

        $eventA = $this->openEvent(['name' => 'Event A']);
        $eventB = $this->openEvent(['name' => 'Event B']);
        $this->mappedType($eventA, 'VAR_EA', 10, 'A Ticket');
        $this->mappedType($eventB, 'VAR_EB', 12, 'B Ticket');

        $this->fakeOrder('sq_cross_order', [
            ['catalog_object_id' => 'VAR_EA', 'quantity' => '1'],
            ['catalog_object_id' => 'VAR_EB', 'quantity' => '1'],
        ]);

        $this->postOnlinePayment('evt_cross', 'sq_cross_pay', 'sq_cross_order', [
            'buyer_email_address' => 'cross@example.com',
            'amount_money' => ['amount' => 2200, 'currency' => 'CAD'],
        ])->assertOk();

        $this->assertSame(1, Registration::query()->where('event_id', $eventA->id)->count());
        $this->assertSame(1, Registration::query()->where('event_id', $eventB->id)->count());
        $this->assertSame(1, Ticket::query()->where('event_id', $eventA->id)->count());
        $this->assertSame(1, Ticket::query()->where('event_id', $eventB->id)->count());
    }

    public function test_unmapped_online_store_variation_stays_unmatched(): void
    {
        Http::fake([
            '*/v2/orders/sq_merch_order' => Http::response([
                'order' => [
                    'id' => 'sq_merch_order',
                    'line_items' => [['catalog_object_id' => 'HOODIE', 'quantity' => '1']],
                ],
            ], 200),
        ]);

        $this->postOnlinePayment('evt_merch', 'sq_merch_pay', 'sq_merch_order')->assertOk();

        $this->assertSame(0, Registration::query()->count());
        $this->assertSame(0, Ticket::query()->count());
        $this->assertSame(WebhookEventStatus::Unmatched->value, WebhookEvent::query()->first()->status);
        $this->assertNotEmpty(WebhookEvent::query()->first()->failure_reason);
    }

    public function test_pending_online_payment_does_not_issue_tickets(): void
    {
        Mail::fake();

        $event = $this->openEvent();
        $this->mappedType($event, 'VAR_PEND', 15);
        $this->fakeOrder('sq_pend_order', [['catalog_object_id' => 'VAR_PEND', 'quantity' => '1']]);

        $this->postOnlinePayment('evt_pend', 'sq_pend_pay', 'sq_pend_order', [
            'status' => 'PENDING',
            'buyer_email_address' => 'pend@example.com',
        ])->assertOk();

        $this->assertSame(0, Registration::query()->count());
        $this->assertSame(0, Ticket::query()->count());
        $this->assertSame(0, EventNotification::query()->count());
        $this->assertSame(WebhookEventStatus::Unmatched->value, WebhookEvent::query()->first()->status);
        $this->assertStringContainsString('not captured', (string) WebhookEvent::query()->first()->failure_reason);
    }

    public function test_approved_online_payment_does_not_issue_tickets(): void
    {
        $event = $this->openEvent();
        $this->mappedType($event, 'VAR_APPR', 15);
        $this->fakeOrder('sq_appr_order', [['catalog_object_id' => 'VAR_APPR', 'quantity' => '1']]);

        $this->postOnlinePayment('evt_appr', 'sq_appr_pay', 'sq_appr_order', [
            'status' => 'APPROVED',
            'buyer_email_address' => 'appr@example.com',
        ])->assertOk();

        $this->assertSame(0, Registration::query()->count());
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_completed_online_payment_issues_ticket_and_queues_confirmation(): void
    {
        Mail::fake();

        $event = $this->openEvent();
        $this->mappedType($event, 'VAR_DONE', 15);
        $this->fakeOrder('sq_done_order', [['catalog_object_id' => 'VAR_DONE', 'quantity' => '1']]);

        $this->postOnlinePayment('evt_done', 'sq_done_pay', 'sq_done_order', [
            'buyer_email_address' => 'done@example.com',
        ])->assertOk();

        $registration = Registration::query()->firstOrFail();
        $this->assertSame(RegistrationStatus::Confirmed, $registration->status);
        $this->assertSame(1, $registration->tickets()->count());
        $this->assertNotNull($registration->tickets()->first()->qr_payload);
        $this->assertSame(1, EventNotification::query()->where('type', NotificationType::RegistrationConfirmed->value)->count());
        Mail::assertSent(EventNotificationMail::class);
    }

    public function test_duplicate_webhooks_create_one_sale_and_one_confirmation(): void
    {
        Mail::fake();

        $event = $this->openEvent();
        $this->mappedType($event, 'VAR_DUP', 15);
        $this->fakeOrder('sq_dup_order', [['catalog_object_id' => 'VAR_DUP', 'quantity' => '1']]);

        $this->postOnlinePayment('evt_dup_created', 'sq_dup_pay', 'sq_dup_order', [
            'buyer_email_address' => 'dup@example.com',
        ])->assertOk();
        $this->postOnlinePayment('evt_dup_updated', 'sq_dup_pay', 'sq_dup_order', [
            'buyer_email_address' => 'dup@example.com',
        ])->assertOk();

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, Registration::query()->count());
        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(
            1,
            EventNotification::query()
                ->where('type', NotificationType::RegistrationConfirmed->value)
                ->count()
        );
        $this->assertSame(2, WebhookEvent::query()->count());
    }

    public function test_reconciliation_imports_missed_online_store_sale_once(): void
    {
        Mail::fake();

        $event = $this->openEvent();
        $this->mappedType($event, 'VAR_REC_ON', 15);

        $squarePayment = [
            'id' => 'sq_rec_online',
            'status' => 'COMPLETED',
            'order_id' => 'sq_rec_online_order',
            'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
            'created_at' => now()->toRfc3339String(),
            'buyer_email_address' => 'rec@example.com',
            'application_details' => ['square_product' => 'ONLINE_STORE'],
        ];

        Http::fake([
            '*/v2/catalog/search' => Http::response(['objects' => []], 200),
            '*/v2/payments*' => Http::response(['payments' => [$squarePayment]], 200),
            '*/v2/refunds*' => Http::response(['refunds' => []], 200),
            '*/v2/orders/sq_rec_online_order' => Http::response([
                'order' => [
                    'id' => 'sq_rec_online_order',
                    'line_items' => [['catalog_object_id' => 'VAR_REC_ON', 'quantity' => '1']],
                ],
            ], 200),
        ]);

        $first = app(SquareReconciliationService::class)->reconcile();
        $second = app(SquareReconciliationService::class)->reconcile();

        $this->assertSame(1, $first['ingested']);
        $this->assertSame(0, $second['ingested']);
        $this->assertSame(1, Registration::query()->count());
        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame('square_online_store', Payment::query()->first()->source_channel);
        $this->assertFalse((bool) (Registration::query()->first()->metadata['walk_in'] ?? false));
    }

    public function test_online_store_refund_revokes_tickets(): void
    {
        Mail::fake();

        $event = $this->openEvent();
        $this->mappedType($event, 'VAR_RFND', 15);
        $this->fakeOrder('sq_rfnd_order', [['catalog_object_id' => 'VAR_RFND', 'quantity' => '1']]);
        $this->postOnlinePayment('evt_rfnd_pay', 'sq_rfnd_pay', 'sq_rfnd_order', [
            'buyer_email_address' => 'rfnd@example.com',
        ])->assertOk();

        $registration = Registration::query()->firstOrFail();
        $payment = Payment::query()->firstOrFail();

        $this->postWebhook([
            'event_id' => 'evt_rfnd',
            'type' => 'refund.created',
            'data' => ['object' => ['refund' => [
                'id' => 'sq_online_refund',
                'status' => 'COMPLETED',
                'payment_id' => $payment->provider_payment_id,
                'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                'reason' => 'Online refund',
            ]]],
        ])->assertOk();

        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertSame(RegistrationStatus::Refunded, $registration->fresh()->status);
        $this->assertSame(TicketStatus::Revoked, $registration->tickets()->first()->status);
    }

    public function test_insufficient_capacity_does_not_oversell(): void
    {
        Mail::fake();

        $event = $this->openEvent(['capacity' => 1]);
        $ticketType = TicketType::factory()->paid(15)->limited(1)->create([
            'event_id' => $event->id,
            'quantity_sold' => 1,
        ]);
        SquareCatalogMapping::query()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'square_catalog_item_id' => 'ITEM_CAP',
            'square_catalog_variation_id' => 'VAR_CAP',
            'sync_status' => SquareCatalogSyncStatus::Synced->value,
            'ems_managed' => true,
        ]);

        $this->fakeOrder('sq_cap_order', [['catalog_object_id' => 'VAR_CAP', 'quantity' => '1']]);

        $this->postOnlinePayment('evt_cap', 'sq_cap_pay', 'sq_cap_order', [
            'buyer_email_address' => 'cap@example.com',
        ])->assertOk();

        $this->assertSame(0, Registration::query()->count());
        $this->assertSame(0, Ticket::query()->count());
        $this->assertSame(1, $ticketType->fresh()->quantity_sold);
        $this->assertSame(WebhookEventStatus::Unmatched->value, WebhookEvent::query()->first()->status);
        $this->assertStringContainsString('capacity', strtolower((string) WebhookEvent::query()->first()->failure_reason));
    }

    public function test_missing_buyer_email_does_not_crash_or_send_mail(): void
    {
        Mail::fake();

        $event = $this->openEvent();
        $this->mappedType($event, 'VAR_NOMAIL', 15);
        $this->fakeOrder('sq_nomail_order', [['catalog_object_id' => 'VAR_NOMAIL', 'quantity' => '1']]);

        $this->postOnlinePayment('evt_nomail', 'sq_nomail_pay', 'sq_nomail_order')->assertOk();

        $registration = Registration::query()->firstOrFail();
        $this->assertSame('', (string) $registration->attendee_email);
        $this->assertSame('Square Online Guest', $registration->attendee_name);
        $this->assertSame(1, $registration->tickets()->count());
        $this->assertFalse((bool) ($registration->metadata['walk_in'] ?? false));

        Mail::assertNothingSent();
        $failed = EventNotification::query()
            ->where('registration_id', $registration->id)
            ->where('type', NotificationType::RegistrationConfirmed->value)
            ->first();
        $this->assertNotNull($failed);
        $this->assertSame(NotificationStatus::Failed, $failed->status);
    }

    public function test_order_webhook_without_payment_does_not_register(): void
    {
        $event = $this->openEvent();
        $this->mappedType($event, 'VAR_ORD', 15);

        Http::fake([
            '*/v2/orders/sq_ord_only' => Http::response([
                'order' => [
                    'id' => 'sq_ord_only',
                    'state' => 'OPEN',
                    'line_items' => [['catalog_object_id' => 'VAR_ORD', 'quantity' => '1']],
                    'tenders' => [],
                ],
            ], 200),
        ]);

        $this->postWebhook([
            'event_id' => 'evt_order_only',
            'type' => 'order.updated',
            'data' => ['object' => ['order_updated' => [
                'order_id' => 'sq_ord_only',
                'state' => 'OPEN',
            ]]],
        ])->assertOk();

        $this->assertSame(0, Registration::query()->count());
        $this->assertSame(WebhookEventStatus::Unmatched->value, WebhookEvent::query()->first()->status);
    }

    public function test_order_webhook_ingests_after_settled_tender_lookup(): void
    {
        Mail::fake();

        $event = $this->openEvent();
        $this->mappedType($event, 'VAR_TEND', 15);

        Http::fake([
            '*/v2/orders/sq_tend_order' => Http::response([
                'order' => [
                    'id' => 'sq_tend_order',
                    'line_items' => [['catalog_object_id' => 'VAR_TEND', 'quantity' => '1']],
                    'tenders' => [['payment_id' => 'sq_tend_pay']],
                    'buyer_email' => 'tender@example.com',
                ],
            ], 200),
            '*/v2/payments/sq_tend_pay' => Http::response([
                'payment' => [
                    'id' => 'sq_tend_pay',
                    'status' => 'COMPLETED',
                    'order_id' => 'sq_tend_order',
                    'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                    'buyer_email_address' => 'tender@example.com',
                    'application_details' => ['square_product' => 'ONLINE_STORE'],
                ],
            ], 200),
        ]);

        $this->postWebhook([
            'event_id' => 'evt_tend',
            'type' => 'order.updated',
            'data' => ['object' => ['order_updated' => [
                'order_id' => 'sq_tend_order',
                'state' => 'COMPLETED',
            ]]],
        ])->assertOk();

        $this->assertSame(1, Registration::query()->count());
        $this->assertSame('square_online_store', Payment::query()->first()->source_channel);
        $this->assertSame('tender@example.com', Registration::query()->first()->attendee_email);
    }

    private function mappedType(Event $event, string $variationId, float $price, string $name = 'General Admission'): TicketType
    {
        $ticketType = TicketType::factory()->paid($price)->create([
            'event_id' => $event->id,
            'name' => $name,
        ]);

        SquareCatalogMapping::query()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'square_catalog_item_id' => 'ITEM_'.$variationId,
            'square_catalog_variation_id' => $variationId,
            'sync_status' => SquareCatalogSyncStatus::Synced->value,
            'ems_managed' => true,
        ]);

        return $ticketType;
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
     * @param  list<array<string, mixed>>  $lineItems
     * @param  array<string, mixed>  $extra
     */
    private function fakeOrder(string $orderId, array $lineItems, array $extra = []): void
    {
        Http::fake([
            '*/v2/orders/'.$orderId => Http::response([
                'order' => array_merge([
                    'id' => $orderId,
                    'line_items' => $lineItems,
                ], $extra),
            ], 200),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function postOnlinePayment(
        string $eventId,
        string $paymentId,
        string $orderId,
        array $overrides = []
    ): \Illuminate\Testing\TestResponse {
        $payment = array_merge([
            'id' => $paymentId,
            'status' => 'COMPLETED',
            'order_id' => $orderId,
            'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
            'application_details' => ['square_product' => 'ONLINE_STORE'],
        ], $overrides);

        return $this->postWebhook([
            'event_id' => $eventId,
            'type' => 'payment.updated',
            'data' => ['object' => ['payment' => $payment]],
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
