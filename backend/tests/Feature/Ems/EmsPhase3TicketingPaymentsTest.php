<?php

namespace Tests\Feature\Ems;

use App\Ems\Enums\EventStatus;
use App\Ems\Enums\OrderStatus;
use App\Ems\Enums\PaymentStatus;
use App\Ems\Enums\RegistrationStatus;
use App\Ems\Enums\TicketStatus;
use App\Ems\Models\Event;
use App\Ems\Models\Order;
use App\Ems\Models\Payment;
use App\Ems\Models\Registration;
use App\Ems\Models\Ticket;
use App\Ems\Models\TicketType;
use App\Ems\Models\WaitlistEntry;
use App\Ems\Models\WebhookEvent;
use App\Ems\Services\PaymentFulfillmentService;
use App\Ems\Support\EmsRoles;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class EmsPhase3TicketingPaymentsTest extends EmsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ems.payments.enabled' => true,
            'ems.payments.square.access_token' => 'test-token',
            'ems.payments.square.location_id' => 'LOCATION_TEST',
            'ems.payments.square.webhook_signature_key' => 'webhook-secret',
            'ems.payments.square.webhook_notification_url' => 'https://example.test/api/v1/webhooks/square',
            'ems.payments.square.environment' => 'sandbox',
            'queue.default' => 'sync',
        ]);
    }

    protected function publicUrl(string $path = ''): string
    {
        return $this->url('public/' . ltrim($path, '/'));
    }

    protected function publicEvent(array $attributes = []): Event
    {
        $category = $this->category(['is_active' => true]);

        return Event::factory()
            ->publiclyDiscoverable()
            ->create(array_merge([
                'category_id' => $category->id,
                'name' => 'Gala Night',
                'slug' => 'gala-night-' . Str::lower(Str::random(4)),
                'capacity' => 100,
            ], $attributes));
    }

    protected function organizerFor(Event $event)
    {
        $user = $this->emsUser(EmsRoles::EVENT_ORGANIZER);
        $event->update(['organizer_id' => $user->id, 'created_by' => $user->id]);

        return $user;
    }

    public function test_organizer_can_create_and_list_ticket_types(): void
    {
        $event = $this->publicEvent();
        $user = $this->organizerFor($event);

        $create = $this->actingAsEms($user)->postJson($this->url("events/{$event->uuid}/tickets"), [
            'name' => 'MSA Member',
            'price' => 15,
            'currency' => 'CAD',
            'quantity' => 50,
        ]);

        $this->assertSuccessEnvelope($create);
        $create->assertCreated();
        $create->assertJsonPath('data.name', 'MSA Member');
        $create->assertJsonPath('data.price', 15);

        $list = $this->actingAsEms($user)->getJson($this->url("events/{$event->uuid}/tickets"));
        $this->assertSuccessEnvelope($list);
        $this->assertCount(1, $list->json('data'));
    }

    public function test_ticket_capacity_and_sold_out(): void
    {
        $event = $this->publicEvent(['capacity' => 10]);
        $ticket = TicketType::factory()->paid(25)->limited(1)->create([
            'event_id' => $event->id,
            'name' => 'VIP',
        ]);

        Http::fake([
            '*/v2/online-checkout/payment-links' => Http::response([
                'payment_link' => [
                    'id' => 'plink_1',
                    'url' => 'https://square.test/checkout/1',
                    'order_id' => 'sq_order_1',
                ],
            ], 200),
        ]);

        $first = $this->postJson($this->publicUrl("events/{$event->slug}/checkout"), [
            'first_name' => 'Amina',
            'last_name' => 'Ali',
            'email' => 'amina@example.com',
            'ticket_type_id' => $ticket->uuid,
            'quantity' => 1,
        ]);
        $first->assertCreated();
        $first->assertJsonPath('data.requires_payment', true);

        $second = $this->postJson($this->publicUrl("events/{$event->slug}/checkout"), [
            'first_name' => 'Omar',
            'last_name' => 'Hassan',
            'email' => 'omar@example.com',
            'ticket_type_id' => $ticket->uuid,
            'quantity' => 1,
        ]);
        $second->assertStatus(409);
        $this->assertErrorEnvelope($second);
    }

    public function test_free_ticket_type_issues_ticket_immediately(): void
    {
        $event = $this->publicEvent();
        $ticket = TicketType::factory()->free()->create([
            'event_id' => $event->id,
            'name' => 'General Free',
        ]);

        $response = $this->postJson($this->publicUrl("events/{$event->slug}/register"), [
            'first_name' => 'Sara',
            'last_name' => 'Khan',
            'email' => 'sara@example.com',
            'ticket_type_id' => $ticket->uuid,
        ]);

        $this->assertSuccessEnvelope($response);
        $response->assertCreated();
        $this->assertDatabaseHas('ems_registrations', [
            'attendee_email' => 'sara@example.com',
            'status' => RegistrationStatus::Confirmed->value,
        ]);
        $this->assertDatabaseHas('ems_tickets', [
            'status' => TicketStatus::Issued->value,
        ]);
        $this->assertSame(1, TicketType::find($ticket->id)->quantity_sold);
    }

    public function test_paid_checkout_creates_pending_order_without_tickets(): void
    {
        $event = $this->publicEvent();
        $ticket = TicketType::factory()->paid(40)->create([
            'event_id' => $event->id,
            'name' => 'VIP',
        ]);

        Http::fake([
            '*/v2/online-checkout/payment-links' => Http::response([
                'payment_link' => [
                    'id' => 'plink_vip',
                    'url' => 'https://square.test/checkout/vip',
                    'order_id' => 'sq_order_vip',
                ],
            ], 200),
        ]);

        $response = $this->postJson($this->publicUrl("events/{$event->slug}/checkout"), [
            'first_name' => 'Layla',
            'last_name' => 'Noor',
            'email' => 'layla@example.com',
            'ticket_type_id' => $ticket->uuid,
            'quantity' => 2,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.requires_payment', true);
        $response->assertJsonPath('data.checkout_url', 'https://square.test/checkout/vip');

        $registration = Registration::where('attendee_email', 'layla@example.com')->first();
        $this->assertNotNull($registration);
        $this->assertSame(RegistrationStatus::AwaitingPayment, $registration->status);
        $this->assertSame(0, $registration->tickets()->count());

        $order = Order::where('buyer_email', 'layla@example.com')->first();
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(80.0, (float) $order->total_amount);

        $payment = Payment::where('order_id', $order->id)->first();
        $this->assertSame(PaymentStatus::Processing, $payment->status);
        $this->assertSame('plink_vip', $payment->provider_checkout_id);
    }

    public function test_payment_fulfillment_issues_tickets_and_qr(): void
    {
        $event = $this->publicEvent();
        $ticketType = TicketType::factory()->paid(20)->create(['event_id' => $event->id]);

        $order = Order::factory()->create([
            'event_id' => $event->id,
            'total_amount' => 20,
            'status' => OrderStatus::Pending,
            'buyer_email' => 'paid@example.com',
        ]);

        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'attendee_email' => 'paid@example.com',
            'status' => RegistrationStatus::AwaitingPayment,
            'type' => 'paid',
            'quantity' => 1,
            'amount_due' => 20,
        ]);

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'registration_id' => $registration->id,
            'amount' => 20,
            'currency' => 'CAD',
            'provider' => 'square',
            'status' => PaymentStatus::Processing->value,
            'provider_checkout_id' => 'plink_x',
        ]);

        /** @var PaymentFulfillmentService $fulfillment */
        $fulfillment = app(PaymentFulfillmentService::class);
        $fulfillment->markPaid($payment, [
            'provider_payment_id' => 'sq_pay_1',
            'provider_transaction_id' => 'txn_1',
        ]);

        $registration->refresh();
        $order->refresh();
        $payment->refresh();

        $this->assertSame(RegistrationStatus::Confirmed, $registration->status);
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame(1, $registration->tickets()->count());
        $this->assertNotNull($registration->tickets()->first()->qr_payload);
    }

    public function test_webhook_signature_and_idempotency(): void
    {
        $event = $this->publicEvent();
        $ticketType = TicketType::factory()->paid(15)->create(['event_id' => $event->id]);
        $order = Order::factory()->create([
            'event_id' => $event->id,
            'reference' => 'ORD-TESTWEB1',
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
            'provider_order_id' => 'sq_order_web',
        ]);

        $payload = json_encode([
            'event_id' => 'evt_duplicate_1',
            'type' => 'payment.updated',
            'data' => [
                'object' => [
                    'payment' => [
                        'id' => 'sq_pay_web',
                        'status' => 'COMPLETED',
                        'order_id' => 'sq_order_web',
                        'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                        'reference_id' => 'ORD-TESTWEB1',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $url = 'https://example.test/api/v1/webhooks/square';
        $signature = base64_encode(hash_hmac('sha256', $url . $payload, 'webhook-secret', true));

        $first = $this->call(
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
            $payload
        );
        $first->assertOk();

        $this->assertSame(1, Ticket::where('registration_id', $registration->id)->count());
        $this->assertSame(1, WebhookEvent::count());

        $second = $this->call(
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
            $payload
        );
        $second->assertOk();

        $this->assertSame(1, Ticket::where('registration_id', $registration->id)->count());
        $this->assertSame(1, WebhookEvent::count());
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_invalid_webhook_signature_is_rejected(): void
    {
        $payload = json_encode(['event_id' => 'evt_bad', 'type' => 'payment.updated'], JSON_THROW_ON_ERROR);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/square',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_SQUARE_HMACSHA256_SIGNATURE' => 'invalid',
            ],
            $payload
        );

        $response->assertUnauthorized();
    }

    public function test_waitlist_join_and_queue_position(): void
    {
        $event = $this->publicEvent([
            'capacity' => 1,
            'waitlist_enabled' => true,
        ]);

        // Fill capacity.
        $this->postJson($this->publicUrl("events/{$event->slug}/register"), [
            'first_name' => 'First',
            'last_name' => 'Guest',
            'email' => 'first@example.com',
        ])->assertCreated();

        $wait = $this->postJson($this->publicUrl("events/{$event->slug}/waitlist"), [
            'first_name' => 'Second',
            'last_name' => 'Guest',
            'email' => 'second@example.com',
        ]);

        $wait->assertCreated();
        $wait->assertJsonPath('data.position', 1);
        $this->assertDatabaseHas('ems_waitlist_entries', [
            'attendee_email' => 'second@example.com',
            'position' => 1,
        ]);
        $this->assertSame(1, WaitlistEntry::count());
    }

    public function test_registration_limit_per_order(): void
    {
        $event = $this->publicEvent(['max_tickets_per_order' => 1]);
        $ticket = TicketType::factory()->free()->create(['event_id' => $event->id]);

        $response = $this->postJson($this->publicUrl("events/{$event->slug}/register"), [
            'first_name' => 'Limit',
            'last_name' => 'Test',
            'email' => 'limit@example.com',
            'ticket_type_id' => $ticket->uuid,
            'quantity' => 2,
        ]);

        $response->assertStatus(422);
        $this->assertErrorEnvelope($response);
    }

    public function test_payment_summary_endpoint(): void
    {
        $event = $this->publicEvent();
        $user = $this->organizerFor($event);
        TicketType::factory()->paid(10)->create(['event_id' => $event->id, 'name' => 'GA']);

        $response = $this->actingAsEms($user)
            ->getJson($this->url("events/{$event->uuid}/payment-summary"));

        $this->assertSuccessEnvelope($response);
        $response->assertJsonPath('data.paid_orders', 0);
        $response->assertJsonPath('data.currency', 'CAD');
    }

    public function test_public_event_includes_ticket_types(): void
    {
        $event = $this->publicEvent();
        TicketType::factory()->paid(25)->create([
            'event_id' => $event->id,
            'name' => 'General Admission',
            'is_visible' => true,
            'is_active' => true,
        ]);

        $response = $this->getJson($this->publicUrl("events/{$event->slug}"));
        $this->assertSuccessEnvelope($response);
        $this->assertNotEmpty($response->json('data.ticket_types'));
        $response->assertJsonPath('data.ticket_types.0.name', 'General Admission');
    }
}
