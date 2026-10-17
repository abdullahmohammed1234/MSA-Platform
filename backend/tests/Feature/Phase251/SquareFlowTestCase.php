<?php

namespace Tests\Feature\Phase251;

use App\Ems\Models\Event;
use App\Ems\Models\Payment;
use App\Ems\Models\TicketType;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Feature\Ems\EmsTestCase;

/**
 * Real checkout + signed webhook flow against a faked Square HTTP API.
 * No request ever leaves the process.
 */
abstract class SquareFlowTestCase extends EmsTestCase
{
    protected int $linkCounter = 0;

    /** @var list<array<string, mixed>> Payments returned by the faked Square payments list. */
    protected array $squarePayments = [];

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

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/v2/catalog/')) {
                return Http::response(['objects' => [], 'id_mappings' => []], 200);
            }

            if (str_contains($request->url(), '/v2/online-checkout/payment-links')) {
                if ($request->method() === 'POST') {
                    $n = ++$this->linkCounter;

                    return Http::response(['payment_link' => [
                        'id' => "plink_{$n}",
                        'url' => "https://square.test/checkout/{$n}",
                        'order_id' => "sq_order_{$n}",
                    ]], 200);
                }

                return Http::response([], 200);
            }

            if (str_contains($request->url(), '/v2/payments') && $request->method() === 'GET') {
                return Http::response(['payments' => $this->squarePayments], 200);
            }

            return Http::response(['errors' => [['code' => 'NOT_FAKED']]], 404);
        });
    }

    protected function paidEvent(array $eventAttributes = [], ?int $ticketLimit = null): array
    {
        $category = $this->category(['is_active' => true]);
        $event = Event::factory()->publiclyDiscoverable()->create(array_merge([
            'category_id' => $category->id,
            'name' => 'Last Seat Gala',
            'slug' => 'last-seat-'.Str::lower(Str::random(6)),
            'capacity' => 100,
        ], $eventAttributes));

        $factory = TicketType::factory()->paid(15);
        if ($ticketLimit !== null) {
            $factory = $factory->limited($ticketLimit);
        }

        return [$event, $factory->create(['event_id' => $event->id, 'name' => 'GA'])];
    }

    protected function startCheckout(Event $event, TicketType $ticketType, string $email): \Illuminate\Testing\TestResponse
    {
        return $this->postJson($this->url("public/events/{$event->slug}/checkout"), [
            'first_name' => 'Test',
            'last_name' => Str::before($email, '@'),
            'email' => $email,
            'ticket_type_id' => $ticketType->uuid,
            'quantity' => 1,
        ]);
    }

    protected function paymentFor(string $email): Payment
    {
        return Payment::query()
            ->whereHas('order', fn ($q) => $q->where('buyer_email', $email))
            ->latest('id')
            ->firstOrFail();
    }

    protected function completedWebhook(Payment $payment, string $eventId, ?string $squarePaymentId = null, string $status = 'COMPLETED'): \Illuminate\Testing\TestResponse
    {
        $payment->loadMissing('order');

        return $this->postWebhook([
            'event_id' => $eventId,
            'type' => 'payment.updated',
            'data' => ['object' => ['payment' => [
                'id' => $squarePaymentId ?? 'sq_pay_'.$payment->id,
                'status' => $status,
                'order_id' => $payment->provider_order_id,
                'amount_money' => ['amount' => 1500, 'currency' => 'CAD'],
                'reference_id' => $payment->order->reference,
            ]]],
        ]);
    }

    protected function postWebhook(array $payload, ?string $signatureKey = 'webhook-secret'): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $url = 'https://example.test/api/v1/webhooks/square';
        $signature = base64_encode(hash_hmac('sha256', $url.$body, (string) $signatureKey, true));

        return $this->call('POST', '/api/v1/webhooks/square', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SQUARE_HMACSHA256_SIGNATURE' => $signature,
        ], $body);
    }

    protected function expireCheckout(Payment $payment): void
    {
        $payment->forceFill(['checkout_expires_at' => now()->subMinute()])->save();
        app(\App\Ems\Services\CheckoutLifecycleService::class)->expireStale();
    }
}
